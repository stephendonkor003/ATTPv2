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
            'me.data_entry.view',
        ]);

        // Evaluation Evaluator
        $this->syncRolePermissionsByNames('Evaluation Evaluator', [
            'evaluations.evaluate',
            'me.configuration.view',
            'me.configuration.manage',
            'me.data_entry.view',
            'me.data_entry.manage',
        ]);

        $portfolioLeadershipPermissions = [
            'dashboard.access',
            'budget.access',
            'budget.structure.manage',
            'budget.activities.manage',
            'budget.allocations.manage',
            'budget.reports.view',
            'budget.project_financial_position.view',
            'budget.summary.view',
            'sector.view',
            'sector.edit',
            'program.view',
            'program.create',
            'program.edit',
            'project.view',
            'project.create',
            'project.edit',
            'activities.view',
            'activities.create',
            'activities.edit',
            'subactivities.view',
            'subactivities.create',
            'subactivities.edit',
            'finance.access',
            'finance.resources.view',
            'finance.resources.create',
            'finance.resources.edit',
            'finance.resources.delete',
            'finance.program_funding.view',
            'finance.commitments.view',
            'finance.commitments.create',
            'finance.commitments.edit',
            'finance.purchase_requests.view',
            'finance.purchase_requests.view_all',
            'finance.purchase_requests.send',
            'finance.purchase_requests.approve',
            'finance.purchase_orders.create',
            'finance.awp.view',
            'finance.awp.create',
            'finance.awp.edit',
            'finance.executions.view',
            'me.configuration.view',
            'me.configuration.manage',
            'me.data_entry.view',
            'me.data_entry.manage',
            'forms.manage',
            'forms.submit',
            'forms.approve',
            'forms.reject',
            'evaluations.manage',
            'evaluations.evaluate',
            'evaluations.view_all',
            'site_visits.view',
            'site_visits.create',
            'site_visits.observe',
            'site_visits.submit',
            'site_visits.approve',
            'grm.submit',
            'grm.view',
            'grm.configure',
            'grm.escalations',
            'grm.reports',
        ];

        // Portfolio Manager / Coordinator
        $this->syncRolePermissionsByNames('Portfolio Manager', $portfolioLeadershipPermissions);
        $this->syncRolePermissionsByNames('Portfolio Coordinator', $portfolioLeadershipPermissions);

        // Monitoring and Evaluation Manager
        $this->syncRolePermissionsByNames('Monitoring and Evaluation Manager', [
            'me.configuration.view',
            'me.configuration.manage',
            'me.data_entry.view',
            'me.data_entry.manage',
            'budget.access',
            'budget.reports.view',
            'budget.summary.view',
            'sector.view',
            'program.view',
            'project.view',
            'activities.view',
            'subactivities.view',
            'grm.submit',
            'grm.view',
            'grm.reports',
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
            'discussions.view',
            'discussions.create',
            'discussions.manage',
            'discussions.thematic_areas.manage',
            'discussions.participants.manage',
            'discussions.moderate',
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
