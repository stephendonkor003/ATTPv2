<?php

namespace App\Exports;

use App\Models\ThinkTankProcurementItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class ThinkTankProcurementStepExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStrictNullComparison
{
    public function __construct(private readonly Collection $items) {}

    public function collection(): Collection
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            'ATTP Item ID',
            'Plan Code',
            'Think Tank',
            'Consortium',
            'Financial Year',
            'STEP Reference',
            'Activity Reference No.',
            'Activity Description',
            'Loan / Credit No.',
            'Component',
            'Category',
            'Procurement Method',
            'Market Approach',
            'Review Type',
            'Estimated Amount (US$)',
            'High SEA/SH Risk',
            'Procurement Document Type',
            'Process Status',
            'Activity Status',
            'Currency',
            'Planned Start Date',
            'Planned End Date',
            'Planned Quarter',
            'Status',
            'TOR Attached',
            'Supporting Document Count',
        ];
    }

    public function map($item): array
    {
        /** @var ThinkTankProcurementItem $item */
        return [
            $item->item_code,
            $item->plan?->plan_code,
            $item->plan?->member?->name,
            $item->plan?->consortium?->name,
            $item->plan?->fiscal_year,
            $item->step_reference,
            $item->source_reference,
            $item->title,
            $item->loan_credit_no,
            $item->component,
            $item->procurement_category ? str_replace('_', ' ', $item->procurement_category) : null,
            $item->procurement_method,
            $item->market_approach,
            $item->review_type,
            (float) $item->estimated_amount,
            $item->source_sea_sh_risk,
            $item->source_document_type,
            $item->source_process_status,
            $item->workflowActivityStatus(),
            $item->currency,
            $item->planned_start_date?->format('Y-m-d'),
            $item->planned_end_date?->format('Y-m-d'),
            $item->planned_quarter,
            str_replace('_', ' ', $item->status),
            $item->documents->contains('document_type', 'tor') ? 'Yes' : 'No',
            $item->documents->where('document_type', 'supporting')->count(),
        ];
    }
}
