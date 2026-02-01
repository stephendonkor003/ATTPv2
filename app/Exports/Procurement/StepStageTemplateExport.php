<?php

namespace App\Exports\Procurement;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StepStageTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            ['Document Preparation', 'Planning', 'Preparing procurement documents', 1, 'Yes'],
            ['Technical Review', 'Evaluation', 'Technical evaluation of bids', 2, 'Yes'],
            ['Financial Review', 'Evaluation', 'Financial evaluation of bids', 3, 'Yes'],
        ];
    }

    public function headings(): array
    {
        return ['name', 'parent_stage', 'description', 'sort_order', 'is_active'];
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
            'B' => 20,
            'C' => 40,
            'D' => 12,
            'E' => 12,
        ];
    }
}
