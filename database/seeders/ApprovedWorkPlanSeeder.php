<?php

namespace Database\Seeders;

use App\Models\ApprovedWorkPlan;
use App\Models\BudgetCommitment;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ApprovedWorkPlanSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'finance.awp.view', 'module' => 'Finance', 'description' => 'View Approved Work Plans'],
            ['name' => 'finance.awp.create', 'module' => 'Finance', 'description' => 'Create Approved Work Plans'],
            ['name' => 'finance.awp.edit', 'module' => 'Finance', 'description' => 'Edit Approved Work Plans'],
            ['name' => 'finance.awp.approve', 'module' => 'Finance', 'description' => 'Approve and close Approved Work Plans'],
            ['name' => 'finance.awp.delete', 'module' => 'Finance', 'description' => 'Delete draft Approved Work Plans'],
        ])->map(fn ($permission) => Permission::updateOrCreate(['name' => $permission['name']], $permission));

        foreach (['System Admin', 'Finance Manager'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
            }
        }

        $fundingPartner = Role::where('name', 'Funding Partner')->first();
        if ($fundingPartner) {
            $fundingPartner->permissions()->syncWithoutDetaching(
                $permissions->whereIn('name', ['finance.awp.view', 'finance.awp.approve'])->pluck('id')->all()
            );
        }

        $financeOfficer = Role::where('name', 'Finance Officer')->first();
        if ($financeOfficer) {
            $financeOfficer->permissions()->syncWithoutDetaching(
                $permissions->whereIn('name', ['finance.awp.view', 'finance.awp.create', 'finance.awp.edit'])->pluck('id')->all()
            );
        }

        $commitments = BudgetCommitment::with(['programFunding.program'])
            ->where('status', BudgetCommitment::STATUS_APPROVED)
            ->whereNotNull('commitment_amount')
            ->limit(5)
            ->get();

        foreach ($commitments as $commitment) {
            $codeSuffix = Str::upper(Str::substr(str_replace('-', '', (string) $commitment->id), -8));

            ApprovedWorkPlan::updateOrCreate(
                ['budget_commitment_id' => $commitment->id],
                [
                    'awp_code' => 'AWP-' . ($commitment->commitment_year ?? now()->format('Y')) . '-' . $codeSuffix,
                    'title' => 'Approved Work Plan - ' . ($commitment->programFunding?->program?->name ?? $commitment->programFunding?->program_name ?? 'Budget Commitment'),
                    'program_funding_id' => $commitment->program_funding_id,
                    'governance_node_id' => $commitment->governance_node_id,
                    'fiscal_year' => (string) ($commitment->commitment_year ?? now()->format('Y')),
                    'planned_amount' => (float) $commitment->commitment_amount,
                    'currency' => 'USD',
                    'status' => 'approved',
                    'description' => 'Seeded AWP linked to an approved budget commitment.',
                    'expected_outputs' => 'Implementation outputs aligned to the approved budget commitment.',
                    'implementation_notes' => 'Controlled by the ATTP Secretariat.',
                    'approved_at' => now(),
                ]
            );
        }
    }

}
