<?php

namespace Database\Seeders;

use App\Models\Treaty;
use App\Models\TreatySupportingDocument;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TreatySeeder extends Seeder
{
    /**
     * Relative file path inside the database directory.
     */
    private const SOURCE_FILE = 'treaty files/AU_Treaties_List_Alphabetical.xlsx';
    private const SUPPORTING_DOCUMENTS_DIR = 'treaty files/Treaties Contd';
    private const AU_TREATIES_INDEX_URL = 'https://au.int/en/treaties/';
    private const AU_BASE_URL = 'https://au.int';
    private const FOLDER_TITLE_ALIASES = [
        'additional protocol to the oau general convention on privileges and immunitie'
            => 'Additional Protocol to the OAU General Convention on the Privileges and Immunities of the OAU',
        'additional protocol to the oau general convention on privileges and immunities'
            => 'Additional Protocol to the OAU General Convention on the Privileges and Immunities of the OAU',
    ];

    public function run(): void
    {
        if (!Schema::hasTable('myb_treaties')) {
            $this->command?->warn('TreatySeeder skipped: table myb_treaties does not exist.');
            return;
        }

        $filePath = database_path(self::SOURCE_FILE);
        if (!is_file($filePath)) {
            $this->command?->warn('TreatySeeder skipped: source file not found at ' . $filePath . '.');
            return;
        }

        $titles = $this->loadTreatyTitlesFromWorkbook($filePath);

        if (empty($titles)) {
            $this->command?->warn('TreatySeeder skipped: no treaty titles found in workbook.');
            return;
        }

        $seedUserId = User::query()
            ->where('user_type', 'admin')
            ->value('id') ?? User::query()->oldest()->value('id');

        $officialTreatyIndex = $this->loadOfficialAuTreatyIndex();
        if (!empty($officialTreatyIndex)) {
            $this->command?->info('Loaded ' . count($officialTreatyIndex) . ' official AU treaty metadata rows from au.int.');
        }

        $synced = 0;
        foreach ($titles as $index => $title) {
            $treaty = Treaty::query()->firstOrNew(['title' => $title]);
            $isNew = !$treaty->exists;
            $officialMetadata = $this->findOfficialTreatyMetadata($title, $officialTreatyIndex);

            $treaty->short_title = Str::limit($title, 120, '');
            if ($this->shouldReplaceSeededDescription((string) $treaty->description)) {
                $treaty->description = $this->buildDescription($title, $officialMetadata);
            }
            if ($officialMetadata) {
                if (empty($treaty->adoption_date) && !empty($officialMetadata['adoption_date'])) {
                    $treaty->adoption_date = $officialMetadata['adoption_date'];
                }
                if (empty($treaty->entry_into_force_date) && !empty($officialMetadata['entry_into_force_date'])) {
                    $treaty->entry_into_force_date = $officialMetadata['entry_into_force_date'];
                }
                if (empty($treaty->read_more_url) && !empty($officialMetadata['url'])) {
                    $treaty->read_more_url = $officialMetadata['url'];
                }
            }
            if ($isNew && empty($treaty->status)) {
                $treaty->status = 'active';
            }

            if (empty($treaty->reference_code)) {
                $treaty->reference_code = $this->buildReferenceCode($title, $index + 1);
            }

            if ($isNew && $seedUserId) {
                $treaty->created_by = $seedUserId;
            }
            if ($seedUserId) {
                $treaty->updated_by = $seedUserId;
            }

            $treaty->save();
            $synced++;
        }

        $documentStats = $this->syncSupportingDocumentsFromFolders($seedUserId);
        $descriptionBackfills = $this->backfillSeededDescriptions($officialTreatyIndex, $seedUserId);

        $this->command?->info("TreatySeeder synced {$synced} treaties from workbook " . self::SOURCE_FILE . '.');
        if ($descriptionBackfills > 0) {
            $this->command?->info("TreatySeeder replaced {$descriptionBackfills} seeded placeholder treaty descriptions.");
        }
        $this->command?->info(
            'TreatySeeder synced '
            . $documentStats['documents_created']
            . ' new supporting PDF rows and updated '
            . $documentStats['documents_updated']
            . ' existing rows from '
            . $documentStats['folders_processed']
            . '/'
            . $documentStats['folders_total']
            . ' folders.'
        );

        if ($documentStats['treaties_created'] > 0) {
            $this->command?->warn(
                'TreatySeeder created '
                . $documentStats['treaties_created']
                . ' extra treaty records from document folders that were not present in the workbook.'
            );
        }
    }

    /**
     * @param array<string, array<string, ?string>> $officialTreatyIndex
     */
    private function backfillSeededDescriptions(array $officialTreatyIndex, ?string $seedUserId): int
    {
        $updated = 0;

        Treaty::query()
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '')
                    ->orWhere('description', 'like', 'Seed source:%');
            })
            ->orderBy('title')
            ->get()
            ->each(function (Treaty $treaty) use ($officialTreatyIndex, $seedUserId, &$updated): void {
                $title = (string) $treaty->title;
                $officialMetadata = $this->findOfficialTreatyMetadata($title, $officialTreatyIndex);

                $treaty->description = $this->buildDescription($title, $officialMetadata);

                if ($officialMetadata) {
                    if (empty($treaty->adoption_date) && !empty($officialMetadata['adoption_date'])) {
                        $treaty->adoption_date = $officialMetadata['adoption_date'];
                    }
                    if (empty($treaty->entry_into_force_date) && !empty($officialMetadata['entry_into_force_date'])) {
                        $treaty->entry_into_force_date = $officialMetadata['entry_into_force_date'];
                    }
                    if (empty($treaty->read_more_url) && !empty($officialMetadata['url'])) {
                        $treaty->read_more_url = $officialMetadata['url'];
                    }
                }

                if ($seedUserId) {
                    $treaty->updated_by = $seedUserId;
                }

                $treaty->save();
                $updated++;
            });

        return $updated;
    }

    /**
     * @return array<int, string>
     */
    private function loadTreatyTitlesFromWorkbook(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $exception) {
            Log::warning('TreatySeeder: unable to load source workbook.', [
                'path' => $filePath,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }

        $rows = $spreadsheet
            ->getActiveSheet()
            ->toArray(null, true, true, true);

        if (empty($rows)) {
            return [];
        }

        $headerRow = array_shift($rows) ?: [];
        $titleColumn = $this->detectTitleColumn($headerRow);

        if ($titleColumn === null) {
            Log::warning('TreatySeeder: treaty title column not found in workbook.', [
                'path' => $filePath,
                'headers' => array_values($headerRow),
            ]);
            return [];
        }

        $titlesByKey = [];
        foreach ($rows as $row) {
            $title = trim((string) ($row[$titleColumn] ?? ''));
            if ($title === '') {
                continue;
            }

            $key = Str::lower($title);
            if (!isset($titlesByKey[$key])) {
                $titlesByKey[$key] = $title;
            }
        }

        return array_values($titlesByKey);
    }

    /**
     * @return array<string, array<string, ?string>>
     */
    private function loadOfficialAuTreatyIndex(): array
    {
        try {
            $response = Http::timeout(45)
                ->retry(2, 750)
                ->accept('text/html')
                ->get(self::AU_TREATIES_INDEX_URL);

            if (!$response->successful()) {
                $this->command?->warn('TreatySeeder: AU treaty metadata request failed with HTTP ' . $response->status() . '.');
                return [];
            }
        } catch (\Throwable $exception) {
            $this->command?->warn('TreatySeeder: AU treaty metadata request skipped: ' . $exception->getMessage());
            return [];
        }

        return $this->parseOfficialAuTreatyRows($response->body());
    }

    /**
     * @return array<string, array<string, ?string>>
     */
    private function parseOfficialAuTreatyRows(string $html): array
    {
        if (trim($html) === '' || !class_exists(\DOMDocument::class)) {
            return [];
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $rows = [];

        foreach ($xpath->query('//tr') as $row) {
            $titleLink = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " views-field-title ")]//a', $row)->item(0);
            if (!$titleLink instanceof \DOMElement) {
                continue;
            }

            $title = trim(html_entity_decode($titleLink->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '') {
                continue;
            }

            $url = $this->absoluteAuUrl((string) $titleLink->getAttribute('href'));
            $metadata = [
                'title' => $title,
                'url' => $url,
                'adoption_date' => $this->extractDateFromRow($xpath, $row, 'views-field-field-date-adoption'),
                'entry_into_force_date' => $this->extractDateFromRow($xpath, $row, 'views-field-field-date-intoforce'),
                'signature_date' => $this->extractDateFromRow($xpath, $row, 'views-field-field-date-signature'),
                'category' => $this->extractTextFromRow($xpath, $row, 'views-field-nothing'),
            ];

            foreach ($this->buildMatchKeys($title) as $key) {
                $rows[$key] = $metadata;
            }
        }

        return $rows;
    }

    private function extractDateFromRow(\DOMXPath $xpath, \DOMNode $row, string $className): ?string
    {
        $cell = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ' . $className . ' ")]', $row)->item(0);
        if (!$cell instanceof \DOMElement) {
            return null;
        }

        $dateSpan = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " date-display-single ")]', $cell)->item(0);
        if ($dateSpan instanceof \DOMElement) {
            $contentDate = trim((string) $dateSpan->getAttribute('content'));
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $contentDate, $matches)) {
                return $matches[0];
            }
        }

        $dateText = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '');
        if ($dateText === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($dateText))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractTextFromRow(\DOMXPath $xpath, \DOMNode $row, string $className): ?string
    {
        $cell = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ' . $className . ' ")]', $row)->item(0);
        if (!$cell instanceof \DOMElement) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode($cell->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return $text === '' ? null : $text;
    }

    private function absoluteAuUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return self::AU_BASE_URL . '/' . ltrim($path, '/');
    }

    /**
     * @param array<string, array<string, ?string>> $officialTreatyIndex
     * @return array<string, ?string>|null
     */
    private function findOfficialTreatyMetadata(string $title, array $officialTreatyIndex): ?array
    {
        foreach ($this->buildMatchKeys($title) as $key) {
            if (isset($officialTreatyIndex[$key])) {
                return $officialTreatyIndex[$key];
            }
        }

        $titleTokens = $this->extractMatchTokens($title);
        if (count($titleTokens) < 4) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($officialTreatyIndex as $metadata) {
            $candidateTokens = $this->extractMatchTokens((string) ($metadata['title'] ?? ''));
            if (count($candidateTokens) < 4) {
                continue;
            }

            $intersection = count(array_intersect($titleTokens, $candidateTokens));
            $score = $intersection / max(count($titleTokens), count($candidateTokens));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $metadata;
            }
        }

        return $bestScore >= 0.82 ? $best : null;
    }

    /**
     * @return array{
     *     folders_total: int,
     *     folders_processed: int,
     *     documents_created: int,
     *     documents_updated: int,
     *     treaties_created: int
     * }
     */
    private function syncSupportingDocumentsFromFolders(?string $seedUserId): array
    {
        $rootPath = database_path(self::SUPPORTING_DOCUMENTS_DIR);
        if (!is_dir($rootPath)) {
            $this->command?->warn('TreatySeeder: supporting-document folder not found at ' . $rootPath . '.');

            return [
                'folders_total' => 0,
                'folders_processed' => 0,
                'documents_created' => 0,
                'documents_updated' => 0,
                'treaties_created' => 0,
            ];
        }

        $folders = File::directories($rootPath);
        if (empty($folders)) {
            return [
                'folders_total' => 0,
                'folders_processed' => 0,
                'documents_created' => 0,
                'documents_updated' => 0,
                'treaties_created' => 0,
            ];
        }

        /** @var Collection<int, Treaty> $treaties */
        $treaties = Treaty::query()->orderBy('title')->get();
        $lookup = $this->buildTreatyLookup($treaties);

        $foldersProcessed = 0;
        $documentsCreated = 0;
        $documentsUpdated = 0;
        $treatiesCreated = 0;

        foreach ($folders as $folderPath) {
            $folderName = basename($folderPath);
            $treaty = $this->resolveTreatyForFolder($folderName, $treaties, $lookup, $seedUserId, $treatiesCreated);

            $pdfFiles = collect(File::files($folderPath))
                ->filter(static fn ($file) => Str::lower($file->getExtension()) === 'pdf')
                ->values();

            if ($pdfFiles->isEmpty()) {
                continue;
            }

            $foldersProcessed++;

            foreach ($pdfFiles as $pdfFile) {
                $fileName = $pdfFile->getFilename();
                $storagePath = "treaties/{$treaty->id}/supporting-documents/{$fileName}";

                if (!$this->copyFileToLocalDisk($pdfFile->getPathname(), $storagePath)) {
                    Log::warning('TreatySeeder: failed to copy supporting document file.', [
                        'source' => $pdfFile->getPathname(),
                        'destination' => $storagePath,
                        'treaty_id' => $treaty->id,
                    ]);
                    continue;
                }

                $document = TreatySupportingDocument::query()->firstOrNew([
                    'treaty_id' => $treaty->id,
                    'file_name' => $fileName,
                ]);

                $isNew = !$document->exists;
                $needsSave = $isNew;

                if ($document->file_path !== $storagePath) {
                    $document->file_path = $storagePath;
                    $needsSave = true;
                }

                if (empty($document->title)) {
                    $document->title = $this->buildDocumentTitle($fileName);
                    $needsSave = true;
                }

                if (empty($document->document_type)) {
                    $document->document_type = 'pdf';
                    $needsSave = true;
                }

                if (empty($document->uploaded_by) && $seedUserId) {
                    $document->uploaded_by = $seedUserId;
                    $needsSave = true;
                }

                if ($needsSave) {
                    $document->save();
                    if ($isNew) {
                        $documentsCreated++;
                    } else {
                        $documentsUpdated++;
                    }
                }
            }
        }

        return [
            'folders_total' => count($folders),
            'folders_processed' => $foldersProcessed,
            'documents_created' => $documentsCreated,
            'documents_updated' => $documentsUpdated,
            'treaties_created' => $treatiesCreated,
        ];
    }

    /**
     * @param Collection<int, Treaty> $treaties
     * @param array<string, Treaty> $lookup
     */
    private function resolveTreatyForFolder(
        string $folderName,
        Collection $treaties,
        array &$lookup,
        ?string $seedUserId,
        int &$treatiesCreated
    ): Treaty {
        $aliasTitle = $this->resolveAliasTitleForFolder($folderName);
        if ($aliasTitle !== null) {
            foreach ($this->buildMatchKeys($aliasTitle) as $key) {
                if (isset($lookup[$key])) {
                    return $lookup[$key];
                }
            }
        }

        foreach ($this->buildMatchKeys($folderName) as $key) {
            if (isset($lookup[$key])) {
                return $lookup[$key];
            }
        }

        $matchedTreaty = $this->findHighConfidenceTreatyMatch($folderName, $treaties);
        if ($matchedTreaty) {
            foreach ($this->buildMatchKeys($folderName) as $key) {
                $lookup[$key] = $matchedTreaty;
            }

            return $matchedTreaty;
        }

        $title = $aliasTitle ?? $this->prepareTreatyTitleFromFolder($folderName);
        $treaty = Treaty::query()->firstOrNew(['title' => $title]);
        $isNew = !$treaty->exists;

        if (empty($treaty->short_title)) {
            $treaty->short_title = Str::limit($title, 120, '');
        }
        if (empty($treaty->description)) {
            $treaty->description = $this->buildFolderDescription($folderName);
        }
        if ($isNew && empty($treaty->status)) {
            $treaty->status = 'active';
        }
        if (empty($treaty->reference_code)) {
            $deterministicIndex = (int) sprintf('%u', crc32(Str::lower($title)));
            $treaty->reference_code = $this->buildReferenceCode($title, $deterministicIndex);
        }

        if ($isNew && $seedUserId) {
            $treaty->created_by = $seedUserId;
        }
        if ($seedUserId) {
            $treaty->updated_by = $seedUserId;
        }

        $treaty->save();

        if ($isNew) {
            $treaties->push($treaty);
            $treatiesCreated++;
        }

        foreach ($this->buildMatchKeys($treaty->title) as $key) {
            $lookup[$key] = $treaty;
        }
        foreach ($this->buildMatchKeys($folderName) as $key) {
            $lookup[$key] = $treaty;
        }

        return $treaty;
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
     * @param Collection<int, Treaty> $treaties
     * @return array<string, Treaty>
     */
    private function buildTreatyLookup(Collection $treaties): array
    {
        $lookup = [];

        foreach ($treaties as $treaty) {
            foreach ($this->buildMatchKeys($treaty->title) as $key) {
                if (!isset($lookup[$key])) {
                    $lookup[$key] = $treaty;
                }
            }
        }

        return $lookup;
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

    private function normalizeForMatching(string $value): string
    {
        $normalized = Str::ascii($value);
        $normalized = str_replace(['&', '_', '-'], [' and ', ' ', ' '], $normalized);
        $normalized = Str::lower($normalized);
        $normalized = preg_replace('/[^a-z0-9\s]+/u', ' ', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized ?? '');

        return trim((string) $normalized);
    }

    private function resolveAliasTitleForFolder(string $folderName): ?string
    {
        $normalized = $this->normalizeForMatching($folderName);

        return self::FOLDER_TITLE_ALIASES[$normalized] ?? null;
    }

    private function prepareTreatyTitleFromFolder(string $folderName): string
    {
        $title = str_replace('_', '\'', $folderName);
        $title = preg_replace('/\s+/u', ' ', $title ?? '');

        return trim((string) $title);
    }

    private function buildFolderDescription(string $folderName): string
    {
        return 'Seed source: folder database/' . self::SUPPORTING_DOCUMENTS_DIR . '/' . $folderName . '.';
    }

    private function buildDocumentTitle(string $fileName): string
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $title = str_replace(['_', '-'], ' ', (string) $baseName);
        $title = preg_replace('/\s+/u', ' ', $title ?? '');

        return Str::limit(trim((string) $title), 255, '');
    }

    private function copyFileToLocalDisk(string $sourcePath, string $destinationPath): bool
    {
        $disk = Storage::disk('local');

        if ($disk->exists($destinationPath)) {
            return true;
        }

        $stream = @fopen($sourcePath, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            return (bool) $disk->writeStream($destinationPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param array<string, mixed> $headerRow
     */
    private function detectTitleColumn(array $headerRow): ?string
    {
        foreach ($headerRow as $column => $value) {
            $header = Str::lower(trim((string) $value));
            if ($header === '') {
                continue;
            }

            if (Str::contains($header, 'treaty') || Str::contains($header, 'instrument')) {
                return (string) $column;
            }
        }

        return null;
    }

    /**
     * Keep generated codes deterministic and short for MySQL constraints.
     */
    private function buildReferenceCode(string $title, int $index): string
    {
        return 'AU-TRT-' . strtoupper(substr(sha1($title . '|' . $index), 0, 14));
    }

    /**
     * @param array<string, ?string>|null $officialMetadata
     */
    private function buildDescription(string $title, ?array $officialMetadata = null): string
    {
        if ($officialMetadata) {
            $parts = [
                'Official African Union treaty record for "' . $title . '".',
            ];

            if (!empty($officialMetadata['category'])) {
                $parts[] = 'It is listed by the African Union under ' . $officialMetadata['category'] . '.';
            }
            if (!empty($officialMetadata['adoption_date'])) {
                $parts[] = 'Adopted on ' . $this->formatOfficialDate($officialMetadata['adoption_date']) . '.';
            }
            if (!empty($officialMetadata['entry_into_force_date'])) {
                $parts[] = 'Entered into force on ' . $this->formatOfficialDate($officialMetadata['entry_into_force_date']) . '.';
            }
            if (!empty($officialMetadata['signature_date'])) {
                $parts[] = 'Opened for signature on ' . $this->formatOfficialDate($officialMetadata['signature_date']) . '.';
            }
            if (!empty($officialMetadata['url'])) {
                $parts[] = 'The official AU treaty page provides the treaty text and status-list references.';
            }

            return implode(' ', $parts);
        }

        return 'African Union treaty instrument titled "' . $title . '", tracked by ATTP for member-state signature, ratification, and instrument-submission status.';
    }

    private function shouldReplaceSeededDescription(string $description): bool
    {
        $description = trim($description);

        return $description === ''
            || Str::startsWith($description, 'Seed source:');
    }

    private function formatOfficialDate(?string $date): string
    {
        if (!$date) {
            return '';
        }

        try {
            return (new \DateTimeImmutable($date))->format('F j, Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
