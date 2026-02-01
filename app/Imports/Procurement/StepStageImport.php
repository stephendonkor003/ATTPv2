<?php

namespace App\Imports\Procurement;

use App\Models\ProcurementStepStage;
use App\Models\ProcurementStage;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\Auth;

class StepStageImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    protected $stageCache = [];

    public function model(array $row)
    {
        $stageId = null;

        // Look up stage by name if provided
        if (!empty($row['parent_stage'])) {
            $stageName = trim($row['parent_stage']);
            if (!isset($this->stageCache[$stageName])) {
                $stage = ProcurementStage::where('stage_name', $stageName)->first();
                $this->stageCache[$stageName] = $stage ? $stage->id : null;
            }
            $stageId = $this->stageCache[$stageName];
        }

        return new ProcurementStepStage([
            'name' => $row['name'],
            'stage_id' => $stageId,
            'description' => $row['description'] ?? null,
            'sort_order' => $row['sort_order'] ?? 0,
            'is_active' => $this->parseBoolean($row['is_active'] ?? 'yes'),
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_stage' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|string',
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
