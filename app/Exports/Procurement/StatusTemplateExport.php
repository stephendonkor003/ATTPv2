<?php

namespace App\Exports\Procurement;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StatusTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            ['Draft', 'Initial draft status', '#6c757d', 1, 'Yes'],
            ['Pending', 'Awaiting approval', '#ffc107', 2, 'Yes'],
            ['Approved', 'Has been approved', '#28a745', 3, 'Yes'],
            ['Rejected', 'Has been rejected', '#dc3545', 4, 'Yes'],
            ['Completed', 'Process completed', '#17a2b8', 5, 'Yes'],
        ];
    }

    public function headings(): array
    {
        return ['name', 'description', 'color', 'sort_order', 'is_active'];
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
            'A' => 20,
            'B' => 35,
            'C' => 15,
            'D' => 12,
            'E' => 12,
        ];
    }
}
