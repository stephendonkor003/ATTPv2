<?php

namespace App\Exports\Procurement;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GeographicTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            ['Africa', 'AFR', 'African continent', 'Yes', 1],
            ['Europe', 'EUR', 'European continent', 'Yes', 2],
        ];
    }

    public function headings(): array
    {
        return ['name', 'code', 'description', 'is_active', 'sort_order'];
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
            'B' => 15,
            'C' => 40,
            'D' => 12,
            'E' => 12,
        ];
    }
}
