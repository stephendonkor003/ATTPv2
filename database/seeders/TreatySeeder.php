<?php

namespace Database\Seeders;

use App\Models\Treaty;
use App\Models\User;
use Carbon\Carbon;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TreatySeeder extends Seeder
{
    /**
     * AU treaty register endpoints (same data, language/route variants).
     *
     * @var array<int, string>
     */
    private const SOURCE_URLS = [
        'https://au.int/treaties',
        'https://au.int/en/treaties',
    ];

    public function run(): void
    {
        if (!Schema::hasTable('myb_treaties')) {
            $this->command?->warn('TreatySeeder skipped: table myb_treaties does not exist.');
            return;
        }

        $records = $this->fetchTreatiesFromAuRegister();

        if (empty($records)) {
            $this->command?->warn('TreatySeeder skipped: unable to fetch or parse treaties from AU register.');
            return;
        }

        $seedUserId = User::query()
            ->where('user_type', 'admin')
            ->value('id') ?? User::query()->oldest()->value('id');

        $synced = 0;
        foreach ($records as $index => $record) {
            $treaty = Treaty::query()->firstOrNew(['title' => $record['title']]);
            $isNew = !$treaty->exists;

            $treaty->short_title = Str::limit($record['title'], 120, '');
            $treaty->description = $this->buildDescription($record);
            $treaty->adoption_date = $record['adoption_date'];
            $treaty->entry_into_force_date = $record['entry_into_force_date'];

            // Preserve archived records, otherwise derive status from entry-into-force date.
            if ($isNew || $treaty->status !== 'archived') {
                $treaty->status = $record['entry_into_force_date'] ? 'active' : 'draft';
            }

            if (empty($treaty->reference_code)) {
                $treaty->reference_code = $this->buildReferenceCode($record['title'], $index + 1);
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

        $this->command?->info("TreatySeeder synced {$synced} treaties from AU register.");
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     adoption_date: string|null,
     *     entry_into_force_date: string|null,
     *     last_update_date: string|null,
     *     category: string|null
     * }>
     */
    private function fetchTreatiesFromAuRegister(): array
    {
        foreach (self::SOURCE_URLS as $url) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                    ->timeout(45)
                    ->retry(2, 400)
                    ->accept('text/html')
                    ->get($url);

                if (!$response->successful()) {
                    Log::warning('TreatySeeder: AU register responded with non-success status.', [
                        'url' => $url,
                        'status' => $response->status(),
                    ]);
                    continue;
                }

                $records = $this->parseTreatyRowsFromHtml($response->body());
                if (!empty($records)) {
                    return $records;
                }

                Log::warning('TreatySeeder: AU register response parsed with zero treaty rows.', [
                    'url' => $url,
                    'status' => $response->status(),
                    'content_type' => $response->header('Content-Type'),
                    'body_preview' => Str::limit(preg_replace('/\s+/u', ' ', strip_tags($response->body())), 220),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('TreatySeeder: failed to fetch treaties from AU register URL.', [
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [];
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     adoption_date: string|null,
     *     entry_into_force_date: string|null,
     *     last_update_date: string|null,
     *     category: string|null
     * }>
     */
    private function parseTreatyRowsFromHtml(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);

        $rowNodes = $xpath->query("//table[contains(@class,'views-table')]//tr[td]");
        if (!$rowNodes || $rowNodes->length === 0) {
            $rowNodes = $xpath->query('//table//tr[td]');
        }

        libxml_clear_errors();

        if (!$rowNodes || $rowNodes->length === 0) {
            return [];
        }

        $recordsByTitle = [];

        foreach ($rowNodes as $rowNode) {
            $cells = $xpath->query('./td', $rowNode);
            if (!$cells || $cells->length < 4) {
                continue;
            }

            $title = $this->cleanText($cells->item(0));
            if ($title === '' || Str::lower($title) === 'treaty') {
                continue;
            }

            $record = [
                'title' => $title,
                'adoption_date' => $this->parseDate($this->cleanText($cells->item(1))),
                'entry_into_force_date' => $this->parseDate($this->cleanText($cells->item(2))),
                'last_update_date' => $this->parseDate($this->cleanText($cells->item(3))),
                'category' => $cells->length >= 5 ? $this->cleanText($cells->item($cells->length - 1)) : null,
            ];

            if ($record['category'] === 'Status List') {
                $record['category'] = null;
            }

            $this->mergeRecord($recordsByTitle, $record);
        }

        return array_values($recordsByTitle);
    }

    /**
     * @param array<string, array{
     *     title: string,
     *     adoption_date: string|null,
     *     entry_into_force_date: string|null,
     *     last_update_date: string|null,
     *     category: string|null
     * }> $recordsByTitle
     * @param array{
     *     title: string,
     *     adoption_date: string|null,
     *     entry_into_force_date: string|null,
     *     last_update_date: string|null,
     *     category: string|null
     * } $candidate
     */
    private function mergeRecord(array &$recordsByTitle, array $candidate): void
    {
        $key = Str::lower($candidate['title']);

        if (!isset($recordsByTitle[$key])) {
            $recordsByTitle[$key] = $candidate;
            return;
        }

        foreach (['adoption_date', 'entry_into_force_date', 'last_update_date', 'category'] as $field) {
            if (empty($recordsByTitle[$key][$field]) && !empty($candidate[$field])) {
                $recordsByTitle[$key][$field] = $candidate[$field];
            }
        }
    }

    private function cleanText(?DOMNode $node): string
    {
        if (!$node) {
            return '';
        }

        $text = html_entity_decode($node->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text ?? '');

        return trim((string) $text);
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-' || Str::lower($value) === 'n/a') {
            return null;
        }

        try {
            return Carbon::createFromFormat('F d, Y', $value)->toDateString();
        } catch (\Throwable $exception) {
            // Fallback parser for source date edge cases.
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable $innerException) {
                return null;
            }
        }
    }

    /**
     * Keep generated codes deterministic and short for MySQL constraints.
     */
    private function buildReferenceCode(string $title, int $index): string
    {
        return 'AU-TRT-' . strtoupper(substr(sha1($title . '|' . $index), 0, 14));
    }

    /**
     * @param array{
     *     title: string,
     *     adoption_date: string|null,
     *     entry_into_force_date: string|null,
     *     last_update_date: string|null,
     *     category: string|null
     * } $record
     */
    private function buildDescription(array $record): string
    {
        $parts = [
            'Seed source: AU Treaty Register (https://au.int/treaties).',
        ];

        if (!empty($record['category'])) {
            $parts[] = 'Category: ' . $record['category'] . '.';
        }

        if (!empty($record['last_update_date'])) {
            $parts[] = 'Last update in AU register: ' . Carbon::parse($record['last_update_date'])->format('F d, Y') . '.';
        }

        return implode(' ', $parts);
    }
}
