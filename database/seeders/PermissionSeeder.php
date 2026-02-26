<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // System / Dashboard
            ['name' => 'dashboard.access', 'module' => 'dashboard', 'description' => 'Access the main dashboard'],
            ['name' => 'users.manage', 'module' => 'system', 'description' => 'Manage system users'],
            ['name' => 'roles.manage', 'module' => 'system', 'description' => 'Manage roles and role settings'],
            ['name' => 'permissions.manage', 'module' => 'system', 'description' => 'Manage permission catalog and assignments'],

            // Prescreening
            ['name' => 'prescreening.access', 'module' => 'prescreening', 'description' => 'Access prescreening module'],
            ['name' => 'prescreening.evaluate', 'module' => 'prescreening', 'description' => 'Evaluate prescreening submissions'],
            ['name' => 'prescreening.manage', 'module' => 'prescreening', 'description' => 'Manage prescreening templates and assignments'],
            ['name' => 'prescreening.view_all', 'module' => 'prescreening', 'description' => 'View all prescreening submissions'],
            ['name' => 'prescreening.request_rework', 'module' => 'prescreening', 'description' => 'Request prescreening rework'],

            // HR
            ['name' => 'hr.access', 'module' => 'HR', 'description' => 'Access HR module'],
            ['name' => 'hrm.positions.view', 'module' => 'HR', 'description' => 'View HR positions'],
            ['name' => 'hrm.positions.create', 'module' => 'HR', 'description' => 'Create HR positions'],
            ['name' => 'hrm.vacancies.view', 'module' => 'HR', 'description' => 'View HR vacancies'],
            ['name' => 'hrm.vacancies.create', 'module' => 'HR', 'description' => 'Create HR vacancies'],
            ['name' => 'hrm.vacancies.submit', 'module' => 'HR', 'description' => 'Submit HR vacancies for approval'],
            ['name' => 'hr.vacancies.approve', 'module' => 'HR', 'description' => 'Approve and publish HR vacancies'],
            ['name' => 'hr.positions.view', 'module' => 'HR', 'description' => 'View HR positions'],
            ['name' => 'hr.positions.create', 'module' => 'HR', 'description' => 'Create HR positions'],
            ['name' => 'hr.vacancies.view', 'module' => 'HR', 'description' => 'View HR vacancies'],
            ['name' => 'hr.vacancies.create', 'module' => 'HR', 'description' => 'Create HR vacancies'],
            ['name' => 'hr.vacancies.workflow', 'module' => 'HR', 'description' => 'Manage HR vacancy workflow'],
            ['name' => 'hr.applicants.view', 'module' => 'HR', 'description' => 'View HR applicants'],
            ['name' => 'hr.applicants.manage', 'module' => 'HR', 'description' => 'Manage HR applicants'],
            ['name' => 'hr.applicants.hire', 'module' => 'HR', 'description' => 'Hire HR applicants'],
            ['name' => 'hr.ai.score', 'module' => 'HR', 'description' => 'Run AI scoring for HR applicants'],
            ['name' => 'hr.analytics.view', 'module' => 'HR', 'description' => 'View HR analytics'],

            // Finance
            ['name' => 'finance.access', 'module' => 'Finance', 'description' => 'Access finance module'],
            ['name' => 'finance.resources.view', 'module' => 'Finance', 'description' => 'View finance resources'],
            ['name' => 'finance.resources.create', 'module' => 'Finance', 'description' => 'Create finance resources'],
            ['name' => 'finance.resources.manage', 'module' => 'Finance', 'description' => 'Manage finance resources'],
            ['name' => 'finance.funders.view', 'module' => 'Finance', 'description' => 'View finance funders'],
            ['name' => 'finance.funders.create', 'module' => 'Finance', 'description' => 'Create finance funders'],
            ['name' => 'finance.funders.edit', 'module' => 'Finance', 'description' => 'Edit finance funders'],
            ['name' => 'finance.funders.manage', 'module' => 'Finance', 'description' => 'Manage finance funders'],
            ['name' => 'finance.departments.view', 'module' => 'Finance', 'description' => 'View finance departments'],
            ['name' => 'finance.departments.create', 'module' => 'Finance', 'description' => 'Create finance departments'],
            ['name' => 'finance.departments.edit', 'module' => 'Finance', 'description' => 'Edit finance departments'],
            ['name' => 'finance.departments.delete', 'module' => 'Finance', 'description' => 'Delete finance departments'],
            ['name' => 'finance.departments.manage', 'module' => 'Finance', 'description' => 'Manage finance departments'],
            ['name' => 'finance.governance_structure.view', 'module' => 'Finance', 'description' => 'View governance structure'],
            ['name' => 'finance.governance_structure.create', 'module' => 'Finance', 'description' => 'Create governance structure records'],
            ['name' => 'finance.governance_structure.edit', 'module' => 'Finance', 'description' => 'Edit governance structure records'],
            ['name' => 'finance.governance_structure.delete', 'module' => 'Finance', 'description' => 'Delete governance structure records'],
            ['name' => 'finance.governance_structure.manage', 'module' => 'Finance', 'description' => 'Manage governance structure records'],
            ['name' => 'finance.program_funding.view', 'module' => 'Finance', 'description' => 'View program funding'],
            ['name' => 'finance.program_funding.create', 'module' => 'Finance', 'description' => 'Create program funding'],
            ['name' => 'finance.program_funding.edit', 'module' => 'Finance', 'description' => 'Edit program funding'],
            ['name' => 'finance.program_funding.delete', 'module' => 'Finance', 'description' => 'Delete program funding'],
            ['name' => 'finance.program_funding.submit', 'module' => 'Finance', 'description' => 'Submit program funding for approval'],
            ['name' => 'finance.program_funding.approve', 'module' => 'Finance', 'description' => 'Approve program funding'],
	            ['name' => 'finance.program_funding.manage', 'module' => 'Finance', 'description' => 'Manage program funding'],
		            ['name' => 'finance.commitments.view', 'module' => 'Finance', 'description' => 'View finance commitments'],
		            ['name' => 'finance.commitments.view_all', 'module' => 'Finance', 'description' => 'View all commitments across governance nodes'],
		            ['name' => 'finance.commitments.create', 'module' => 'Finance', 'description' => 'Create finance commitments'],
		            ['name' => 'finance.commitments.edit', 'module' => 'Finance', 'description' => 'Edit finance commitments'],
		            ['name' => 'finance.commitments.delete', 'module' => 'Finance', 'description' => 'Delete finance commitments'],
		            ['name' => 'finance.commitments.manage', 'module' => 'Finance', 'description' => 'Manage finance commitments'],
		            ['name' => 'finance.purchase_requests.view', 'module' => 'Finance', 'description' => 'View purchase requests'],
		            ['name' => 'finance.purchase_requests.view_all', 'module' => 'Finance', 'description' => 'View all purchase requests across governance nodes'],
		            ['name' => 'finance.purchase_requests.send', 'module' => 'Finance', 'description' => 'Send purchase requests via email'],
		            ['name' => 'finance.executions.view', 'module' => 'Finance', 'description' => 'View finance execution dashboard'],

            // Budget
            ['name' => 'budget.access', 'module' => 'Budget', 'description' => 'Access budget module'],
            ['name' => 'budget.structure.manage', 'module' => 'Budget', 'description' => 'Manage budget structure'],
            ['name' => 'budget.activities.manage', 'module' => 'Budget', 'description' => 'Manage budget activities'],
            ['name' => 'budget.allocations.manage', 'module' => 'Budget', 'description' => 'Manage budget allocations'],
            ['name' => 'budget.reports.view', 'module' => 'Budget', 'description' => 'View budget reports'],
            ['name' => 'budget.summary.view', 'module' => 'Budget', 'description' => 'View budget summary dashboard'],
            ['name' => 'sector.view', 'module' => 'Budget', 'description' => 'View sectors'],
            ['name' => 'sector.create', 'module' => 'Budget', 'description' => 'Create sectors'],
            ['name' => 'sector.edit', 'module' => 'Budget', 'description' => 'Edit sectors'],
            ['name' => 'sector.delete', 'module' => 'Budget', 'description' => 'Delete sectors'],
            ['name' => 'program.view', 'module' => 'Budget', 'description' => 'View programs'],
            ['name' => 'program.create', 'module' => 'Budget', 'description' => 'Create programs'],
            ['name' => 'program.edit', 'module' => 'Budget', 'description' => 'Edit programs'],
            ['name' => 'program.delete', 'module' => 'Budget', 'description' => 'Delete programs'],
            ['name' => 'project.view', 'module' => 'Budget', 'description' => 'View projects'],
            ['name' => 'project.create', 'module' => 'Budget', 'description' => 'Create projects'],
            ['name' => 'project.edit', 'module' => 'Budget', 'description' => 'Edit projects'],
            ['name' => 'project.delete', 'module' => 'Budget', 'description' => 'Delete projects'],
            ['name' => 'activities.view', 'module' => 'Budget', 'description' => 'View activities'],
            ['name' => 'activities.create', 'module' => 'Budget', 'description' => 'Create activities'],
            ['name' => 'activities.edit', 'module' => 'Budget', 'description' => 'Edit activities'],
            ['name' => 'activities.delete', 'module' => 'Budget', 'description' => 'Delete activities'],
            ['name' => 'subactivities.view', 'module' => 'Budget', 'description' => 'View sub-activities'],
            ['name' => 'subactivities.create', 'module' => 'Budget', 'description' => 'Create sub-activities'],
            ['name' => 'subactivities.edit', 'module' => 'Budget', 'description' => 'Edit sub-activities'],
            ['name' => 'subactivities.delete', 'module' => 'Budget', 'description' => 'Delete sub-activities'],
            ['name' => 'subactivity.edit', 'module' => 'Budget', 'description' => 'Edit sub-activity (legacy)'],
            ['name' => 'subactivity.delete', 'module' => 'Budget', 'description' => 'Delete sub-activity (legacy)'],
            ['name' => 'program.report', 'module' => 'Budget', 'description' => 'View program reports'],
            ['name' => 'project.report', 'module' => 'Budget', 'description' => 'View project reports'],
            ['name' => 'activity.report', 'module' => 'Budget', 'description' => 'View activity reports'],

            // Evaluations
            ['name' => 'evaluations.manage', 'module' => 'evaluations', 'description' => 'Manage evaluations and assignments'],
            ['name' => 'evaluations.evaluate', 'module' => 'evaluations', 'description' => 'Evaluate submissions'],
            ['name' => 'evaluations.view_all', 'module' => 'evaluations', 'description' => 'View all evaluations and reports'],

            // Prescreening Reports
            ['name' => 'prescreening.reports.view_all', 'module' => 'prescreening', 'description' => 'Access all prescreening reports'],

            // Procurement
            ['name' => 'forms.manage', 'module' => 'procurement', 'description' => 'Manage procurement forms'],
            ['name' => 'forms.submit', 'module' => 'procurement', 'description' => 'Submit procurement forms for approval'],
            ['name' => 'forms.approve', 'module' => 'procurement', 'description' => 'Approve procurement forms'],
            ['name' => 'forms.reject', 'module' => 'procurement', 'description' => 'Reject procurement forms with reason'],
            ['name' => 'procurement.audit', 'module' => 'procurement', 'description' => 'View procurement audit logs'],

            // Vendors
            ['name' => 'vendor.manage', 'module' => 'vendor', 'description' => 'Manage vendor accounts and imports'],
            ['name' => 'vendor.requests.manage', 'module' => 'vendor', 'description' => 'View vendor clarification requests'],
            ['name' => 'vendor.requests.respond', 'module' => 'vendor', 'description' => 'Respond to vendor clarification requests'],
            ['name' => 'vendor.outreach.send', 'module' => 'vendor', 'description' => 'Send procurement notifications to vendor groups'],

            // Site Visits (Procurement)
            ['name' => 'site_visits.view', 'module' => 'procurement', 'description' => 'View site visits'],
            ['name' => 'site_visits.create', 'module' => 'procurement', 'description' => 'Create site visits'],
            ['name' => 'site_visits.observe', 'module' => 'procurement', 'description' => 'Add observations and upload evidence for site visits'],
            ['name' => 'site_visits.submit', 'module' => 'procurement', 'description' => 'Submit site visits for approval'],
            ['name' => 'site_visits.approve', 'module' => 'procurement', 'description' => 'Approve or reject site visits'],

            // System Audit
            ['name' => 'system.audit.view', 'module' => 'system', 'description' => 'View system activity audit logs'],

            // Treaties & Agreements
            ['name' => 'treaties.view', 'module' => 'AU Master Data', 'description' => 'View treaties and agreements'],
            ['name' => 'treaties.create', 'module' => 'AU Master Data', 'description' => 'Create treaties and agreements'],
            ['name' => 'treaties.edit', 'module' => 'AU Master Data', 'description' => 'Edit treaties and member-state status'],
            ['name' => 'treaties.delete', 'module' => 'AU Master Data', 'description' => 'Delete treaties and agreements'],

            // Member State Treaty Portal (strict RBAC)
            ['name' => 'member_state.treaties.view', 'module' => 'Member State Portal', 'description' => 'View own member-state treaties workspace'],
            ['name' => 'member_state.treaties.update', 'module' => 'Member State Portal', 'description' => 'Sign, ratify, and submit original treaty documents'],
            ['name' => 'member_state.treaties.documents.download', 'module' => 'Member State Portal', 'description' => 'Download own treaty status documents and treaty supporting documents'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'module' => $permission['module'],
                    'description' => $permission['description'],
                ]
            );
        }
    }
}
