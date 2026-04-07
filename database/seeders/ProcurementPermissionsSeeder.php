<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class ProcurementPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Procurement Plans
            ['name' => 'procurement.plan.view', 'module' => 'Procurement Settings', 'description' => 'View own procurement plans'],
            ['name' => 'procurement.plan.create', 'module' => 'Procurement Settings', 'description' => 'Create procurement plans'],
            ['name' => 'procurement.plan.edit', 'module' => 'Procurement Settings', 'description' => 'Edit own procurement plans'],
            ['name' => 'procurement.plan.delete', 'module' => 'Procurement Settings', 'description' => 'Delete own procurement plans'],
            ['name' => 'procurement.view_all', 'module' => 'Procurement Settings', 'description' => 'View all procurement plans from all users'],
            ['name' => 'procurement.manage_all', 'module' => 'Procurement Settings', 'description' => 'Manage all procurement plans from all users'],

            // Procurement Settings Management
            ['name' => 'procurement.settings.manage', 'module' => 'Procurement Settings', 'description' => 'Manage all procurement settings'],
            ['name' => 'procurement.settings.geographics', 'module' => 'Procurement Settings', 'description' => 'Manage procurement geographics'],
            ['name' => 'procurement.settings.methods', 'module' => 'Procurement Settings', 'description' => 'Manage procurement methods planned'],
            ['name' => 'procurement.settings.stages', 'module' => 'Procurement Settings', 'description' => 'Manage procurement stages'],
            ['name' => 'procurement.settings.statuses', 'module' => 'Procurement Settings', 'description' => 'Manage procurement statuses'],
            ['name' => 'procurement.settings.step_stages', 'module' => 'Procurement Settings', 'description' => 'Manage procurement step stages'],
            ['name' => 'procurement.settings.step_approvals', 'module' => 'Procurement Settings', 'description' => 'Manage procurement step approvals'],
            ['name' => 'procurement.settings.import', 'module' => 'Procurement Settings', 'description' => 'Import procurement settings from Excel/CSV'],
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

        $this->command->info('Procurement permissions seeded successfully!');
    }
}
