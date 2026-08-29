<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EvaluationReportSheet implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly array $rows
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '0B2138'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $columnCount = max(1, ...array_map('count', $this->rows));
        $widths = [];

        for ($index = 1; $index <= $columnCount; $index++) {
            $widths[Coordinate::stringFromColumnIndex($index)] = match (true) {
                $this->title === 'Overview' && $index === 1 => 34,
                $this->title === 'Overview' && $index === 2 => 78,
                $index === 1 => 20,
                $index <= 3 => 28,
                $index <= 8 => 20,
                default => 24,
            };
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = max(1, $sheet->getHighestRow());

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageMargins()
                    ->setTop(0.35)
                    ->setBottom(0.35)
                    ->setLeft(0.25)
                    ->setRight(0.25);
                $sheet->getPageSetup()->setHorizontalCentered(true);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
                $sheet->getSheetView()->setZoomScale(90);
                $sheet->getDefaultRowDimension()->setRowHeight(18);
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getStyle("A1:{$lastColumn}1")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
