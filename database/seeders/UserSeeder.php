<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\GovernanceNode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::pluck('id', 'name');
        $nodes = GovernanceNode::pluck('id', 'name');

        $users = [
            [
                'name' => 'System Admin',
                'email' => 'amodonlimited@gmail.com',
                'role' => 'System Admin',
                'user_type' => 'admin',
                'node' => 'African Union Commission',
            ],
            [
                'name' => 'Prescreening Evaluator',
                'email' => 'prescreener@example.com',
                'role' => 'Prescreening Evaluator',
                'user_type' => 'staff',
                'node' => 'Directorate of Governance',
            ],
            [
                'name' => 'Evaluation Evaluator',
                'email' => 'evaluator@example.com',
                'role' => 'Evaluation Evaluator',
                'user_type' => 'staff',
                'node' => 'Directorate of Elections',
            ],
            [
                'name' => 'HR Manager',
                'email' => 'hr.manager@example.com',
                'role' => 'HR Manager',
                'user_type' => 'staff',
                'node' => 'African Union Commission',
            ],
            [
                'name' => 'HR Officer',
                'email' => 'hr.officer@example.com',
                'role' => 'HR Officer',
                'user_type' => 'staff',
                'node' => 'African Union Commission',
            ],
            [
                'name' => 'Finance Manager',
                'email' => 'finance.manager@example.com',
                'role' => 'Finance Manager',
                'user_type' => 'staff',
                'node' => 'Department of Trade',
            ],
            [
                'name' => 'Finance Officer',
                'email' => 'finance.officer@example.com',
                'role' => 'Finance Officer',
                'user_type' => 'staff',
                'node' => 'Department of Trade',
            ],
            [
                'name' => 'Budget Officer',
                'email' => 'budget.officer@example.com',
                'role' => 'Budget Officer',
                'user_type' => 'staff',
                'node' => 'Department of Health',
            ],
            [
                'name' => 'Auditor',
                'email' => 'auditor@example.com',
                'role' => 'Auditor',
                'user_type' => 'staff',
                'node' => 'African Union Commission',
            ],
        ];

        foreach ($users as $user) {
            $createdUser = User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'user_type' => $user['user_type'],
                    'must_change_password' => true,
                    'role_id' => $roles[$user['role']] ?? null,
                    'governance_node_id' => $nodes[$user['node']] ?? null,
                ]
            );

            if ($user['role'] === 'System Admin') {
                $createdUser->permissions()->sync(Permission::pluck('id'));
            }

            if ($user['role'] === 'Evaluation Evaluator') {
                $createdUser->permissions()->syncWithoutDetaching(
                    Permission::whereIn('name', [
                        'evaluations.evaluate',
                    ])->pluck('id')
                );
            }
        }
    }
}