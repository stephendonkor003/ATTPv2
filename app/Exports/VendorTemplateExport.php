<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VendorTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            ['Acme Supplies Ltd', 'vendor1@example.com', 'goods', 'no', 'no'],
            ['BlueTech Electronics', 'vendor2@example.com', 'electronics', 'no', 'no'],
        ];
    }

    public function headings(): array
    {
        return ['name', 'email', 'vendor_category', 'disabled', 'blacklisted'];
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
            'A' => 28,
            'B' => 30,
            'C' => 20,
            'D' => 12,
            'E' => 12,
        ];
    }
}
