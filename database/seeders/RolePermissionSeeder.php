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
        $admin->permissions()->sync(Permission::pluck('id'));

        // HR Manager
        Role::where('name', 'HR Manager')->first()
            ->permissions()->sync(
                Permission::where('module', 'HR')->pluck('id')
            );

        // HR Officer
        Role::where('name', 'HR Officer')->first()
            ->permissions()->sync(
                Permission::whereIn('name', [
                    'hr.access',
                    'hr.positions.view',
                    'hr.vacancies.view',
                    'hr.applicants.view',
                    'hr.applicants.manage',
                    'hr.ai.score',
                ])->pluck('id')
            );

        // Finance Manager
        Role::where('name', 'Finance Manager')->first()
            ->permissions()->sync(
                Permission::where('module', 'Finance')->pluck('id')
            );

        // Finance Officer
        Role::where('name', 'Finance Officer')->first()
            ->permissions()->sync(
                Permission::whereIn('name', [
                    'finance.access',
                    'finance.commitments.manage',
                    'finance.executions.view',
                ])->pluck('id')
            );

        // Budget Officer
        Role::where('name', 'Budget Officer')->first()
            ->permissions()->sync(
                Permission::whereIn('name', [
                    'budget.access',
                    'budget.structure.manage',
                    'budget.activities.manage',
                    'budget.allocations.manage',
                    'budget.reports.view',
                ])->pluck('id')
            );

        // Auditor
        Role::where('name', 'Auditor')->first()
            ->permissions()->sync(
                Permission::whereIn('name', [
                    'finance.access',
                    'finance.executions.view',
                    'budget.access',
                    'budget.reports.view',
                    'budget.summary.view',
                    'hr.analytics.view',
                ])->pluck('id')
            );

        // Prescreening Evaluator
        Role::where('name', 'Prescreening Evaluator')->first()
            ->permissions()->sync(
                Permission::whereIn('name', [
                    'prescreening.access',
                    'prescreening.evaluate',
                ])->pluck('id')
            );

        // Evaluation Evaluator
        Role::where('name', 'Evaluation Evaluator')->first()
            ->permissions()->sync(
                Permission::whereIn('name', [
                    'evaluations.evaluate',
                ])->pluck('id')
            );

        // Member State Focal Point
        Role::where('name', 'Member State Focal Point')->first()
            ->permissions()->sync(
                Permission::whereIn('name', [
                    'member_state.treaties.view',
                    'member_state.treaties.update',
                    'member_state.treaties.documents.download',
                ])->pluck('id')
            );
    }
}
