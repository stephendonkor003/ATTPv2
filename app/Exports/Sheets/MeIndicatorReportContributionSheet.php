<?php

namespace App\Exports\Sheets;

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

class MeIndicatorReportContributionSheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use MeIndicatorReportSheetFormatting;

    private readonly Collection $rows;

    public function __construct(Collection|array $rows)
    {
        $this->rows = collect($rows)->values();
    }

    public function title(): string
    {
        return 'Approved Contributions';
    }

    public function headings(): array
    {
        return [
            'Indicator ID',
            'Indicator Code',
            'Indicator',
            'Results Level',
            'Portfolio',
            'Program',
            'Project Component ID',
            'Project Component Code',
            'Project Component',
            'Value Type',
            'Unit',
            'Baseline',
            'Approved Target',
            'Target Text',
            'Consolidated Actual',
            'Achievement %',
            'Performance Status',
            'Source Result ID',
            'Contributor Organization',
            'Contributor Country',
            'Reporting Period',
            'Approved Actual',
            'Roll-up Numerator',
            'Roll-up Denominator',
            'Data Source',
            'Evidence Links',
            'Verified Evidence Links',
            'Achievement Records',
            'Approved At',
            'Calculation Note',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(fn (array $row): array => [
            $row['indicator_id'] ?? null,
            $row['indicator_code'] ?? null,
            $row['indicator_name'] ?? null,
            $this->resultsLevel($row['results_level'] ?? null),
            $row['portfolio'] ?? null,
            $row['program'] ?? null,
            $row['project_component_id'] ?? null,
            $row['project_component_code'] ?? null,
            $row['project_component_name'] ?? null,
            filled($row['value_type'] ?? null) ? str($row['value_type'])->headline()->toString() : null,
            $row['unit'] ?? null,
            $this->cellValue($row['baseline'] ?? null),
            $this->cellValue($row['target_value'] ?? null),
            $row['target_text'] ?? null,
            $this->cellValue($row['consolidated_actual'] ?? null),
            $this->cellValue($row['achievement_percent'] ?? null),
            $row['performance_status'] ?? null,
            $row['source_result_id'] ?? $row['id'] ?? null,
            $row['organization'] ?? null,
            $row['country'] ?? null,
            $row['period'] ?? null,
            $this->cellValue($row['actual'] ?? null),
            $this->cellValue($row['rollup_numerator'] ?? null),
            $this->cellValue($row['rollup_denominator'] ?? null),
            $row['data_source'] ?? null,
            (int) ($row['evidence_count'] ?? 0),
            (int) ($row['verified_evidence_count'] ?? 0),
            (int) ($row['achievement_count'] ?? 0),
            $this->dateTime($row['approved_at'] ?? null),
            $row['calculation_note'] ?? null,
        ])->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '246A73']],
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
            'A' => 38, 'B' => 17, 'C' => 54, 'D' => 20, 'E' => 27, 'F' => 30,
            'G' => 38, 'H' => 20, 'I' => 40, 'J' => 18, 'K' => 14, 'L' => 16,
            'M' => 18, 'N' => 28, 'O' => 20, 'P' => 18, 'Q' => 24, 'R' => 38,
            'S' => 40, 'T' => 24, 'U' => 22, 'V' => 22, 'W' => 20, 'X' => 20,
            'Y' => 32, 'Z' => 16, 'AA' => 22, 'AB' => 20, 'AC' => 24, 'AD' => 66,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('R2');
                $sheet->setAutoFilter('A1:AD1');
                $sheet->getStyle("A1:AD{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getStyle('A1:AD1')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(34);
            },
        ];
    }
}
