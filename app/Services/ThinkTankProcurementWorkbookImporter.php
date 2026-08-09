<?php

namespace App\Services;

use App\Models\ConsortiumThinkTank;
use App\Models\Consortium;
use App\Models\ThinkTankProcurementImportBatch;
use App\Models\ThinkTankProcurementImportRow;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class ThinkTankProcurementWorkbookImporter
{
    private const HEADER_ALIASES = [
        'title' => ['activity reference no description', 'procurement description', 'description of procurement', 'contract description', 'activity description', 'item description', 'description', 'procurement item', 'activity'],
        'source_reference' => ['reference no', 'reference number', 'procurement reference', 'contract reference', 'package number', 'package no', 'ref no', 'reference', 'id'],
        'procurement_category' => ['procurement category', 'category', 'type of procurement', 'procurement type'],
        'procurement_method' => ['procurement method', 'selection method', 'method', 'method of procurement'],
        'market_approach' => ['market approach', 'approach to market', 'market'],
        'review_type' => ['review type', 'bank review', 'prior post review', 'prior/post'],
        'quantity' => ['quantity', 'qty'],
        'unit' => ['unit of measure', 'unit'],
        'estimated_unit_cost' => ['estimated unit cost', 'unit cost'],
        'estimated_amount' => ['estimated amount us', 'estimated amount', 'estimated cost', 'estimated budget', 'budget', 'total amount', 'amount usd', 'cost'],
        'currency' => ['currency', 'curr'],
        'planned_quarter' => ['planned quarter', 'quarter', 'qtr'],
        'planned_start_date' => ['planned start date', 'start date', 'launch date', 'planned date'],
        'planned_end_date' => ['planned end date', 'end date', 'completion date'],
        'fiscal_year' => ['fiscal year', 'financial year', 'fy'],
        'loan_credit_no' => ['loan credit no'],
        'component' => ['component'],
        'source_in_process' => ['in process'],
        'source_process_status' => ['process status'],
        'source_activity_status' => ['activity status'],
        'source_document_type' => ['procurement document type'],
        'source_sea_sh_risk' => ['high sea sh risk'],
    ];

    public function import(string $path, ?string $memberId = null, bool $force = false): array
    {
        $resolvedPath = realpath($path);
        if (! $resolvedPath || ! is_file($resolvedPath)) {
            throw new RuntimeException("Workbook not found: {$path}");
        }

        $checksum = hash_file('sha256', $resolvedPath);
        $existing = ThinkTankProcurementImportBatch::query()->where('source_checksum', $checksum)->first();
        if ($existing && ! $force) {
            return ['status' => 'skipped', 'batch' => $existing, 'summary' => $existing->summary ?? []];
        }

        if ($existing && $force) {
            $existing->delete();
        }

        $forcedMember = $memberId ? ConsortiumThinkTank::with('consortium')->findOrFail($memberId) : null;
        $consortium = $forcedMember?->consortium ?: $this->matchConsortium($resolvedPath);
        $archivePath = 'think-tank-procurement-imports/'.$checksum.'/'.basename($resolvedPath);
        $stream = fopen($resolvedPath, 'rb');
        if (! $stream || ! Storage::disk('local')->put($archivePath, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('The source workbook could not be archived before import.');
        }
        if (is_resource($stream)) {
            fclose($stream);
        }

        $batch = ThinkTankProcurementImportBatch::create([
            'source_path' => $resolvedPath,
            'original_name' => basename($resolvedPath),
            'archive_path' => $archivePath,
            'file_size' => filesize($resolvedPath) ?: 0,
            'source_checksum' => $checksum,
            'status' => 'processing',
        ]);

        try {
            $spreadsheet = IOFactory::load($resolvedPath);
            $summary = [
                'file' => basename($resolvedPath),
                'checksum' => $checksum,
                'forced_member_id' => $forcedMember?->id,
                'forced_member_name' => $forcedMember?->name,
                'consortium_id' => $consortium?->id,
                'consortium_name' => $consortium?->name,
                'sheets' => [],
            ];
            $sourceRowCount = 0;
            $mappedCount = 0;
            $warningCount = $consortium || $forcedMember ? 0 : 1;
            $touchedPlanIds = collect();

            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $sheetName = $worksheet->getTitle();
                $highestRow = max(1, $worksheet->getHighestDataRow());
                $highestColumn = $worksheet->getHighestDataColumn();
                $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
                $rows = [];

                for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
                    $payload = $this->rowPayload($worksheet, $rowNumber, $highestColumnIndex);
                    $isEmpty = collect($payload['cells'])->every(fn ($cell) => blank($cell['formatted']) && blank($cell['formula']));
                    $row = ThinkTankProcurementImportRow::create([
                        'batch_id' => $batch->id,
                        'sheet_name' => $sheetName,
                        'row_number' => $rowNumber,
                        'row_payload' => $payload,
                        'mapping_status' => $isEmpty ? 'blank_preserved' : 'preserved',
                        'mapping_message' => null,
                    ]);
                    $rows[$rowNumber] = ['model' => $row, 'payload' => $payload, 'empty' => $isEmpty];
                    $sourceRowCount++;
                }

                [$headerRow, $columnMap] = $this->detectHeader($rows);
                $sheetMapped = 0;
                $sheetWarnings = 0;
                if ($headerRow) {
                    $rows[$headerRow]['model']->update([
                        'mapping_status' => 'header',
                        'mapping_message' => 'Detected source header row.',
                    ]);
                }

                if ($headerRow && isset($columnMap['title'])) {
                    $fiscalYear = $this->sheetFiscalYear($rows, $columnMap, $resolvedPath);

                    foreach ($rows as $rowNumber => $rowData) {
                        if ($rowNumber <= $headerRow + 1 || $rowData['empty']) {
                            continue;
                        }
                        $mapped = $this->mappedValues($rowData['payload'], $columnMap);
                        if (blank($mapped['title'] ?? null)) {
                            $rowData['model']->update([
                                'mapping_status' => 'non_item_preserved',
                                'mapping_message' => 'Row has no procurement description and remains available in the raw import record.',
                            ]);
                            continue;
                        }

                        [$sourceReference, $title] = $this->splitReferenceAndTitle((string) $mapped['title']);
                        $member = $forcedMember ?: $this->matchMemberFromText($title, $consortium);
                        if (! $member) {
                            $rowData['model']->update([
                                'mapping_status' => 'member_unmapped',
                                'mapping_message' => 'The Think Tank abbreviation in this item could not be matched. The complete row remains preserved.',
                            ]);
                            $sheetWarnings++;
                            $warningCount++;
                            continue;
                        }

                        $plan = $this->resolvePlan($member, $fiscalYear, $batch);
                        $touchedPlanIds->push($plan->id);
                        $milestones = $this->milestones(
                            $rows[$headerRow]['payload'],
                            $rows[$headerRow + 1]['payload'] ?? ['cells' => []],
                            $rowData['payload']
                        );
                        $plannedDates = collect($milestones)
                            ->where('timing', 'planned')
                            ->pluck('date')
                            ->filter()
                            ->sort()
                            ->values();

                        $sourceIdentity = [
                            'plan_id' => $plan->id,
                            'source_file' => basename($resolvedPath),
                            'source_sheet' => $sheetName,
                            'source_row' => $rowNumber,
                        ];
                        $item = ThinkTankProcurementItem::query()->firstOrNew($sourceIdentity);
                        if (! $item->exists) {
                            $item->item_code = $this->nextImportedItemCode($plan);
                            $item->created_by = null;
                        }
                        $item->fill([
                            ...$sourceIdentity,
                            'source_reference' => $mapped['source_reference'] ?? $sourceReference,
                            'loan_credit_no' => $mapped['loan_credit_no'] ?? null,
                            'component' => $mapped['component'] ?? null,
                            'source_in_process' => $mapped['source_in_process'] ?? null,
                            'source_process_status' => $mapped['source_process_status'] ?? null,
                            'source_activity_status' => $mapped['source_activity_status'] ?? null,
                            'source_document_type' => $mapped['source_document_type'] ?? null,
                            'source_sea_sh_risk' => $mapped['source_sea_sh_risk'] ?? null,
                            'title' => $title,
                            'description' => $title,
                            'procurement_category' => $this->category($mapped['procurement_category'] ?? null),
                            'procurement_method' => $mapped['procurement_method'] ?? $this->methodFromSheet($sheetName),
                            'market_approach' => $mapped['market_approach'] ?? null,
                            'review_type' => $mapped['review_type'] ?? null,
                            'quantity' => $this->number($mapped['quantity'] ?? null),
                            'unit' => $mapped['unit'] ?? null,
                            'estimated_unit_cost' => $this->number($mapped['estimated_unit_cost'] ?? null),
                            'estimated_amount' => $this->number($mapped['estimated_amount'] ?? null) ?? 0,
                            'currency' => $this->currency($mapped['currency'] ?? null),
                            'planned_quarter' => $mapped['planned_quarter'] ?? null,
                            'planned_start_date' => $this->date($mapped['planned_start_date'] ?? null) ?: $plannedDates->first(),
                            'planned_end_date' => $this->date($mapped['planned_end_date'] ?? null) ?: $plannedDates->last(),
                            'status' => ThinkTankProcurementItem::STATUS_DRAFT,
                            'source_payload' => [
                                'header' => $rows[$headerRow]['payload'],
                                'subheader' => $rows[$headerRow + 1]['payload'] ?? null,
                                'row' => $rowData['payload'],
                            ],
                            'planned_milestones' => $milestones,
                        ])->save();

                        $rowData['model']->update([
                            'mapping_status' => 'mapped',
                            'mapping_message' => 'Mapped to procurement item; TOR must be attached before submission.',
                            'plan_id' => $plan->id,
                            'item_id' => $item->id,
                        ]);
                        $sheetMapped++;
                        $mappedCount++;
                    }

                } elseif (! $headerRow || ! isset($columnMap['title'])) {
                    $sheetWarnings++;
                    $warningCount++;
                }

                $summary['sheets'][] = [
                    'name' => $sheetName,
                    'source_rows' => $highestRow,
                    'highest_column' => $highestColumn,
                    'header_row' => $headerRow,
                    'mapped_items' => $sheetMapped,
                    'warnings' => $sheetWarnings,
                    'merged_ranges' => $worksheet->getMergeCells(),
                ];
            }

            ThinkTankProcurementPlan::query()
                ->whereIn('id', $touchedPlanIds->unique())
                ->get()
                ->each(fn (ThinkTankProcurementPlan $plan) => $this->syncPlanBudget($plan));

            $batch->update([
                'status' => $warningCount ? 'completed_with_warnings' : 'completed',
                'sheet_count' => count($summary['sheets']),
                'source_row_count' => $sourceRowCount,
                'mapped_item_count' => $mappedCount,
                'warning_count' => $warningCount,
                'summary' => $summary,
            ]);
            $spreadsheet->disconnectWorksheets();

            return ['status' => $batch->status, 'batch' => $batch->fresh(), 'summary' => $summary];
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'warning_count' => 1,
                'summary' => ['error' => $exception->getMessage()],
            ]);
            throw $exception;
        }
    }

    private function rowPayload($worksheet, int $rowNumber, int $highestColumnIndex): array
    {
        $cells = [];
        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowNumber;
            $cell = $worksheet->getCell($coordinate);
            $raw = $cell->getValue();
            $formula = is_string($raw) && str_starts_with($raw, '=') ? $raw : null;
            try {
                $formatted = $cell->getFormattedValue();
            } catch (Throwable) {
                $formatted = $raw;
            }
            $cells[Coordinate::stringFromColumnIndex($columnIndex)] = [
                'raw' => is_scalar($raw) || $raw === null ? $raw : (string) $raw,
                'formatted' => is_scalar($formatted) || $formatted === null ? $formatted : (string) $formatted,
                'formula' => $formula,
                'data_type' => $cell->getDataType(),
            ];
        }

        return ['cells' => $cells];
    }

    private function detectHeader(array $rows): array
    {
        $best = [null, [], 0];
        foreach (array_slice($rows, 0, 30, true) as $rowNumber => $rowData) {
            $map = [];
            foreach ($rowData['payload']['cells'] as $column => $cell) {
                $label = $this->normalize((string) ($cell['formatted'] ?? ''));
                if ($label === '') {
                    continue;
                }
                foreach (self::HEADER_ALIASES as $canonical => $aliases) {
                    if (in_array($label, $aliases, true)) {
                        $map[$canonical] ??= $column;
                    }
                }
            }
            $score = count($map) + (isset($map['title']) ? 3 : 0) + (isset($map['estimated_amount']) ? 1 : 0);
            if ($score > $best[2]) {
                $best = [$rowNumber, $map, $score];
            }
        }

        return $best[2] >= 4 ? [$best[0], $best[1]] : [null, []];
    }

    private function mappedValues(array $payload, array $columnMap): array
    {
        $mapped = [];
        foreach ($columnMap as $canonical => $column) {
            $mapped[$canonical] = $payload['cells'][$column]['formatted'] ?? null;
        }

        return $mapped;
    }

    private function matchConsortium(string $path): ?Consortium
    {
        $haystack = $this->normalize(pathinfo($path, PATHINFO_FILENAME));
        $known = [
            'bridge' => 'Bridge Africa Consortium',
            'caceps' => 'CACEPS Consortium',
            'raised' => 'RAISED Africa',
        ];
        foreach ($known as $alias => $name) {
            if (str_contains($haystack, $alias)) {
                return Consortium::query()->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
            }
        }

        $matches = Consortium::query()->get()->filter(function (Consortium $consortium) use ($haystack): bool {
            $name = $this->normalize($consortium->name);
            return $name !== '' && (str_contains($haystack, $name) || str_contains($name, $haystack));
        });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function matchMemberFromText(string $text, ?Consortium $consortium): ?ConsortiumThinkTank
    {
        $haystack = ' '.$this->normalize($text).' ';
        $candidates = $consortium
            ? $consortium->loadMissing('members')->members
            : ConsortiumThinkTank::query()->get();
        $known = [
            'acet' => 'african center for economic transformation',
            'afidep' => 'african institute for development policy',
            'nkafu' => 'nkafu policy institute',
            'pcns' => 'policy center for the new south',
            'saiia' => 'south africa institute of international affairs',
            'aphrc' => 'african population and health research center',
            'cip' => 'centro de integridade publica',
            'cped' => 'centre for population and environmental development',
            'eces' => 'egyptian center for economic studies',
            'ipar' => 'initiative prospective agricole et rurale',
            'reprc' => 'resource and environmental policy research centre',
            'pep' => 'partnership for economic policy',
            'erf' => 'economic research forum',
        ];

        $matches = collect();
        foreach ($known as $alias => $nameFragment) {
            if (! str_contains($haystack, ' '.$alias.' ')) {
                continue;
            }
            $member = $candidates->first(fn (ConsortiumThinkTank $candidate): bool =>
                str_contains($this->normalize($candidate->name), $nameFragment)
            );
            if ($member) {
                $matches->push($member);
            }
        }

        if ($matches->unique('id')->count() === 1) {
            return $matches->first();
        }

        $nameMatches = $candidates->filter(function (ConsortiumThinkTank $candidate) use ($haystack): bool {
            $name = $this->normalize($candidate->name);
            return mb_strlen($name) >= 8 && str_contains($haystack, ' '.$name.' ');
        });

        return $nameMatches->count() === 1 ? $nameMatches->first() : null;
    }

    private function splitReferenceAndTitle(string $value): array
    {
        $parts = preg_split('/\s*\/\s*/u', trim($value), 2);
        if (count($parts) === 2 && preg_match('/^[A-Z]{2,}-/i', $parts[0])) {
            return [trim($parts[0]), trim($parts[1])];
        }

        return [null, trim($value)];
    }

    private function methodFromSheet(string $sheetName): string
    {
        $name = Str::upper(trim($sheetName));
        return match (true) {
            str_contains($name, 'RFQ') => 'Request for Quotations (RFQ)',
            str_contains($name, 'RFB') => 'Request for Bids (RFB)',
            str_contains($name, 'QCBS') => 'QCBS / FBS / LCS',
            str_contains($name, 'CDS') => 'Consultant Direct Selection (CDS)',
            str_contains($name, 'INDV') => 'Individual Consultant Selection (INDV)',
            default => Str::headline($sheetName),
        };
    }

    private function milestones(array $header, array $subheader, array $row): array
    {
        $milestones = [];
        $currentHeading = null;
        foreach ($row['cells'] as $column => $cell) {
            $heading = trim((string) ($header['cells'][$column]['formatted'] ?? ''));
            if ($heading !== '') {
                $currentHeading = $heading;
            }
            $timing = $this->normalize((string) ($subheader['cells'][$column]['formatted'] ?? ''));
            if (! in_array($timing, ['planned', 'actual'], true) || blank($cell['formatted'] ?? null)) {
                continue;
            }
            $milestones[] = [
                'column' => $column,
                'milestone' => $currentHeading,
                'timing' => $timing,
                'value' => $cell['formatted'],
                'date' => $this->date($cell['formatted']),
            ];
        }

        return $milestones;
    }

    private function sheetFiscalYear(array $rows, array $columnMap, string $path): string
    {
        if (isset($columnMap['fiscal_year'])) {
            foreach ($rows as $row) {
                $value = $row['payload']['cells'][$columnMap['fiscal_year']]['formatted'] ?? null;
                if (preg_match('/20\d{2}(?:\s*[\/\-]\s*(?:20)?\d{2})?/', (string) $value, $match)) {
                    return preg_replace('/\s+/', '', $match[0]);
                }
            }
        }

        if (preg_match('/(?:FY\s*)?(20\d{2})(?:\s*[\/\-]\s*(?:20)?(\d{2,4}))?/i', basename($path), $match)) {
            return isset($match[2]) && $match[2] !== '' ? $match[1].'/'.substr($match[2], -2) : $match[1];
        }

        return now()->format('Y');
    }

    private function resolvePlan(ConsortiumThinkTank $member, string $fiscalYear, ThinkTankProcurementImportBatch $batch): ThinkTankProcurementPlan
    {
        $plan = ThinkTankProcurementPlan::query()->firstOrCreate([
            'think_tank_member_id' => $member->id,
            'fiscal_year' => $fiscalYear,
        ], [
            'consortium_id' => $member->consortium_id,
            'plan_code' => 'TT-IMP-'.preg_replace('/\D/', '', $fiscalYear).'-'.Str::upper(substr(hash('sha256', $member->id), 0, 8)),
            'title' => 'Imported Annual Procurement Plan FY '.$fiscalYear,
            'estimated_budget' => 0,
            'currency' => $member->consortium?->currency ?: 'USD',
            'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
            'description' => 'Imported from legacy workbook '.$batch->original_name.'. Source rows and the original workbook are preserved.',
        ]);

        return $plan;
    }

    private function nextImportedItemCode(ThinkTankProcurementPlan $plan): string
    {
        $next = $plan->items()->count() + 1;
        do {
            $code = $plan->plan_code.'-'.str_pad((string) $next++, 3, '0', STR_PAD_LEFT);
        } while (ThinkTankProcurementItem::query()->where('item_code', $code)->exists());

        return $code;
    }

    private function syncPlanBudget(ThinkTankProcurementPlan $plan): void
    {
        $plan->forceFill(['estimated_budget' => $plan->items()->sum('estimated_amount')])->save();
    }

    private function category(mixed $value): string
    {
        $normalized = $this->normalize((string) $value);
        return match (true) {
            str_contains($normalized, 'work') => 'works',
            str_contains($normalized, 'non consulting') => 'non_consulting_services',
            str_contains($normalized, 'consult') => 'consulting_services',
            str_contains($normalized, 'training') => 'training',
            str_contains($normalized, 'good') => 'goods',
            default => 'other',
        };
    }

    private function currency(mixed $value): string
    {
        $currency = Str::upper(trim((string) $value));
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'USD';
    }

    private function number(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = preg_replace('/[^0-9.\-()]/', '', (string) $value);
        if ($clean === '' || $clean === '-') {
            return null;
        }
        $negative = str_contains($clean, '(') && str_contains($clean, ')');
        $clean = str_replace(['(', ')'], '', $clean);

        return is_numeric($clean) ? (float) $clean * ($negative ? -1 : 1) : null;
    }

    private function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        if (is_numeric($value) && (float) $value > 10_000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        try {
            return \Carbon\CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', Str::lower($value))));
    }
}
