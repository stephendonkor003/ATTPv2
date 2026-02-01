<?php

namespace App\Exports\Procurement;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StageTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            ['Planning', 'Initial planning and preparation stage', 1, 'Yes'],
            ['Tendering', 'Tender publication and submission stage', 2, 'Yes'],
            ['Evaluation', 'Bid evaluation and selection stage', 3, 'Yes'],
            ['Award', 'Contract award stage', 4, 'Yes'],
        ];
    }

    public function headings(): array
    {
        return ['stage_name', 'description', 'sort_order', 'is_active'];
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
            'A' => 25,
            'B' => 45,
            'C' => 12,
            'D' => 12,
        ];
    }
}
