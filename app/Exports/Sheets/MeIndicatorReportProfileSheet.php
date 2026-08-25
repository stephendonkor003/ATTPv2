<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MeIndicatorReportProfileSheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use MeIndicatorReportSheetFormatting;

    private readonly Collection $rows;

    public function __construct(Collection|array $rows)
    {
        $this->rows = collect($rows)->values();
    }

    public function title(): string
    {
        return 'Indicator Profile';
    }

    public function headings(): array
    {
        return [
            'Portfolio',
            'Program',
            'Project Component ID',
            'Project Component Code',
            'Project Component',
            'Results Level',
            'Indicator ID',
            'Indicator Code',
            'Indicator',
            'Definition',
            'IRS Version',
            'IRS Definition',
            'IRS Calculation Method',
            'IRS Data Sources',
            'IRS Means of Verification',
            'IRS Disaggregation',
            'Value Type',
            'Unit',
            'Reporting Source',
            'Reporting Frequency',
            'Time Aggregation',
            'Organization Roll-up',
            'Cumulative',
            'Baseline',
            'Target Value',
            'Target Text',
            'Target Scope',
            'Target Revision',
            'Target Effective From',
            'Period Actual',
            'Cumulative Actual',
            'Approved Consolidated Actual',
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
            'Evidence Links',
            'Verified Evidence Links',
            'Achievement Records',
            'Participant / Beneficiary Instances',
            'Female Instances',
            'Male Instances',
            'Latest Approval',
            'Calculation Note',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(function (array $row): array {
            $indicator = $row['indicator'] ?? null;
            $project = $indicator?->projectComponent;
            $referenceSheet = $indicator?->approvedReferenceSheet;
            $target = $row['target'] ?? null;

            return [
                $project?->program?->sector?->name,
                $project?->program?->name,
                $project?->id,
                $project?->project_id ?: 'PDO',
                $project?->name ?: 'Project Development Objective / Cross-project results',
                $this->resultsLevel($indicator?->results_level),
                $indicator?->id,
                $indicator?->indicator_code,
                $indicator?->name,
                $referenceSheet?->definition ?: $indicator?->definitions,
                $referenceSheet?->version,
                $referenceSheet?->definition,
                $referenceSheet?->calculation_method,
                $referenceSheet?->data_sources,
                $referenceSheet?->means_of_verification,
                $this->cellValue($referenceSheet?->disaggregation),
                Str::headline((string) ($indicator?->value_type ?: 'Not configured')),
                $row['unit_label'] ?? $indicator?->unit?->symbol ?? $indicator?->unit?->name,
                Str::headline((string) ($indicator?->reporting_source ?: 'Not configured')),
                $indicator?->frequency?->name ?: $referenceSheet?->reporting_frequency,
                $row['time_aggregation_label'] ?? Str::headline((string) ($indicator?->aggregation_method ?: 'Not configured')),
                $row['organization_rollup_label'] ?? Str::headline((string) ($indicator?->organization_rollup_method ?: 'Not configured')),
                (bool) ($indicator?->is_cumulative ?? false) ? 'Yes' : 'No',
                $this->cellValue($row['baseline'] ?? null),
                $this->cellValue($row['target_value'] ?? null),
                $row['target_text'] ?? null,
                $target?->target_scope,
                $target?->revision,
                $target?->effective_from?->format('Y-m-d'),
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
                (int) ($row['evidence_count'] ?? 0),
                (int) ($row['verified_evidence_count'] ?? 0),
                (int) ($row['achievement_count'] ?? 0),
                (int) ($row['beneficiary_count'] ?? 0),
                (int) ($row['female_beneficiaries'] ?? 0),
                (int) ($row['male_beneficiaries'] ?? 0),
                $this->dateTime($row['latest_approved_at'] ?? null),
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
        $widths = [];
        foreach (range(1, count($this->headings())) as $index) {
            $widths[Coordinate::stringFromColumnIndex($index)] = 17;
        }

        foreach ([1 => 26, 2 => 28, 5 => 38, 9 => 52, 10 => 64, 12 => 64, 13 => 54,
            14 => 42, 15 => 48, 16 => 42, 21 => 28, 22 => 32, 26 => 28, 36 => 24,
            37 => 20, 40 => 45, 51 => 62] as $index => $width) {
            $widths[Coordinate::stringFromColumnIndex($index)] = $width;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('J2');
                $sheet->setAutoFilter("A1:{$highestColumn}1");
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getStyle("A1:{$highestColumn}1")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(36);
            },
        ];
    }
}
