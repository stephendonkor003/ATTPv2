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

class InterimFinancialReportExport implements FromArray, WithStyles, WithColumnWidths, WithEvents
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
            'Global Commitments',
            'Planned Commitments',
            'Cumulative Disbursed',
            'Variance',
            'Commitment Rate %',
            'Disbursement Rate %',
        ];
        foreach ($this->yearRange as $year) {
            $headerRow1[] = (string) $year;
            $headerRow1[] = '';
            $headerRow1[] = '';
        }

        $headerRow2 = array_fill(0, 12, '');
        foreach ($this->yearRange as $year) {
            $headerRow2[] = 'Global Commitments';
            $headerRow2[] = 'Cumulative Disbursed';
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
                $projectRow['global_commitment'] ?? $projectRow['committed'],
                $projectRow['planned_commitment'] ?? 0,
                $projectRow['disbursed'],
                $projectRow['variance'],
                $projectRow['commitment_rate'] ?? 0,
                $projectRow['disbursement_rate'] ?? $projectRow['utilization'],
            ];

            foreach ($this->yearRange as $year) {
                $row[] = $projectRow['yearly']['committed'][$year] ?? 0;
                $row[] = $projectRow['yearly']['disbursed'][$year] ?? 0;
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
                    $activityRow['global_commitment'] ?? $activityRow['committed'],
                    $activityRow['planned_commitment'] ?? 0,
                    $activityRow['disbursed'],
                    $activityRow['variance'],
                    $activityRow['commitment_rate'] ?? 0,
                    $activityRow['disbursement_rate'] ?? $activityRow['utilization'],
                ];

                foreach ($this->yearRange as $year) {
                    $row[] = $activityRow['yearly']['committed'][$year] ?? 0;
                    $row[] = $activityRow['yearly']['disbursed'][$year] ?? 0;
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
                        $subRow['references'] ?? '-',
                        $subRow['global_commitment'] ?? $subRow['committed'],
                        $subRow['planned_commitment'] ?? 0,
                        $subRow['disbursed'],
                        $subRow['variance'],
                        $subRow['commitment_rate'] ?? 0,
                        $subRow['disbursement_rate'] ?? $subRow['utilization'],
                    ];

                    foreach ($this->yearRange as $year) {
                        $row[] = $subRow['yearly']['committed'][$year] ?? 0;
                        $row[] = $subRow['yearly']['disbursed'][$year] ?? 0;
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
            $this->totals['global_commitment'] ?? $this->totals['committed'] ?? 0,
            $this->totals['planned_commitment'] ?? 0,
            $this->totals['disbursed'] ?? 0,
            $this->totals['variance'] ?? 0,
            $this->totals['commitment_rate'] ?? 0,
            $this->totals['disbursement_rate'] ?? $this->totals['utilization'] ?? 0,
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
            'H' => 16,
            'I' => 16,
            'J' => 16,
            'K' => 18,
            'L' => 18,
        ];

        $start = 13;
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

                for ($col = 1; $col <= 12; $col++) {
                    $letter = Coordinate::stringFromColumnIndex($col);
                    $sheet->mergeCells("{$letter}1:{$letter}2");
                }

                $colIndex = 13;
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
