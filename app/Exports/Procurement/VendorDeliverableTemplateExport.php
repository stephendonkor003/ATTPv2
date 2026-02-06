<?php

namespace App\Exports\Procurement;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VendorDeliverableTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            [
                'Inception Report',
                'deliverable',
                'Kickoff report covering scope, methodology, and team.',
                '2026-03-01',
                '2026-03-15',
                5000,
                'USD',
                'pending',
                1,
            ],
            [
                'Kickoff Workshop',
                'milestone',
                'Workshop to align stakeholders and confirm delivery plan.',
                '2026-03-05',
                '2026-03-05',
                0,
                'USD',
                'pending',
                2,
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'title',
            'type',
            'description',
            'timeline_start',
            'timeline_end',
            'amount',
            'currency',
            'status',
            'sequence',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 14,
            'C' => 50,
            'D' => 16,
            'E' => 16,
            'F' => 12,
            'G' => 10,
            'H' => 14,
            'I' => 10,
        ];
    }
}
