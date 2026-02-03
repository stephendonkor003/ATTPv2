<?php

namespace App\Exports;

use App\Models\Program;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CommitmentReportExport implements FromArray, WithStyles, WithColumnWidths, WithEvents
{
    public function __construct(
        protected array $rows,
        protected array $totals,
        protected ?Program $program,
        protected array $yearRange
    ) {}

    public function array(): array
    {
        $data = [];
        $programName = $this->program?->name ?? 'Program';

        $headerRow1 = [
            'Program',
            'Level',
            'Project',
            'Activity',
            'Sub-Activity',
            'PR Reference No',
            'Allocated',
            'Planned Commitment',
            'Variance',
            'Utilization %',
        ];
        foreach ($this->yearRange as $year) {
            $headerRow1[] = (string) $year;
            $headerRow1[] = '';
            $headerRow1[] = '';
        }

        $headerRow2 = array_fill(0, 10, '');
        foreach ($this->yearRange as $year) {
            $headerRow2[] = 'Allocated';
            $headerRow2[] = 'Committed';
            $headerRow2[] = 'Variance';
        }

        $data[] = $headerRow1;
        $data[] = $headerRow2;

        foreach ($this->rows as $projectRow) {
                $row = [
                    $programName,
                    'Project',
                    $projectRow['project']->name ?? '',
                    '',
                    '',
                    $projectRow['references'] ?? '',
                    $projectRow['allocated'],
                    $projectRow['committed'],
                    $projectRow['variance'],
                    $projectRow['utilization'],
                ];

            foreach ($this->yearRange as $year) {
                $row[] = $projectRow['yearly']['allocated'][$year] ?? 0;
                $row[] = $projectRow['yearly']['committed'][$year] ?? 0;
                $row[] = $projectRow['yearly']['variance'][$year] ?? 0;
            }
            $data[] = $row;

            foreach ($projectRow['activities'] as $activityRow) {
                $row = [
                    $programName,
                    'Activity',
                    $projectRow['project']->name ?? '',
                    $activityRow['activity']->name ?? '',
                    '',
                    $activityRow['references'] ?? '',
                    $activityRow['allocated'],
                    $activityRow['committed'],
                    $activityRow['variance'],
                    $activityRow['utilization'],
                ];

                foreach ($this->yearRange as $year) {
                    $row[] = $activityRow['yearly']['allocated'][$year] ?? 0;
                    $row[] = $activityRow['yearly']['committed'][$year] ?? 0;
                    $row[] = $activityRow['yearly']['variance'][$year] ?? 0;
                }
                $data[] = $row;

                foreach ($activityRow['subActivities'] as $subRow) {
                    $row = [
                        $programName,
                        'Sub-Activity',
                        $projectRow['project']->name ?? '',
                        $activityRow['activity']->name ?? '',
                        $subRow['subActivity']->name ?? '',
                        $subRow['references'] ?? '—',
                        $subRow['allocated'],
                        $subRow['committed'],
                        $subRow['variance'],
                        $subRow['utilization'],
                    ];

                    foreach ($this->yearRange as $year) {
                        $row[] = $subRow['yearly']['allocated'][$year] ?? 0;
                        $row[] = $subRow['yearly']['committed'][$year] ?? 0;
                        $row[] = $subRow['yearly']['variance'][$year] ?? 0;
                    }
                    $data[] = $row;
                }
            }
        }

        $totalRow = [
            $programName,
            'TOTAL',
            '',
            '',
            '',
            '',
            $this->totals['allocated'] ?? 0,
            $this->totals['committed'] ?? 0,
            $this->totals['variance'] ?? 0,
            $this->totals['utilization'] ?? 0,
        ];

        foreach ($this->yearRange as $year) {
            $totalRow[] = '';
            $totalRow[] = '';
            $totalRow[] = '';
        }
        $data[] = $totalRow;

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'E2E8F0']]],
            2 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'F8FAFC']]],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 20,
            'B' => 12,
            'C' => 22,
            'D' => 22,
            'E' => 24,
            'F' => 28,
            'G' => 16,
            'H' => 20,
            'I' => 16,
            'J' => 14,
        ];

        $start = 11;
        $colIndex = $start;
        foreach ($this->yearRange as $year) {
            $widths[Coordinate::stringFromColumnIndex($colIndex++)] = 14;
            $widths[Coordinate::stringFromColumnIndex($colIndex++)] = 14;
            $widths[Coordinate::stringFromColumnIndex($colIndex++)] = 14;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Merge base headers vertically (rows 1-2)
                for ($col = 1; $col <= 10; $col++) {
                    $letter = Coordinate::stringFromColumnIndex($col);
                    $sheet->mergeCells("{$letter}1:{$letter}2");
                }

                // Merge year headers across 3 columns each
                $colIndex = 11;
                foreach ($this->yearRange as $year) {
                    $start = Coordinate::stringFromColumnIndex($colIndex);
                    $end = Coordinate::stringFromColumnIndex($colIndex + 2);
                    $sheet->mergeCells("{$start}1:{$end}1");
                    $colIndex += 3;
                }

                $sheet->freezePane('A3');
                $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex($colIndex - 1) . '2')
                    ->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');
            },
        ];
    }
}
