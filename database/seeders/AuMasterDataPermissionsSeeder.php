<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class AuMasterDataPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates permissions for AU Master Data management.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'settings.au_master_data.view',
                'module' => 'AU Master Data',
                'description' => 'View AU Master Data settings (member states, regional blocks, aspirations, goals, flagship projects)',
            ],
            [
                'name' => 'settings.au_master_data.create',
                'module' => 'AU Master Data',
                'description' => 'Create new AU Master Data records',
            ],
            [
                'name' => 'settings.au_master_data.edit',
                'module' => 'AU Master Data',
                'description' => 'Edit AU Master Data records',
            ],
            [
                'name' => 'settings.au_master_data.delete',
                'module' => 'AU Master Data',
                'description' => 'Delete AU Master Data records',
            ],
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

        // Assign to Admin role if exists
        $adminRole = Role::whereIn('name', ['System Admin', 'Admin'])->first();
        if ($adminRole) {
            $permissionIds = Permission::where('name', 'like', 'settings.au_master_data.%')
                ->pluck('id')
                ->toArray();

            $adminRole->permissions()->syncWithoutDetaching($permissionIds);
        }

        $this->command->info('AU Master Data permissions created and assigned to Admin role.');
    }
}
