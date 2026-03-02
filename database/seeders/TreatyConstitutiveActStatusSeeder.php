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
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TreatyConstitutiveActStatusSeeder extends Seeder
{
    private const STATUS_FILES_DIR = 'treaty files/Treaties Contd';

    public function run(): void
    {
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
            $this->command?->warn('TreatyConstitutiveActStatusSeeder skipped: no status Excel files found.');
            return;
        }

        $memberStatesByNormalized = AuMemberState::query()
            ->get(['id', 'name'])
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

                $isRatified = !is_null($ratificationDate);
                $isSigned = !is_null($signatureDate) || $isRatified;

                $status = TreatyMemberStateStatus::query()->firstOrNew([
                    'treaty_id' => $treaty->id,
                    'member_state_id' => $memberState->id,
                ]);

                $isNew = !$status->exists;

                $status->is_signed = $isSigned;
                $status->signed_at = $isSigned ? ($signatureDate ?? $ratificationDate) : null;
                $status->is_ratified = $isRatified;
                $status->ratified_at = $ratificationDate;

                if ($isSigned && empty($status->signed_service_code)) {
                    $status->signed_service_code = TreatyMemberStateStatus::generateUniqueServiceCode('signed_service_code');
                }

                if ($isRatified && empty($status->ratified_service_code)) {
                    $status->ratified_service_code = TreatyMemberStateStatus::generateUniqueServiceCode('ratified_service_code');
                }

                if ($isSigned && !empty($status->signed_service_code) && empty($status->signed_service_code_verified_at)) {
                    $status->signed_service_code_verified_at = now();
                    if ($seedUserId && empty($status->signed_service_code_verified_by_user_id)) {
                        $status->signed_service_code_verified_by_user_id = $seedUserId;
                    }
                }

                if ($isRatified && !empty($status->ratified_service_code) && empty($status->ratified_service_code_verified_at)) {
                    $status->ratified_service_code_verified_at = now();
                    if ($seedUserId && empty($status->ratified_service_code_verified_by_user_id)) {
                        $status->ratified_service_code_verified_by_user_id = $seedUserId;
                    }
                }

                if ($isSigned && empty($status->signed_notes)) {
                    $status->signed_notes = $this->buildSignedNote($filePath);
                }

                if ($isRatified) {
                    $status->ratified_notes = $this->buildRatifiedNote($filePath, $depositDate, (string) $status->ratified_notes);
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

        $backfilledCodes = $this->backfillMissingServiceCodes($seedUserId);

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
            . '). Service-code backfill (signed: '
            . $backfilledCodes['signed_code']
            . ', ratified: '
            . $backfilledCodes['ratified_code']
            . '), verification backfill (signed: '
            . $backfilledCodes['signed_verified']
            . ', ratified: '
            . $backfilledCodes['ratified_verified']
            . ').'
        );
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

    private function buildRatifiedNote(string $filePath, ?Carbon $depositDate, string $existing): string
    {
        $base = 'Seeded from treaty status spreadsheet (' . $this->relativeStatusPath($filePath) . ').';
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
