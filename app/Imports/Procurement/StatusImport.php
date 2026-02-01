<?php

namespace App\Imports\Procurement;

use App\Models\ProcurementStatus;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\Auth;

class StatusImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public function model(array $row)
    {
        return new ProcurementStatus([
            'name' => $row['name'],
            'description' => $row['description'] ?? null,
            'color' => $row['color'] ?? '#6c757d',
            'is_active' => $this->parseBoolean($row['is_active'] ?? 'yes'),
            'sort_order' => $row['sort_order'] ?? 0,
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'is_active' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => 'The name field is required for each row.',
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
