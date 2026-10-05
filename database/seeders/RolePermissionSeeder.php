<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

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
            'dashboard.access',
            'api_sync.view',
            'api_sync.audit.view',
            'api_sync.documents.view',
            'settings.au_master_data.view',
            'treaties.view',
            'finance.access',
            'finance.awp.view',
            'finance.commitments.view',
            'finance.commitments.view_all',
            'finance.departments.view',
            'finance.executions.view',
            'finance.funders.view',
            'finance.governance_structure.view',
            'finance.program_funding.view',
            'finance.purchase_requests.view',
            'finance.purchase_requests.view_all',
            'finance.resources.view',
            'budget.access',
            'budget.reports.view',
            'budget.project_financial_position.view',
            'budget.summary.view',
            'sector.view',
            'program.view',
            'program.report',
            'project.view',
            'project.report',
            'activities.view',
            'activity.report',
            'subactivities.view',
            'consortiums.view',
            'consortiums.analysis.view',
            'discussions.view',
            'evaluations.view_all',
            'grm.view',
            'grm.reports',
            'hr.access',
            'hr.positions.view',
            'hr.vacancies.view',
            'hr.analytics.view',
            'hr.applicants.view',
            'hr.employees.view',
            'hr.view_all_nodes',
            'hrm.positions.view',
            'hrm.vacancies.view',
            'biannual_site_visits.view',
            'biannual_site_visits.export',
            'me.configuration.view',
            'me.data_entry.view',
            'me.mission_reports.view',
            'me.performance_reports.view',
            'me.reporting_notifications.view',
            'me.reports.export',
            'me.results.view',
            'prescreening.access',
            'prescreening.reports.view_all',
            'prescreening.view_all',
            'procurement.audit',
            'procurement.plan.view',
            'procurement.view_all',
            'site_visits.view',
            'communications.view',
            'national_data.review',
            'questions.view',
            'system.audit.view',
            'think_tanks.directory.view',
            'think_tanks.funding.history.view',
            'think_tanks.funding.view',
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
            'me.framework.manage',
            'me.targets.manage',
            'me.submissions.review',
            'me.results.view',
            'me.reports.export',
            'me.dqa.manage',
            'me.performance_reports.view',
            'me.mission_reports.view',
            'me.reporting_notifications.view',
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
            'biannual_site_visits.view',
            'biannual_site_visits.create',
            'biannual_site_visits.respond',
            'biannual_site_visits.submit',
            'biannual_site_visits.approve',
            'biannual_site_visits.export',
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
        $melManagementPermissions = [
            'me.configuration.view',
            'me.configuration.manage',
            'me.data_entry.view',
            'me.data_entry.manage',
            'me.framework.manage',
            'me.targets.manage',
            'me.submissions.review',
            'me.results.view',
            'me.reports.export',
            'me.dqa.manage',
            'me.performance_reports.view',
            'me.performance_reports.review',
            'me.performance_reports.archive',
            'me.mission_reports.view',
            'me.mission_reports.manage',
            'me.mission_reports.review',
            'me.mission_reports.archive',
            'me.reporting_notifications.view',
            'biannual_site_visits.view',
            'biannual_site_visits.create',
            'biannual_site_visits.respond',
            'biannual_site_visits.submit',
            'biannual_site_visits.approve',
            'biannual_site_visits.templates.manage',
            'biannual_site_visits.export',
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
        ];
        $this->syncRolePermissionsByNames('Monitoring and Evaluation Manager', $melManagementPermissions);
        $this->syncRolePermissionsByNames('M&e', $melManagementPermissions);

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

        $this->syncRolePermissionsByNames('API Sync Administrator', [
            'api_sync.view',
            ...((bool) config('api_sync.legacy_v1_enabled', false) ? [
                'api_sync.generate',
                'api_sync.revoke',
            ] : []),
            'api_sync.audit.view',
            'api_sync.invitations.approve',
            'api_sync.invitations.decline',
            'api_sync.invitations.revoke',
            'api_sync.documents.view',
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
        if (! $role) {
            return;
        }

        $role->permissions()->sync($permissionIds);
    }
}
