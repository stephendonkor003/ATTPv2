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
            'Portfolio',
            'Program',
            'Project',
            'Activity',
            'Sub-Activity',
            'Indicator',
            'Owner Type',
            'Project Component',
            'Results Level',
            'Disaggregation',
            'Frequency',
            'Baseline Type',
            'Baseline Period',
            'Baseline Value',
            'Responsible Party/Person',
            'Data Collection Method/Data Source',
            'Means of Verification',
            'Definition',
            'Target',
            'Actual',
            'Achievement',
            'Status',
            'Notes',
        ];

        foreach ($this->rows as $row) {
            $data[] = [
                $row['portfolio'] ?? '—',
                $row['program'] ?? '—',
                $row['project'] ?? '—',
                $row['activity'] ?? '—',
                $row['sub_activity'] ?? '—',
                $row['indicator_name'] ?? '—',
                $row['owner_type'] ?? '—',
                $row['project_component'] ?? '—',
                $row['indicator_level'] ?? '—',
                $row['disaggregation'] ?? '—',
                $row['frequency'] ?? '—',
                $row['baseline_type'] ?? '—',
                $row['baseline_period'] ?? '—',
                $row['baseline_value'] ?? '—',
                $row['responsible'] ?? '—',
                $row['data_collection_method'] ?? '—',
                $row['means_of_verification'] ?? '—',
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
            'C' => 28,
            'D' => 26,
            'E' => 26,
            'F' => 32,
            'G' => 16,
            'H' => 28,
            'I' => 20,
            'J' => 28,
            'K' => 18,
            'L' => 16,
            'M' => 18,
            'N' => 18,
            'O' => 30,
            'P' => 32,
            'Q' => 30,
            'R' => 30,
            'S' => 14,
            'T' => 14,
            'U' => 14,
            'V' => 14,
            'W' => 30,
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
