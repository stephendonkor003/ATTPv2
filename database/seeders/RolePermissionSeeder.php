<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // System Admin → ALL permissions
        $admin = Role::where('name', 'System Admin')->first();
        if ($admin) {
            $admin->permissions()->sync(Permission::pluck('id'));
        }

        // HR Manager
        $this->syncRolePermissionsByIds('HR Manager', Permission::where('module', 'HR')->pluck('id')->all());

        // HR Officer
        $this->syncRolePermissionsByNames('HR Officer', [
            'hr.access',
            'hr.positions.view',
            'hr.vacancies.view',
            'hr.applicants.view',
            'hr.applicants.manage',
            'hr.ai.score',
        ]);

        // Finance Manager
        $this->syncRolePermissionsByIds('Finance Manager', Permission::where('module', 'Finance')->pluck('id')->all());

        // Finance Officer
        $this->syncRolePermissionsByNames('Finance Officer', [
            'finance.access',
            'finance.commitments.manage',
            'finance.executions.view',
        ]);

        // Budget Officer
        $this->syncRolePermissionsByNames('Budget Officer', [
            'budget.access',
            'budget.structure.manage',
            'budget.activities.manage',
            'budget.allocations.manage',
            'budget.reports.view',
            'budget.project_financial_position.view',
        ]);

        // Auditor
        $this->syncRolePermissionsByNames('Auditor', [
            'finance.access',
            'finance.executions.view',
            'budget.access',
            'budget.reports.view',
            'budget.project_financial_position.view',
            'budget.summary.view',
            'hr.analytics.view',
            'national_data.review',
        ]);

        // Prescreening Evaluator
        $this->syncRolePermissionsByNames('Prescreening Evaluator', [
            'prescreening.access',
            'prescreening.evaluate',
            'me.configuration.view',
        ]);

        // Evaluation Evaluator
        $this->syncRolePermissionsByNames('Evaluation Evaluator', [
            'evaluations.evaluate',
            'me.configuration.view',
            'me.configuration.manage',
            'world.indicators.manage',
        ]);

        $communicationOfficerPermissions = [
            'communications.view',
            'communications.respond',
            'news.manage',
            'news.approve',
            'questions.view',
            'questions.respond',
            'national_data.review',
            'national_data.approve',
        ];

        // Communication Officer
        $this->syncRolePermissionsByNames('Communication Officer', $communicationOfficerPermissions);

        // Communications Officer (legacy plural label)
        $this->syncRolePermissionsByNames('Communications Officer', $communicationOfficerPermissions);

        // Member State Focal Point
        $this->syncRolePermissionsByNames('Member State Focal Point', [
            'member_state.treaties.view',
            'member_state.treaties.update',
            'member_state.treaties.documents.download',
        ]);
    }

    private function syncRolePermissionsByNames(string $roleName, array $permissionNames): void
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id')->all();
        $this->syncRolePermissionsByIds($roleName, $permissionIds);
    }

    private function syncRolePermissionsByIds(string $roleName, array $permissionIds): void
    {
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            return;
        }

        $role->permissions()->sync($permissionIds);
    }
}
