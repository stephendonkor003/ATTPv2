<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ApprovedWorkPlanRegistryExport implements FromArray, WithColumnWidths, WithStyles
{
    public function __construct(
        protected $folders,
        protected array $summary,
        protected ?string $selectedProgramId,
        protected mixed $selectedYear
    ) {}

    public function array(): array
    {
        $rows = [
            ['Work Plans Registry'],
            ['Generated', now()->format('Y-m-d H:i')],
            ['Program Filter', $this->selectedProgramId ?: 'All Programs'],
            ['Year Filter', $this->selectedYear ?: 'All Years'],
            [],
            ['Summary'],
            ['Folders', $this->summary['folders'] ?? 0],
            ['Programs', $this->summary['programs'] ?? 0],
            ['Items Saved', $this->summary['items'] ?? 0],
            ['Work Plan Amount', $this->summary['amount'] ?? 0],
            [],
            [
                'Folder',
                'Program',
                'Year',
                'Currency',
                'Items Saved',
                'Planned Amount',
                'Committed Amount',
                'Approved Items',
                'Submitted Items',
                'Draft Items',
                'Closed Items',
                'Latest Update',
                'Preview Items',
            ],
        ];

        foreach ($this->folders as $folder) {
            $rows[] = [
                $folder['folder_name'] ?? 'Untitled Work Plan',
                $folder['program']?->name ?? 'Program not linked',
                $folder['year'] ?: '',
                $folder['currency'] ?? 'USD',
                $folder['items_count'] ?? 0,
                $folder['planned_amount'] ?? 0,
                $folder['committed_amount'] ?? 0,
                $folder['approved_count'] ?? 0,
                $folder['submitted_count'] ?? 0,
                $folder['draft_count'] ?? 0,
                $folder['closed_count'] ?? 0,
                $folder['latest_update'] ? \Illuminate\Support\Carbon::parse($folder['latest_update'])->format('Y-m-d H:i') : '',
                collect($folder['items_preview'] ?? [])->implode(', '),
            ];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 34,
            'C' => 12,
            'D' => 12,
            'E' => 14,
            'F' => 18,
            'G' => 18,
            'H' => 14,
            'I' => 14,
            'J' => 14,
            'K' => 14,
            'L' => 20,
            'M' => 48,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            6 => ['font' => ['bold' => true]],
            12 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'E2E8F0']]],
        ];
    }
}
