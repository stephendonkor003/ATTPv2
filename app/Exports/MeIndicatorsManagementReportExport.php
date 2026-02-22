<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MeIndicatorsManagementReportExport implements FromArray, WithStyles, WithColumnWidths, WithEvents
{
    public function __construct(
        protected array $rows,
        protected string $searchTerm = ''
    ) {}

    public function array(): array
    {
        $data = [];

        $data[] = [
            'Program',
            'Project',
            'Activity',
            'Sub-Activity',
            'Indicator',
            'Owner Type',
            'Level',
            'Frequency',
            'Baseline Type',
            'Baseline Period',
            'Baseline Value',
            'Responsible Party/Person',
            'Methodology',
            'Primary Source Type',
            'Primary Source Value',
            'Definition',
            'Target',
            'Actual',
            'Achievement',
            'Status',
            'Notes',
        ];

        foreach ($this->rows as $row) {
            $data[] = [
                $row['program'] ?? '—',
                $row['project'] ?? '—',
                $row['activity'] ?? '—',
                $row['sub_activity'] ?? '—',
                $row['indicator_name'] ?? '—',
                $row['owner_type'] ?? '—',
                $row['indicator_level'] ?? '—',
                $row['frequency'] ?? '—',
                $row['baseline_type'] ?? '—',
                $row['baseline_period'] ?? '—',
                $row['baseline_value'] ?? '—',
                $row['responsible'] ?? '—',
                $row['methodology'] ?? '—',
                $row['primary_source_type'] ?? '—',
                $row['primary_source_value'] ?? '—',
                $row['definition'] ?? '—',
                $row['target'] ?? '—',
                $row['actual'] ?? '—',
                $row['achievement'] ?? '—',
                $row['status'] ?? '—',
                $row['notes'] ?? '—',
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'E2E8F0']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 28,
            'C' => 26,
            'D' => 26,
            'E' => 32,
            'F' => 16,
            'G' => 16,
            'H' => 18,
            'I' => 16,
            'J' => 18,
            'K' => 18,
            'L' => 30,
            'M' => 24,
            'N' => 20,
            'O' => 30,
            'P' => 30,
            'Q' => 14,
            'R' => 14,
            'S' => 14,
            'T' => 14,
            'U' => 30,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$highestColumn}1");

                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                    ->getAlignment()
                    ->setVertical('top')
                    ->setWrapText(true);

                $sheet->getStyle("A1:{$highestColumn}1")
                    ->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');
            },
        ];
    }
}
