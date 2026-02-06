<?php

namespace App\Imports\Procurement;

use App\Models\ProcurementDeliverable;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class VendorDeliverableImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public function __construct(private string $procurementId, private string $vendorId)
    {
    }

    public function model(array $row)
    {
        $timelineStart = $this->parseDate($row['timeline_start'] ?? null);
        $timelineEnd = $this->parseDate($row['timeline_end'] ?? null);

        $status = $this->normalizeStatus($row['status'] ?? null);
        $type = $this->normalizeType($row['type'] ?? null);

        return new ProcurementDeliverable([
            'procurement_id' => $this->procurementId,
            'vendor_id' => $this->vendorId,
            'title' => $row['title'],
            'type' => $type,
            'description' => $row['description'] ?? null,
            'timeline_start' => $timelineStart,
            'timeline_end' => $timelineEnd,
            'amount' => $row['amount'] ?? null,
            'currency' => $row['currency'] ?? null,
            'status' => $status,
            'sequence' => $row['sequence'] ?? 0,
            'vendor_approval_status' => 'approved',
            'vendor_approved_by' => $this->vendorId,
            'vendor_approved_at' => now(),
            'admin_approval_status' => 'pending',
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'type' => 'nullable|string|in:deliverable,milestone',
            'description' => 'nullable|string',
            'timeline_start' => 'nullable',
            'timeline_end' => 'nullable',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'sequence' => 'nullable|integer',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'title.required' => 'Each row must include a deliverable title.',
            'type.in' => 'Type must be deliverable or milestone.',
            'status.in' => 'Status must be pending, in_progress, completed, or cancelled.',
        ];
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        try {
            return date('Y-m-d', strtotime((string) $value));
        } catch (\Exception $e) {
            return null;
        }
    }

    private function normalizeStatus(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['pending', 'in_progress', 'completed', 'cancelled'], true)
            ? $value
            : 'pending';
    }

    private function normalizeType(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['deliverable', 'milestone'], true)
            ? $value
            : 'deliverable';
    }
}
