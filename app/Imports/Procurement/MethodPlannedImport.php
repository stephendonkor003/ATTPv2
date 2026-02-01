<?php

namespace App\Imports\Procurement;

use App\Models\ProcurementMethodPlanned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MethodPlannedImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    private array $rowCounters = [];

    public function collection(Collection $rows)
    {
        $methods = [];

        foreach ($rows as $row) {
            $methodName = trim((string) ($row['method_name'] ?? ''));
            if ($methodName === '') {
                continue;
            }

            $methodKey = mb_strtolower($methodName);
            $method = $methods[$methodKey] ?? ProcurementMethodPlanned::where('method_name', $methodName)->first();

            if (!$method) {
                $method = ProcurementMethodPlanned::create([
                    'method_name' => $methodName,
                    'description' => $row['method_description'] ?? null,
                    'is_active' => $this->parseBoolean($row['method_is_active'] ?? 'yes'),
                    'created_by' => Auth::id(),
                ]);
            } else {
                $method->update([
                    'description' => $row['method_description'] ?? $method->description,
                    'is_active' => $this->parseBoolean($row['method_is_active'] ?? ($method->is_active ? 'yes' : 'no')),
                ]);
            }

            $methods[$methodKey] = $method;

            $method->milestones()->create([
                'title' => trim($row['milestone_title'] ?? ''),
                'description' => $row['milestone_description'] ?? null,
                'target_days' => (int) ($row['milestone_target_days'] ?? 0),
                'sort_order' => $this->resolveSortOrder($methodKey, $row['milestone_sort_order'] ?? null),
                'is_active' => $this->parseBoolean($row['milestone_is_active'] ?? 'yes'),
                'created_by' => Auth::id(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'method_name' => 'required|string|max:255',
            'method_description' => 'nullable|string',
            'method_is_active' => 'nullable|string',
            'milestone_title' => 'required|string|max:255',
            'milestone_description' => 'nullable|string',
            'milestone_target_days' => 'nullable|integer|min:0',
            'milestone_sort_order' => 'nullable|integer|min:0',
            'milestone_is_active' => 'nullable|string',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'method_name.required' => 'Each row must include a method name.',
            'milestone_title.required' => 'Each row must include a milestone title.',
        ];
    }

    private function resolveSortOrder(string $methodKey, $value): int
    {
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        $this->rowCounters[$methodKey] = ($this->rowCounters[$methodKey] ?? 0) + 1;

        return $this->rowCounters[$methodKey];
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['yes', 'true', '1', 'active', 'y'], true);
    }
}
