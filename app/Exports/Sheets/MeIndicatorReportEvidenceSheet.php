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

class MeIndicatorReportEvidenceSheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use MeIndicatorReportSheetFormatting;

    private readonly Collection $rows;

    public function __construct(Collection|array $rows)
    {
        $this->rows = collect($rows)->values();
    }

    public function title(): string
    {
        return 'Evidence Links';
    }

    public function headings(): array
    {
        return [
            'Indicator ID',
            'Indicator Code',
            'Indicator',
            'Project Component Code',
            'Source Result ID',
            'Contributor Organization',
            'Contributor Country',
            'Reporting Period',
            'Evidence Key',
            'Evidence Source',
            'Evidence Title',
            'Validation Status',
            'Verified',
            'Result Approved At',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(fn (array $row): array => [
            $row['indicator_id'] ?? null,
            $row['indicator_code'] ?? null,
            $row['indicator_name'] ?? null,
            $row['project_code'] ?? null,
            $row['source_result_id'] ?? null,
            $row['organization'] ?? null,
            $row['country'] ?? null,
            $row['period'] ?? null,
            $row['evidence_key'] ?? $row['key'] ?? null,
            $row['evidence_source'] ?? $row['source'] ?? null,
            $row['title'] ?? null,
            $row['status'] ?? null,
            array_key_exists('verified', $row) ? ((bool) $row['verified'] ? 'Yes' : 'No') : null,
            $this->dateTime($row['approved_at'] ?? null),
        ])->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '187459']],
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
            'A' => 38, 'B' => 17, 'C' => 54, 'D' => 20, 'E' => 38, 'F' => 40,
            'G' => 24, 'H' => 22, 'I' => 42, 'J' => 28, 'K' => 58, 'L' => 22,
            'M' => 14, 'N' => 24,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('E2');
                $sheet->setAutoFilter('A1:N1');
                $sheet->getStyle("A1:N{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getStyle('A1:N1')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(34);
            },
        ];
    }
}
