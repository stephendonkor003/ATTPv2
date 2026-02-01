<?php

namespace App\Imports\Procurement;

use App\Models\ProcurementStage;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\Auth;

class StageImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public function model(array $row)
    {
        return new ProcurementStage([
            'stage_name' => $row['stage_name'],
            'description' => $row['description'] ?? null,
            'is_active' => $this->parseBoolean($row['is_active'] ?? 'yes'),
            'sort_order' => $row['sort_order'] ?? 0,
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'stage_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'stage_name.required' => 'The stage name field is required for each row.',
        ];
    }

    protected function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['yes', 'true', '1', 'active', 'y']);
    }
}
