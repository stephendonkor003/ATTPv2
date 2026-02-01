<?php

namespace App\Exports\Procurement;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MethodPlannedTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            ['Open Tendering', 'Advertising, Evaluation, Award', 90, 'Standard open procurement method', 'Yes'],
            ['Request for Quotation', 'Quotation Request, Evaluation', 30, 'For lower value procurements', 'Yes'],
        ];
    }

    public function headings(): array
    {
        return ['method_name', 'method_milestones', 'method_target_days', 'description', 'is_active'];
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
            'B' => 40,
            'C' => 20,
            'D' => 40,
            'E' => 12,
        ];
    }
}
