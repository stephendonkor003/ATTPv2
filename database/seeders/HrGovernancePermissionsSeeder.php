<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class HrGovernancePermissionsSeeder extends Seeder
{
    /**
     * Add HR-specific permissions including the special all-node visibility permission.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'hr.view_all_nodes', 'description' => 'View HR data from all governance nodes'],
            ['name' => 'hrm.positions.view', 'description' => 'View HR positions'],
            ['name' => 'hrm.positions.create', 'description' => 'Create HR positions'],
            ['name' => 'hrm.positions.edit', 'description' => 'Edit HR positions'],
            ['name' => 'hrm.positions.delete', 'description' => 'Delete HR positions'],
            ['name' => 'hrm.vacancies.view', 'description' => 'View HR vacancies'],
            ['name' => 'hrm.vacancies.create', 'description' => 'Create HR vacancies'],
            ['name' => 'hrm.vacancies.submit', 'description' => 'Submit HR vacancies for approval'],
            ['name' => 'hr.vacancies.approve', 'description' => 'Approve HR vacancies'],
            ['name' => 'hr.vacancies.publish', 'description' => 'Publish HR vacancies'],
            ['name' => 'hr.applicants.view', 'description' => 'View HR applicants'],
            ['name' => 'hr.applicants.manage', 'description' => 'Manage HR applicants'],
            ['name' => 'hr.applicants.hire', 'description' => 'Hire HR applicants'],
            ['name' => 'hr.ai.score', 'description' => 'Use AI scoring for applicants'],
            ['name' => 'hr.employees.view', 'description' => 'View HR employees'],
            ['name' => 'hr.employees.manage', 'description' => 'Manage HR employees'],
            ['name' => 'hr.analytics.view', 'description' => 'View HR analytics dashboard'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'module' => 'Human Resource',
                    'description' => $permission['description'],
                ]
            );
        }

        $allHrPermissionIds = Permission::whereIn('name', collect($permissions)->pluck('name'))
            ->pluck('id')
            ->all();

        $hrAdmin = Role::firstOrCreate(
            ['name' => 'HR Admin'],
            ['description' => 'Human resource administrator with all HR permissions']
        );
        $hrAdmin->permissions()->sync($allHrPermissionIds);

        Role::whereIn('name', ['System Admin', 'super-admin'])->get()->each(function (Role $role) use ($allHrPermissionIds) {
            $role->permissions()->syncWithoutDetaching($allHrPermissionIds);
        });

        $hrManagerPermissionIds = Permission::whereIn('name', [
            'hrm.positions.view',
            'hrm.positions.create',
            'hrm.vacancies.view',
            'hrm.vacancies.create',
            'hrm.vacancies.submit',
            'hr.applicants.view',
            'hr.applicants.manage',
            'hr.ai.score',
            'hr.employees.view',
            'hr.analytics.view',
        ])->pluck('id')->all();

        $hrManager = Role::firstOrCreate(
            ['name' => 'HR Manager'],
            ['description' => 'Human resource manager']
        );
        $hrManager->permissions()->syncWithoutDetaching($hrManagerPermissionIds);

        $this->command?->info('HR governance permissions seeded successfully.');
    }
}
