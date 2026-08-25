<?php

namespace App\Exports\Sheets;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MeIndicatorConsolidationSheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly Collection $rows) {}

    public function title(): string
    {
        return 'Indicator Consolidation';
    }

    public function headings(): array
    {
        return [
            'Portfolio',
            'Program',
            'Project / Results Area Code',
            'Project / Results Area',
            'Results Level',
            'Indicator Code',
            'Indicator',
            'Value Type',
            'Unit',
            'Reporting Source',
            'Time Aggregation',
            'Organization Roll-up',
            'Cumulative',
            'Baseline',
            'Target Value',
            'Target Text',
            'Period Actual',
            'Cumulative Actual',
            'Consolidated Actual',
            'Variance',
            'Achievement %',
            'Performance Code',
            'Performance Status',
            'Trend',
            'Trend Change',
            'Approved Contributions',
            'Contributor Organizations',
            'Organizations Reported',
            'Organizations Expected',
            'Reporting Completeness %',
            'Contributor Countries',
            'Evidence Links',
            'Verified Evidence Links',
            'Achievement Records',
            'Participant / Beneficiary Instances',
            'Female Instances',
            'Male Instances',
            'Latest Approval',
            'Source Result IDs',
            'Source Periods',
            'Source Data Sources',
            'Contribution Provenance',
            'Calculation Note',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(function (array $row): array {
            $indicator = $row['indicator'] ?? null;
            $project = $indicator?->projectComponent;
            $contributions = collect($row['source_contributions'] ?? []);
            $countries = collect($row['countries'] ?? [])
                ->merge($contributions->pluck('country'))
                ->filter()
                ->unique()
                ->sort()
                ->values();

            return [
                $project?->program?->sector?->name,
                $project?->program?->name,
                $project?->project_id ?: 'PDO',
                $project?->name ?: 'Project Development Objective / Cross-project results',
                $this->resultsLevel($indicator?->results_level),
                $indicator?->indicator_code,
                $indicator?->name,
                Str::headline((string) ($indicator?->value_type ?: 'Not configured')),
                $row['unit_label'] ?? $indicator?->unit?->symbol ?? $indicator?->unit?->name,
                Str::headline((string) ($indicator?->reporting_source ?: 'Not configured')),
                $row['time_aggregation_label'] ?? Str::headline((string) ($indicator?->aggregation_method ?: 'Not configured')),
                $row['organization_rollup_label'] ?? Str::headline((string) ($indicator?->organization_rollup_method ?: 'Not configured')),
                (bool) ($indicator?->is_cumulative ?? false) ? 'Yes' : 'No',
                $this->cellValue($row['baseline'] ?? null),
                $this->cellValue($row['target_value'] ?? null),
                $row['target_text'] ?? null,
                $this->cellValue($row['period_actual'] ?? null),
                $this->cellValue($row['cumulative_actual'] ?? null),
                $this->cellValue($row['actual'] ?? null),
                $this->cellValue($row['variance_value'] ?? $row['variance'] ?? null),
                $this->cellValue($row['achievement_percent'] ?? null),
                data_get($row, 'classification.code'),
                data_get($row, 'classification.label'),
                data_get($row, 'trend.label'),
                $this->cellValue(data_get($row, 'trend.change')),
                (int) ($row['result_count'] ?? 0),
                $this->listValue($row['reporting_organizations'] ?? []),
                (int) ($row['reported_organizations'] ?? 0),
                (int) ($row['expected_organizations'] ?? 0),
                $this->cellValue($row['reporting_completeness'] ?? 0),
                $this->listValue($countries),
                (int) ($row['evidence_count'] ?? 0),
                (int) ($row['verified_evidence_count'] ?? 0),
                (int) ($row['achievement_count'] ?? 0),
                (int) ($row['beneficiary_count'] ?? 0),
                (int) ($row['female_beneficiaries'] ?? 0),
                (int) ($row['male_beneficiaries'] ?? 0),
                $this->dateTime($row['latest_approved_at'] ?? null),
                $this->listValue($contributions->pluck('id')),
                $this->listValue($contributions->pluck('period')),
                $this->listValue($contributions->pluck('data_source')),
                $contributions->map(fn (array $contribution): string => $this->contributionProvenance($contribution))->join(' || '),
                $row['calculation_note'] ?? null,
            ];
        })->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '075C7A']],
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
            'A' => 26, 'B' => 28, 'C' => 18, 'D' => 38, 'E' => 18, 'F' => 16,
            'G' => 55, 'H' => 16, 'I' => 14, 'J' => 20, 'K' => 28, 'L' => 32,
            'M' => 13, 'N' => 14, 'O' => 14, 'P' => 26, 'Q' => 16, 'R' => 18,
            'S' => 18, 'T' => 14, 'U' => 15, 'V' => 18, 'W' => 24, 'X' => 18,
            'Y' => 14, 'Z' => 18, 'AA' => 42, 'AB' => 16, 'AC' => 16, 'AD' => 18,
            'AE' => 28, 'AF' => 14, 'AG' => 18, 'AH' => 18, 'AI' => 16, 'AJ' => 18,
            'AK' => 18, 'AL' => 22, 'AM' => 38, 'AN' => 28, 'AO' => 28, 'AP' => 90,
            'AQ' => 60,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('G2');
                $sheet->setAutoFilter('A1:AQ1');
                $sheet->getStyle("A1:AQ{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getStyle('A1:AQ1')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(34);
            },
        ];
    }

    private function resultsLevel(?string $level): string
    {
        return match ($level) {
            'pdo' => 'PDO',
            'intermediate_results' => 'Intermediate Results',
            default => 'Not classified',
        };
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

    private function contributionProvenance(array $contribution): string
    {
        $organization = trim((string) ($contribution['organization'] ?? '')) ?: 'Unknown contributor';
        $country = filled($contribution['country'] ?? null) ? ' ('.$contribution['country'].')' : '';
        $parts = [
            $organization.$country,
            'Period: '.(filled($contribution['period'] ?? null) ? $contribution['period'] : 'Not specified'),
            'Actual: '.($this->displayValue($contribution['actual'] ?? null)),
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
