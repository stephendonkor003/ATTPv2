<?php

namespace App\Exports\Procurement;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StepApprovalTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        return [
            ['Initial Review', 'Document Preparation', '', 'First level review', 1, 'Yes', 'Yes'],
            ['Manager Approval', 'Document Preparation', '', 'Manager level approval', 2, 'Yes', 'Yes'],
            ['Director Sign-off', 'Technical Review', '', 'Director approval required', 3, 'Yes', 'Yes'],
        ];
    }

    public function headings(): array
    {
        return ['name', 'step_stage', 'governance_node', 'description', 'approval_order', 'is_required', 'is_active'];
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
            'B' => 25,
            'C' => 20,
            'D' => 35,
            'E' => 15,
            'F' => 12,
            'G' => 12,
        ];
    }
}
