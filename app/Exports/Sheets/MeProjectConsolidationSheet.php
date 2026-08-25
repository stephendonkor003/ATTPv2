<?php

namespace App\Exports\Sheets;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MeProjectConsolidationSheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly Collection $rows) {}

    public function title(): string
    {
        return 'Project Consolidation';
    }

    public function headings(): array
    {
        return [
            'Portfolio',
            'Program',
            'Project / Results Area Code',
            'Project / Results Area',
            'Status Code',
            'Status',
            'Indicators',
            'Reported Indicators',
            'Rated Indicators',
            'Not Rated Indicators',
            'Average Achievement %',
            'On Track / Achieved',
            'Attention Required',
            'Reporting Completeness %',
            'Approved Contributions',
            'Contributor Organizations',
            'Contributor Organization Count',
            'Contributor Countries',
            'Source Periods',
            'Source Data Sources',
            'Source Result IDs',
            'Evidence Links',
            'Verified Evidence Links',
            'Evidence Verification %',
            'Achievement Records',
            'Participant / Beneficiary Instances',
            'Female Instances',
            'Male Instances',
            'Latest Approval',
            'Indicator Codes',
            'Indicator Performance Detail',
            'Contribution Provenance',
            'Performance Mix',
            'Calculation Note',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(function (array $row): array {
            $indicatorRows = collect($row['indicator_rows'] ?? []);
            $contributions = $indicatorRows->flatMap(function (array $indicatorRow): Collection {
                $indicatorCode = $indicatorRow['indicator']?->indicator_code;

                return collect($indicatorRow['source_contributions'] ?? [])->map(
                    fn (array $contribution): array => $contribution + ['indicator_code' => $indicatorCode]
                );
            })->values();
            $countries = $contributions->pluck('country')->filter()->unique()->sort()->values();

            return [
                $row['portfolio'] ?? null,
                $row['program'] ?? null,
                $row['code'] ?? null,
                $row['name'] ?? null,
                data_get($row, 'status.code'),
                data_get($row, 'status.label'),
                (int) ($row['indicator_count'] ?? 0),
                (int) ($row['reported_indicator_count'] ?? 0),
                (int) ($row['rated_indicator_count'] ?? 0),
                (int) ($row['not_rated_count'] ?? 0),
                $this->cellValue($row['average_achievement'] ?? null),
                (int) ($row['on_track_count'] ?? 0),
                (int) ($row['attention_count'] ?? 0),
                $this->cellValue($row['reporting_completeness'] ?? 0),
                (int) ($row['approved_contribution_count'] ?? 0),
                $this->listValue($row['organizations'] ?? []),
                (int) ($row['organization_count'] ?? 0),
                $this->listValue($countries),
                $this->listValue($contributions->pluck('period')),
                $this->listValue($contributions->pluck('data_source')),
                $this->listValue($contributions->pluck('id')),
                (int) ($row['evidence_count'] ?? 0),
                (int) ($row['verified_evidence_count'] ?? 0),
                $this->cellValue($row['evidence_verification_rate'] ?? null),
                (int) ($row['achievement_count'] ?? 0),
                (int) ($row['beneficiary_count'] ?? 0),
                (int) ($row['female_beneficiaries'] ?? 0),
                (int) ($row['male_beneficiaries'] ?? 0),
                $this->dateTime($row['latest_approved_at'] ?? null),
                $this->listValue($indicatorRows->map(fn (array $indicatorRow): mixed => $indicatorRow['indicator']?->indicator_code)),
                $indicatorRows->map(fn (array $indicatorRow): string => $this->indicatorDetail($indicatorRow))->join(' || '),
                $contributions->map(fn (array $contribution): string => $this->contributionProvenance($contribution))->join(' || '),
                $this->performanceMix($row['performance_mix'] ?? []),
                $row['calculation_note'] ?? null,
            ];
        })->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '073F30']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 26, 'B' => 28, 'C' => 18, 'D' => 42, 'E' => 16, 'F' => 22,
            'G' => 13, 'H' => 18, 'I' => 16, 'J' => 18, 'K' => 20, 'L' => 18,
            'M' => 18, 'N' => 22, 'O' => 20, 'P' => 48, 'Q' => 20, 'R' => 30,
            'S' => 28, 'T' => 30, 'U' => 42, 'V' => 15, 'W' => 20, 'X' => 22,
            'Y' => 18, 'Z' => 16, 'AA' => 18, 'AB' => 18, 'AC' => 22, 'AD' => 34,
            'AE' => 90, 'AF' => 95, 'AG' => 40, 'AH' => 62,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('E2');
                $sheet->setAutoFilter('A1:AH1');
                $sheet->getStyle("A1:AH{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getStyle('A1:AH1')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(34);
            },
        ];
    }

    private function indicatorDetail(array $row): string
    {
        $indicator = $row['indicator'] ?? null;
        $target = filled($row['target_text'] ?? null)
            ? (string) $row['target_text']
            : $this->displayValue($row['target_value'] ?? null);
        $unit = filled($row['unit_label'] ?? null) ? ' '.$row['unit_label'] : '';

        return implode(' | ', [
            trim((string) ($indicator?->indicator_code ?? '')).' · '.trim((string) ($indicator?->name ?? 'Indicator unavailable')),
            'Actual: '.$this->displayValue($row['actual'] ?? null).$unit,
            'Target: '.$target,
            'Achievement: '.(is_numeric($row['achievement_percent'] ?? null)
                ? number_format((float) $row['achievement_percent'], 1).'%'
                : 'Not rated'),
            'Status: '.(data_get($row, 'classification.label') ?: 'Not rated'),
            'Completeness: '.number_format((float) ($row['reporting_completeness'] ?? 0), 1).'%',
            'Approved contributions: '.(int) ($row['result_count'] ?? 0),
        ]);
    }

    private function contributionProvenance(array $contribution): string
    {
        $organization = trim((string) ($contribution['organization'] ?? '')) ?: 'Unknown contributor';
        $country = filled($contribution['country'] ?? null) ? ' ('.$contribution['country'].')' : '';
        $parts = [
            (filled($contribution['indicator_code'] ?? null) ? $contribution['indicator_code'].' · ' : '').$organization.$country,
            'Period: '.(filled($contribution['period'] ?? null) ? $contribution['period'] : 'Not specified'),
            'Actual: '.$this->displayValue($contribution['actual'] ?? null),
        ];
        if (($contribution['rollup_numerator'] ?? null) !== null || ($contribution['rollup_denominator'] ?? null) !== null) {
            $parts[] = 'Weight: '.$this->displayValue($contribution['rollup_numerator'] ?? null)
                .'/'.$this->displayValue($contribution['rollup_denominator'] ?? null);
        }
        $parts[] = 'Source: '.(filled($contribution['data_source'] ?? null) ? $contribution['data_source'] : 'Not specified');
        $parts[] = 'Evidence: '.(int) ($contribution['evidence_count'] ?? 0);
        $parts[] = 'Achievements: '.(int) ($contribution['achievement_count'] ?? 0);
        $parts[] = 'Approved: '.$this->dateTime($contribution['approved_at'] ?? null);
        if (filled($contribution['id'] ?? null)) {
            $parts[] = 'Result ID: '.$contribution['id'];
        }

        return implode(' | ', $parts);
    }

    private function performanceMix(mixed $mix): string
    {
        return collect($mix instanceof Collection ? $mix->all() : (array) $mix)
            ->map(fn (mixed $count, string|int $status): string => str((string) $status)
                ->replace('_', ' ')
                ->headline()
                ->append(': '.(int) $count)
                ->toString())
            ->values()
            ->join(', ');
    }

    private function cellValue(mixed $value): string|int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return (string) $value;
    }

    private function listValue(mixed $values): string
    {
        return collect($values instanceof Collection ? $values->all() : (array) $values)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->join(', ');
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not available';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        }

        return (string) $value;
    }

    private function dateTime(mixed $value): string
    {
        if (! $value) {
            return 'Not available';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s T');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s T');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
