<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConsolidatedMeReportExport implements FromArray, WithHeadings, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    public function __construct(private readonly Collection $rows, private readonly array $filters)
    {
    }

    public function headings(): array
    {
        return [
            'Reporting Year', 'Reporting Frequency', 'Reporting Period', 'Indicator Code', 'Indicator',
            'Result Type', 'Organization Roll-up', 'Consolidated Numeric Result', 'Qualitative Results',
            'Common Period Target', 'Organizations Reporting', 'Reporting Organizations', 'Reported Values',
            'Duplicate Source Results Suppressed', 'Achievement Records', 'Beneficiaries', 'Geographic Scopes',
            'Countries', 'Regional Economic Communities', 'Priority Themes', 'Implementing Institution Types',
            'Implementing Institutions', 'Stakeholder Categories', 'Female', 'Male', 'Youth Below 35', 'Adults 35+',
            'Gender Not Disaggregated', 'Age Not Disaggregated',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(function (array $row): array {
            return [
                $this->filters['year'],
                $this->filters['period_type'],
                $this->filters['period_label'],
                $row['indicator']?->indicator_code,
                $row['indicator']?->name,
                str($row['indicator']?->value_type ?: 'number')->headline()->toString(),
                $row['rollup_label'],
                $row['value'],
                $row['qualitative_values']->map(
                    fn (array $value): string => $value['organization'].': '.$value['value']
                )->join('; '),
                $row['target'],
                $row['organization_count'],
                $row['organizations']->join(', '),
                $row['reported_value_count'],
                $row['duplicate_result_count'],
                $row['achievement_count'],
                $row['beneficiary_count'],
                $row['geographic_scopes']->keys()->join(', '),
                $row['countries']->keys()->join(', '),
                $row['recs']->keys()->join(', '),
                $row['themes']->keys()->join(', '),
                $row['institution_types']->keys()->join(', '),
                $row['institutions']->keys()->join(', '),
                $row['stakeholders']->map(fn ($count, $category) => $category.': '.$count)->values()->join('; '),
                $row['gender']->get('female', 0),
                $row['gender']->get('male', 0),
                $row['age_groups']->get('youth_below_35', 0),
                $row['age_groups']->get('adult_35_plus', 0),
                $row['gender']->get('not_disaggregated', 0),
                $row['age_groups']->get('not_disaggregated', 0),
            ];
        })->all();
    }

    public function title(): string
    {
        return 'Approved Consolidation';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 18, 'C' => 20, 'D' => 16, 'E' => 46, 'F' => 18,
            'G' => 30, 'H' => 20, 'I' => 55, 'J' => 18, 'K' => 18, 'L' => 40,
            'M' => 16, 'N' => 18, 'O' => 18, 'P' => 16, 'Q' => 24, 'R' => 25,
            'S' => 24, 'T' => 34, 'U' => 32, 'V' => 36, 'W' => 36,
            'X' => 13, 'Y' => 13, 'Z' => 17, 'AA' => 17, 'AB' => 22, 'AC' => 22,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '075C7A']],
                'alignment' => ['vertical' => 'center', 'wrapText' => true],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$highestColumn}1");
                $sheet->getRowDimension(1)->setRowHeight(34);
                if ($highestRow > 1) {
                    $sheet->getStyle("A2:{$highestColumn}{$highestRow}")
                        ->getAlignment()
                        ->setVertical('top')
                        ->setWrapText(true);
                    $sheet->getStyle("A2:{$highestColumn}{$highestRow}")
                        ->getBorders()
                        ->getBottom()
                        ->setBorderStyle('hair');
                }
            },
        ];
    }
}
