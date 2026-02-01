<?php

namespace App\Imports\Procurement;

use App\Models\ProcurementStepApproval;
use App\Models\ProcurementStepStage;
use App\Models\GovernanceNode;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\Auth;

class StepApprovalImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    protected $stepStageCache = [];
    protected $governanceCache = [];

    public function model(array $row)
    {
        $stepStageId = null;
        $governanceNodeId = null;

        // Look up step stage by name if provided
        if (!empty($row['step_stage'])) {
            $stepStageName = trim($row['step_stage']);
            if (!isset($this->stepStageCache[$stepStageName])) {
                $stepStage = ProcurementStepStage::where('name', $stepStageName)->first();
                $this->stepStageCache[$stepStageName] = $stepStage ? $stepStage->id : null;
            }
            $stepStageId = $this->stepStageCache[$stepStageName];
        }

        // Look up governance node by name if provided
        if (!empty($row['governance_node'])) {
            $nodeName = trim($row['governance_node']);
            if (!isset($this->governanceCache[$nodeName])) {
                $node = GovernanceNode::where('name', $nodeName)->first();
                $this->governanceCache[$nodeName] = $node ? $node->id : null;
            }
            $governanceNodeId = $this->governanceCache[$nodeName];
        }

        return new ProcurementStepApproval([
            'name' => $row['name'],
            'step_stage_id' => $stepStageId,
            'governance_node_id' => $governanceNodeId,
            'description' => $row['description'] ?? null,
            'approval_order' => $row['approval_order'] ?? 0,
            'is_required' => $this->parseBoolean($row['is_required'] ?? 'yes'),
            'is_active' => $this->parseBoolean($row['is_active'] ?? 'yes'),
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'step_stage' => 'nullable|string|max:255',
            'governance_node' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'approval_order' => 'nullable|integer',
            'is_required' => 'nullable|string',
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
