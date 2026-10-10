<?php

namespace Database\Seeders;

use App\Models\AuMemberState;
use App\Models\Treaty;
use App\Models\TreatyMemberStateStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TreatyConstitutiveActStatusSeeder extends Seeder
{
    private const STATUS_FILES_DIR = 'treaty files/Treaties Contd';

    public function run(): void
    {
        if (
            !Schema::hasTable('myb_au_member_states')
            || !Schema::hasTable('myb_treaties')
            || !Schema::hasTable('myb_treaty_member_state_statuses')
        ) {
            $this->command?->warn('TreatyConstitutiveActStatusSeeder skipped: treaty/member-state tables are not ready.');
            return;
        }

        if (AuMemberState::query()->count() < 55) {
            $this->command?->warn('AU member states are incomplete. Seeding AU member states first.');
            $this->call(AuMemberStateSeeder::class);
        }

        if (Treaty::query()->count() === 0) {
            $this->command?->warn('Treaties are missing. Seeding treaties first.');
            $this->call(TreatySeeder::class);
        }

        $statusFiles = $this->discoverStatusFiles();
        if (empty($statusFiles)) {
            $this->command?->warn('TreatyConstitutiveActStatusSeeder: no status Excel files found; creating pending matrix rows only.');
        }

        /** @var Collection<int, AuMemberState> $memberStates */
        $memberStates = AuMemberState::query()
            ->ordered()
            ->get(['id', 'name', 'code', 'code_alpha2', 'sort_order']);

        $memberStatesByNormalized = $memberStates
            ->mapWithKeys(function (AuMemberState $state) {
                return [$this->normalizeCountryName($state->name) => $state];
            });

        if ($memberStatesByNormalized->isEmpty()) {
            $this->command?->warn('TreatyConstitutiveActStatusSeeder skipped: no AU member states found.');
            return;
        }

        /** @var Collection<int, Treaty> $treaties */
        $treaties = Treaty::query()
            ->withCount('supportingDocuments')
            ->get(['id', 'title']);

        if ($treaties->isEmpty()) {
            $this->command?->warn('TreatyConstitutiveActStatusSeeder skipped: no treaties available.');
            return;
        }

        $seedUserId = User::query()
            ->where('user_type', 'admin')
            ->value('id') ?? User::query()->oldest()->value('id');

        $aliases = $this->countryAliases();
        $filesProcessed = 0;
        $rowsProcessed = 0;
        $rowsSeeded = 0;
        $rowsUpdated = 0;
        $missingCountries = [];
        $missingTreaties = [];
        $filesWithUnknownLayout = [];

        foreach ($statusFiles as $filePath) {
            $folderName = basename(dirname($filePath));
            $treaty = $this->resolveTreatyForFolder($folderName, $treaties);

            if (!$treaty) {
                $missingTreaties[$folderName] = true;
                continue;
            }

            $rows = $this->loadSpreadsheetRows($filePath);
            if (empty($rows)) {
                continue;
            }

            $columnIndexes = $this->detectStatusColumns($rows);
            if ($columnIndexes === null) {
                $filesWithUnknownLayout[] = $this->relativeStatusPath($filePath);
                continue;
            }

            $fileRowsProcessed = 0;
            for ($i = $columnIndexes['header_row'] + 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $country = trim((string) ($row[$columnIndexes['country']] ?? ''));

                if ($this->shouldSkipCountryCell($country)) {
                    continue;
                }

                $normalizedSheetCountry = $this->normalizeCountryName($country);
                $lookupCountry = $aliases[$normalizedSheetCountry] ?? $normalizedSheetCountry;

                /** @var AuMemberState|null $memberState */
                $memberState = $memberStatesByNormalized->get($lookupCountry);
                if (!$memberState) {
                    $missingCountries[$country] = true;
                    continue;
                }

                $signatureDate = $this->parseSpreadsheetDate($row[$columnIndexes['signature']] ?? null);
                $ratificationDate = $this->parseSpreadsheetDate($row[$columnIndexes['ratification']] ?? null);
                $depositDate = $this->parseSpreadsheetDate($row[$columnIndexes['deposit']] ?? null);

                $isAcceded = !is_null($ratificationDate) && is_null($signatureDate);
                $isRatified = !is_null($ratificationDate) && !$isAcceded;
                $isSigned = !is_null($signatureDate);

                $status = TreatyMemberStateStatus::query()->firstOrNew([
                    'treaty_id' => $treaty->id,
                    'member_state_id' => $memberState->id,
                ]);

                $isNew = !$status->exists;

                if (!$isNew && !empty($status->official_status_source_url)) {
                    continue;
                }

                $hasImportedStatus = !empty($status->official_status_source_url)
                    || Str::contains((string) $status->signed_notes . ' ' . (string) $status->ratified_notes, 'treaty status spreadsheet');
                if (!$isNew && !$hasImportedStatus && ($status->is_signed || $status->is_ratified || $status->is_acceded || $status->is_original_submitted || $status->signed_document_path || $status->ratified_document_path || $status->original_document_path)) {
                    continue;
                }

                $status->is_signed = $isSigned;
                $status->signed_at = $signatureDate;
                $status->is_ratified = $isRatified;
                $status->ratified_at = $isRatified ? $ratificationDate : null;
                $status->is_acceded = $isAcceded;
                $status->acceded_at = $isAcceded ? $ratificationDate : null;
                $status->instrument_deposited_at = $depositDate;

                if ($isSigned && empty($status->signed_notes)) {
                    $status->signed_notes = $this->buildSignedNote($filePath);
                }

                if ($isRatified || $isAcceded) {
                    $status->ratified_notes = $this->buildInstrumentStatusNote(
                        $filePath,
                        $depositDate,
                        (string) $status->ratified_notes,
                        $isAcceded
                    );
                }

                if ($seedUserId) {
                    $status->updated_by = $seedUserId;
                }

                $status->save();

                $fileRowsProcessed++;
                if ($isNew) {
                    $rowsSeeded++;
                } else {
                    $rowsUpdated++;
                }
            }

            if ($fileRowsProcessed > 0) {
                $filesProcessed++;
                $rowsProcessed += $fileRowsProcessed;
            }
        }

        $snapshotStats = $this->syncOfficialStatusSnapshot($memberStates, $memberStatesByNormalized, $aliases, $treaties, $seedUserId);

        if (!empty($missingTreaties)) {
            $this->command?->warn(
                'TreatyConstitutiveActStatusSeeder missing treaty matches for folders: '
                . implode(', ', array_keys($missingTreaties))
            );
        }

        if (!empty($missingCountries)) {
            $this->command?->warn(
                'TreatyConstitutiveActStatusSeeder missing member-state matches: '
                . implode(', ', array_keys($missingCountries))
            );
        }

        if (!empty($filesWithUnknownLayout)) {
            $this->command?->warn(
                'TreatyConstitutiveActStatusSeeder skipped files with unknown layout: '
                . implode(', ', $filesWithUnknownLayout)
            );
        }

        $matrixRowsCreated = $this->ensureCompleteMemberStateMatrix($treaties, $memberStates, $seedUserId);

        $this->command?->info(
            'TreatyConstitutiveActStatusSeeder processed '
            . $rowsProcessed
            . ' status rows across '
            . $filesProcessed
            . '/'
            . count($statusFiles)
            . ' files (new: '
            . $rowsSeeded
            . ', updated: '
            . $rowsUpdated
            . '), full matrix rows created: '
            . $matrixRowsCreated
            . '.'
        );
        if ($snapshotStats['treaties'] > 0) {
            $this->command?->info('TreatyConstitutiveActStatusSeeder imported ' . $snapshotStats['rows'] . ' current AU status rows from ' . $snapshotStats['treaties'] . ' official PDFs (as of ' . $snapshotStats['latest_as_of'] . ').');
        }
    }

    /**
     * @param Collection<int, AuMemberState> $memberStates
     * @param Collection<string, AuMemberState> $memberStatesByNormalized
     * @param array<string, string> $aliases
     * @param Collection<int, Treaty> $treaties
     * @return array{treaties:int,rows:int,latest_as_of:string}
     */
    private function syncOfficialStatusSnapshot(Collection $memberStates, Collection $memberStatesByNormalized, array $aliases, Collection $treaties, ?string $seedUserId): array
    {
        $path = database_path('treaty files/AU_Treaty_Status_Snapshot.json');
        if (!File::exists($path)) {
            return ['treaties' => 0, 'rows' => 0, 'latest_as_of' => 'unavailable'];
        }

        try {
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            Log::warning('Treaty status snapshot could not be read.', ['error' => $exception->getMessage()]);
            return ['treaties' => 0, 'rows' => 0, 'latest_as_of' => 'unavailable'];
        }

        $rowsSynced = 0;
        $treatiesSynced = 0;
        $latestAsOf = null;
        $unmatched = [];
        $missingCountries = [];
        foreach (($snapshot['treaties'] ?? []) as $snapshotTreaty) {
            $treatyTitle = (string) ($snapshotTreaty['title'] ?? '');
            $treaty = $this->resolveTreatyForFolder($treatyTitle, $treaties);
            $statusList = $snapshotTreaty['status_list'] ?? [];
            if (!$treaty || empty($statusList['pdf_url']) || empty($statusList['member_states'])) {
                $unmatched[] = $treatyTitle;
                continue;
            }

            $asOf = $statusList['as_of'] ?? null;
            if ($asOf && (!$latestAsOf || $asOf > $latestAsOf)) {
                $latestAsOf = $asOf;
            }

            foreach ($statusList['member_states'] as $row) {
                $country = trim((string) ($row['country'] ?? ''));
                $normalized = $this->normalizeCountryName($country);
                if (Str::startsWith($normalized, 'c te d ivoire')) {
                    $normalized = $this->normalizeCountryName('Cote d Ivoire');
                }
                $lookupCountry = $aliases[$normalized] ?? $normalized;
                $memberState = $memberStatesByNormalized->get($lookupCountry);
                if (!$memberState) {
                    $missingCountries[$country] = true;
                    continue;
                }

                $signedDate = $this->parseSpreadsheetDate($row['signed_at'] ?? null);
                $completionDate = $this->parseSpreadsheetDate($row['ratification_or_accession_at'] ?? null);
                $depositDate = $this->parseSpreadsheetDate($row['instrument_deposited_at'] ?? null);
                // AU publishes one ratification/accession date; without a signature date, accession is the best source-based classification.
                $isAcceded = $completionDate !== null && $signedDate === null;
                $isRatified = $completionDate !== null && !$isAcceded;
                $sourceUrl = (string) $statusList['pdf_url'];
                $status = TreatyMemberStateStatus::query()->firstOrNew([
                    'treaty_id' => $treaty->id,
                    'member_state_id' => $memberState->id,
                ]);
                $isNew = !$status->exists;
                $isSourceManaged = $isNew
                    || !empty($status->official_status_source_url)
                    || Str::contains((string) $status->signed_notes . ' ' . (string) $status->ratified_notes, 'treaty status spreadsheet');
                if (!$isSourceManaged && ($status->is_signed || $status->is_ratified || $status->is_acceded || $status->is_original_submitted || $status->signed_document_path || $status->ratified_document_path || $status->original_document_path)) {
                    continue;
                }

                $status->is_signed = $signedDate !== null;
                $status->signed_at = $signedDate;
                $status->is_ratified = $isRatified;
                $status->ratified_at = $isRatified ? $completionDate : null;
                $status->is_acceded = $isAcceded;
                $status->acceded_at = $isAcceded ? $completionDate : null;
                $status->instrument_deposited_at = $depositDate;
                $status->official_status_as_of = $asOf;
                $status->official_status_source_url = $sourceUrl;

                if (empty($status->signed_by_user_id) && empty($status->signed_document_path)) {
                    $status->signed_service_code = null;
                    $status->signed_service_code_verified_at = null;
                    $status->signed_service_code_verified_by_user_id = null;
                }
                if (empty($status->ratified_by_user_id) && empty($status->ratified_document_path)) {
                    $status->ratified_service_code = null;
                    $status->ratified_service_code_verified_at = null;
                    $status->ratified_service_code_verified_by_user_id = null;
                }

                $statusNote = 'AU official treaty status list dated ' . ($asOf ?: 'unknown') . ': ' . $sourceUrl;
                if ($signedDate && (empty($status->signed_notes) || Str::contains((string) $status->signed_notes, 'treaty status spreadsheet') || Str::contains((string) $status->signed_notes, 'AU official treaty status list'))) {
                    $status->signed_notes = $statusNote;
                }
                if (($isRatified || $isAcceded) && (empty($status->ratified_notes) || Str::contains((string) $status->ratified_notes, 'treaty status spreadsheet') || Str::contains((string) $status->ratified_notes, 'AU official treaty status list'))) {
                    $status->ratified_notes = $statusNote;
                }
                if ($seedUserId) {
                    $status->updated_by = $seedUserId;
                }
                $status->save();
                $rowsSynced++;
            }

            $treatiesSynced++;
        }

        if (!empty($unmatched)) {
            $this->command?->warn('AU status lists without matching treaty records: ' . implode('; ', $unmatched));
        }
        if (!empty($missingCountries)) {
            $this->command?->warn('AU status list countries without member-state matches: ' . implode(', ', array_keys($missingCountries)));
        }

        return ['treaties' => $treatiesSynced, 'rows' => $rowsSynced, 'latest_as_of' => $latestAsOf ?: 'unknown'];
    }

    /**
     * Ensure every treaty has a status row for every AU member state, even when
     * the country has not yet signed or ratified the instrument.
     *
     * @param Collection<int, Treaty> $treaties
     * @param Collection<int, AuMemberState> $memberStates
     */
    private function ensureCompleteMemberStateMatrix(Collection $treaties, Collection $memberStates, ?string $seedUserId): int
    {
        if ($treaties->isEmpty() || $memberStates->isEmpty()) {
            return 0;
        }

        $existingPairs = TreatyMemberStateStatus::query()
            ->get(['treaty_id', 'member_state_id'])
            ->mapWithKeys(function (TreatyMemberStateStatus $status) {
                return [$status->treaty_id . '|' . $status->member_state_id => true];
            })
            ->all();

        $hasOriginalSubmitted = Schema::hasColumn('myb_treaty_member_state_statuses', 'is_original_submitted');
        $now = now();
        $pendingRows = [];
        $created = 0;

        foreach ($treaties as $treaty) {
            foreach ($memberStates as $memberState) {
                $pairKey = $treaty->id . '|' . $memberState->id;
                if (isset($existingPairs[$pairKey])) {
                    continue;
                }

                $row = [
                    'id' => (string) Str::uuid(),
                    'treaty_id' => $treaty->id,
                    'member_state_id' => $memberState->id,
                    'is_signed' => false,
                    'is_ratified' => false,
                    'updated_by' => $seedUserId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($hasOriginalSubmitted) {
                    $row['is_original_submitted'] = false;
                }

                $pendingRows[] = $row;
                $existingPairs[$pairKey] = true;

                if (count($pendingRows) >= 500) {
                    TreatyMemberStateStatus::query()->insert($pendingRows);
                    $created += count($pendingRows);
                    $pendingRows = [];
                }
            }
        }

        if (!empty($pendingRows)) {
            TreatyMemberStateStatus::query()->insert($pendingRows);
            $created += count($pendingRows);
        }

        return $created;
    }

    /**
     * @return array{
     *     signed_code: int,
     *     ratified_code: int,
     *     signed_verified: int,
     *     ratified_verified: int
     * }
     */
    private function backfillMissingServiceCodes(?string $seedUserId): array
    {
        $signedCodeBackfilled = 0;
        $ratifiedCodeBackfilled = 0;
        $signedVerifiedBackfilled = 0;
        $ratifiedVerifiedBackfilled = 0;

        $signedMissing = TreatyMemberStateStatus::query()
            ->where('is_signed', true)
            ->where(function ($query) {
                $query->whereNull('signed_service_code')
                    ->orWhere('signed_service_code', '');
            })
            ->get();

        foreach ($signedMissing as $status) {
            $status->signed_service_code = TreatyMemberStateStatus::generateUniqueServiceCode('signed_service_code');
            if ($seedUserId) {
                $status->updated_by = $seedUserId;
            }
            $status->save();
            $signedCodeBackfilled++;
        }

        $ratifiedMissing = TreatyMemberStateStatus::query()
            ->where('is_ratified', true)
            ->where(function ($query) {
                $query->whereNull('ratified_service_code')
                    ->orWhere('ratified_service_code', '');
            })
            ->get();

        foreach ($ratifiedMissing as $status) {
            $status->ratified_service_code = TreatyMemberStateStatus::generateUniqueServiceCode('ratified_service_code');
            if ($seedUserId) {
                $status->updated_by = $seedUserId;
            }
            $status->save();
            $ratifiedCodeBackfilled++;
        }

        $signedUnverified = TreatyMemberStateStatus::query()
            ->where('is_signed', true)
            ->whereNotNull('signed_service_code')
            ->where('signed_service_code', '!=', '')
            ->whereNull('signed_service_code_verified_at')
            ->get();

        foreach ($signedUnverified as $status) {
            $status->signed_service_code_verified_at = now();
            if ($seedUserId && empty($status->signed_service_code_verified_by_user_id)) {
                $status->signed_service_code_verified_by_user_id = $seedUserId;
            }
            if ($seedUserId) {
                $status->updated_by = $seedUserId;
            }
            $status->save();
            $signedVerifiedBackfilled++;
        }

        $ratifiedUnverified = TreatyMemberStateStatus::query()
            ->where('is_ratified', true)
            ->whereNotNull('ratified_service_code')
            ->where('ratified_service_code', '!=', '')
            ->whereNull('ratified_service_code_verified_at')
            ->get();

        foreach ($ratifiedUnverified as $status) {
            $status->ratified_service_code_verified_at = now();
            if ($seedUserId && empty($status->ratified_service_code_verified_by_user_id)) {
                $status->ratified_service_code_verified_by_user_id = $seedUserId;
            }
            if ($seedUserId) {
                $status->updated_by = $seedUserId;
            }
            $status->save();
            $ratifiedVerifiedBackfilled++;
        }

        return [
            'signed_code' => $signedCodeBackfilled,
            'ratified_code' => $ratifiedCodeBackfilled,
            'signed_verified' => $signedVerifiedBackfilled,
            'ratified_verified' => $ratifiedVerifiedBackfilled,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function discoverStatusFiles(): array
    {
        $rootPath = database_path(self::STATUS_FILES_DIR);
        if (!is_dir($rootPath)) {
            return [];
        }

        $files = [];
        foreach (File::directories($rootPath) as $directory) {
            foreach (File::files($directory) as $file) {
                if (Str::lower($file->getExtension()) === 'xlsx') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function loadSpreadsheetRows(string $filePath): array
    {
        try {
            return IOFactory::load($filePath)
                ->getActiveSheet()
                ->toArray(null, true, true, false);
        } catch (\Throwable $exception) {
            Log::warning('TreatyConstitutiveActStatusSeeder: failed to load status spreadsheet.', [
                'path' => $filePath,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @return array{
     *     header_row: int,
     *     country: int,
     *     signature: int,
     *     ratification: int,
     *     deposit: int
     * }|null
     */
    private function detectStatusColumns(array $rows): ?array
    {
        $maxHeaderRows = min(10, count($rows));

        for ($headerRow = 0; $headerRow < $maxHeaderRows; $headerRow++) {
            $columns = [
                'country' => null,
                'signature' => null,
                'ratification' => null,
                'deposit' => null,
            ];

            foreach ($rows[$headerRow] as $index => $value) {
                $header = $this->normalizeHeader((string) $value);

                if ($header === '') {
                    continue;
                }

                if ($columns['country'] === null && (Str::contains($header, 'country') || Str::contains($header, 'pays'))) {
                    $columns['country'] = $index;
                }
                if ($columns['signature'] === null && Str::contains($header, 'signature')) {
                    $columns['signature'] = $index;
                }
                if (
                    $columns['ratification'] === null
                    && (Str::contains($header, 'ratification') || Str::contains($header, 'accession'))
                ) {
                    $columns['ratification'] = $index;
                }
                if ($columns['deposit'] === null && Str::contains($header, 'deposit')) {
                    $columns['deposit'] = $index;
                }
            }

            if (
                $columns['country'] !== null
                && $columns['signature'] !== null
                && $columns['ratification'] !== null
                && $columns['deposit'] !== null
            ) {
                return [
                    'header_row' => $headerRow,
                    'country' => (int) $columns['country'],
                    'signature' => (int) $columns['signature'],
                    'ratification' => (int) $columns['ratification'],
                    'deposit' => (int) $columns['deposit'],
                ];
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = Str::ascii($header);
        $normalized = Str::lower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/u', ' ', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized ?? '');

        return trim((string) $normalized);
    }

    private function shouldSkipCountryCell(string $country): bool
    {
        if ($country === '') {
            return true;
        }

        $normalized = $this->normalizeCountryName($country);
        if ($normalized === '') {
            return true;
        }

        return Str::startsWith($normalized, 'total countries');
    }

    private function buildSignedNote(string $filePath): string
    {
        return 'Seeded from treaty status spreadsheet (' . $this->relativeStatusPath($filePath) . ').';
    }

    private function buildInstrumentStatusNote(string $filePath, ?Carbon $depositDate, string $existing, bool $isAcceded): string
    {
        $state = $isAcceded ? 'Accession' : 'Ratification';
        $base = 'AU ' . $state . ' status from treaty status spreadsheet (' . $this->relativeStatusPath($filePath) . ').';
        $depositPart = $depositDate ? ' Deposit date: ' . $depositDate->format('d/m/Y') . '.' : '';
        $appended = trim($base . $depositPart);

        if ($existing !== '' && !Str::contains($existing, $base)) {
            return trim($existing . ' ' . $appended);
        }

        return $existing !== '' ? $existing : $appended;
    }

    private function relativeStatusPath(string $absolutePath): string
    {
        $root = database_path();
        $relative = Str::replaceFirst($root . DIRECTORY_SEPARATOR, '', $absolutePath);

        return str_replace('\\', '/', $relative);
    }

    private function parseSpreadsheetDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            } catch (\Throwable $exception) {
                return null;
            }
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '-' || Str::lower($text) === 'n/a') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $text)->startOfDay();
            } catch (\Throwable $exception) {
                // Continue trying other formats.
            }
        }

        try {
            return Carbon::parse($text)->startOfDay();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * @param Collection<int, Treaty> $treaties
     */
    private function resolveTreatyForFolder(string $folderName, Collection $treaties): ?Treaty
    {
        $folderKeys = $this->buildMatchKeys($folderName);

        $candidates = $treaties->filter(function (Treaty $treaty) use ($folderKeys) {
            $titleKeys = $this->buildMatchKeys($treaty->title);

            return count(array_intersect($folderKeys, $titleKeys)) > 0;
        })->values();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($candidates->count() > 1) {
            $exactTitle = Str::lower(trim($folderName));
            $exact = $candidates->first(function (Treaty $treaty) use ($exactTitle) {
                return Str::lower(trim($treaty->title)) === $exactTitle;
            });

            if ($exact) {
                return $exact;
            }

            return $candidates
                ->sortByDesc('supporting_documents_count')
                ->sortByDesc(function (Treaty $treaty) use ($folderName) {
                    return Str::lower(trim($treaty->title)) === Str::lower(trim($folderName)) ? 1 : 0;
                })
                ->first();
        }

        return $this->findHighConfidenceTreatyMatch($folderName, $treaties);
    }

    /**
     * @return array<int, string>
     */
    private function buildMatchKeys(string $value): array
    {
        $keys = [];

        $normalized = $this->normalizeForMatching($value);
        if ($normalized !== '') {
            $keys[] = $normalized;
        }

        $withoutParentheses = preg_replace('/\([^)]*\)/u', ' ', $value) ?? $value;
        $normalizedWithoutParentheses = $this->normalizeForMatching($withoutParentheses);
        if ($normalizedWithoutParentheses !== '') {
            $keys[] = $normalizedWithoutParentheses;
        }

        return array_values(array_unique($keys));
    }

    private function normalizeForMatching(string $value): string
    {
        $normalized = Str::ascii($value);
        $normalized = str_replace(['&', '_', '-'], [' and ', ' ', ' '], $normalized);
        $normalized = Str::lower($normalized);
        $normalized = preg_replace('/[^a-z0-9\s]+/u', ' ', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized ?? '');

        return trim((string) $normalized);
    }

    /**
     * @param Collection<int, Treaty> $treaties
     */
    private function findHighConfidenceTreatyMatch(string $folderName, Collection $treaties): ?Treaty
    {
        $folderTokens = $this->extractMatchTokens($folderName);
        if (count($folderTokens) < 4) {
            return null;
        }

        $candidates = [];

        foreach ($treaties as $treaty) {
            $treatyTokens = $this->extractMatchTokens($treaty->title);
            if (empty($treatyTokens)) {
                continue;
            }

            $intersectionCount = count(array_intersect($folderTokens, $treatyTokens));
            if ($intersectionCount === 0) {
                continue;
            }

            $folderCoverage = $intersectionCount / count($folderTokens);
            $treatyCoverage = $intersectionCount / count($treatyTokens);

            if ($folderCoverage >= 0.95 && $treatyCoverage >= 0.80 && $intersectionCount >= 4) {
                $candidates[] = [
                    'treaty' => $treaty,
                    'folder_coverage' => $folderCoverage,
                    'treaty_coverage' => $treatyCoverage,
                    'intersection_count' => $intersectionCount,
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, static function (array $left, array $right): int {
            if ($left['folder_coverage'] !== $right['folder_coverage']) {
                return $left['folder_coverage'] < $right['folder_coverage'] ? 1 : -1;
            }
            if ($left['treaty_coverage'] !== $right['treaty_coverage']) {
                return $left['treaty_coverage'] < $right['treaty_coverage'] ? 1 : -1;
            }
            if ($left['intersection_count'] !== $right['intersection_count']) {
                return $left['intersection_count'] < $right['intersection_count'] ? 1 : -1;
            }

            return 0;
        });

        if (count($candidates) > 1) {
            $best = $candidates[0];
            $runnerUp = $candidates[1];
            if (
                $best['folder_coverage'] === $runnerUp['folder_coverage']
                && $best['treaty_coverage'] === $runnerUp['treaty_coverage']
                && $best['intersection_count'] === $runnerUp['intersection_count']
            ) {
                return null;
            }
        }

        return $candidates[0]['treaty'];
    }

    /**
     * @return array<int, string>
     */
    private function extractMatchTokens(string $value): array
    {
        $stopWords = [
            'a', 'an', 'and', 'at', 'by', 'for', 'from', 'in', 'into', 'its', 'of', 'on', 'or',
            'relating', 'the', 'to', 'towards', 'within',
        ];

        $aliases = [
            'african' => 'africa',
            'immunitie' => 'immunities',
            'peoples' => 'people',
        ];

        $withoutParentheses = preg_replace('/\([^)]*\)/u', ' ', $value) ?? $value;
        $normalized = $this->normalizeForMatching($withoutParentheses);

        if ($normalized === '') {
            return [];
        }

        $tokenMap = [];
        foreach (explode(' ', $normalized) as $token) {
            if ($token === '') {
                continue;
            }

            $token = $aliases[$token] ?? $token;
            if (in_array($token, $stopWords, true)) {
                continue;
            }

            $tokenMap[$token] = true;
        }

        return array_keys($tokenMap);
    }

    /**
     * Normalize names to map spreadsheet entries and local member-state names reliably.
     */
    private function normalizeCountryName(string $name): string
    {
        $normalized = Str::ascii($name);
        $normalized = str_replace('&', ' and ', $normalized);
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', ' ', strtolower($normalized));
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);

        return trim((string) $normalized);
    }

    /**
     * @return array<string, string>
     */
    private function countryAliases(): array
    {
        return [
            $this->normalizeCountryName('Central African Rep.') => $this->normalizeCountryName('Central African Republic'),
            $this->normalizeCountryName('Cape Verde') => $this->normalizeCountryName('Cabo Verde'),
            $this->normalizeCountryName('Democratic Rep. of Congo') => $this->normalizeCountryName('Democratic Republic of the Congo'),
            $this->normalizeCountryName('Sao Tome & Principe') => $this->normalizeCountryName('Sao Tome and Principe'),
            $this->normalizeCountryName('Swaziland') => $this->normalizeCountryName('Eswatini'),
        ];
    }
}
