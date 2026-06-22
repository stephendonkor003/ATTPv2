<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PartnerPortalPermissionsSeeder extends Seeder
{
    /**
     * Seed the partner portal permissions and role.
     */
    public function run(): void
    {
        // Create partner portal permissions
        $permissions = [
            // Partner Portal Access
            [
                'name' => 'partner.dashboard.access',
                'module' => 'partner_portal',
                'description' => 'Access partner portal dashboard',
            ],
            [
                'name' => 'partner.programs.view',
                'module' => 'partner_portal',
                'description' => 'View funded programs',
            ],
            [
                'name' => 'partner.projects.view',
                'module' => 'partner_portal',
                'description' => 'View projects under funded programs',
            ],
            [
                'name' => 'partner.budgets.view',
                'module' => 'partner_portal',
                'description' => 'View budget commitments and expenditures',
            ],
            [
                'name' => 'partner.documents.view',
                'module' => 'partner_portal',
                'description' => 'View and download program documents',
            ],
            [
                'name' => 'partner.requests.create',
                'module' => 'partner_portal',
                'description' => 'Create information requests',
            ],
            [
                'name' => 'partner.requests.view',
                'module' => 'partner_portal',
                'description' => 'View own information requests',
            ],
            [
                'name' => 'partner.profile.edit',
                'module' => 'partner_portal',
                'description' => 'Edit own profile and change password',
            ],
            [
                'name' => 'partner.workplan.review',
                'module' => 'partner_portal',
                'description' => 'Review funded work plan items from the partner portal',
            ],

            // Admin side - manage partner requests
            [
                'name' => 'partner.requests.manage',
                'module' => 'finance',
                'description' => 'Manage all partner information requests',
            ],
            [
                'name' => 'partner.requests.respond',
                'module' => 'finance',
                'description' => 'Respond to partner information requests',
            ],
        ];

        // Create or update permissions
        $createdPermissions = collect();
        foreach ($permissions as $permissionData) {
            $permission = Permission::updateOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
            $createdPermissions->push($permission);
        }

        $this->command->info('✅ Created ' . count($permissions) . ' partner portal permissions');

        // Create Funding Partner role
        $partnerRole = Role::updateOrCreate(
            ['name' => 'Funding Partner'],
            [
                'name' => 'Funding Partner',
                'description' => 'External funding partner with read-only portal access to funded programs',
            ]
        );

        // Assign read-only partner permissions to Funding Partner role by default.
        $partnerPermissions = $createdPermissions->filter(function ($permission) {
            return in_array($permission->name, [
                'partner.dashboard.access',
                'partner.programs.view',
                'partner.projects.view',
                'partner.budgets.view',
                'partner.documents.view',
                'partner.requests.view',
            ], true);
        });

        $partnerRole->permissions()->sync($partnerPermissions->pluck('id'));

        $this->command->info('✅ Created "Funding Partner" role with ' . $partnerPermissions->count() . ' permissions');

        // Optionally add admin permissions to System Admin role
        $adminRole = Role::where('name', 'System Admin')->first();
        if ($adminRole) {
            $adminPermissions = $createdPermissions->filter(function ($permission) {
                return in_array($permission->name, ['partner.requests.manage', 'partner.requests.respond']);
            });

            $existingPermissions = $adminRole->permissions()->pluck('permissions.id');
            $newPermissions = $existingPermissions->merge($adminPermissions->pluck('id'))->unique();

            $adminRole->permissions()->sync($newPermissions);

            $this->command->info('✅ Added partner request management permissions to System Admin role');
        }

        $this->command->info('✨ Partner portal permissions seeding completed successfully!');
    }
}
