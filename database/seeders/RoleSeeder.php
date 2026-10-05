<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'System Admin',
            'HR Manager',
            'HR Officer',
            'Finance Manager',
            'Finance Officer',
            'Budget Officer',
            'Auditor',
            'Prescreening Evaluator',
            'Evaluation Evaluator',
            'Portfolio Manager',
            'Portfolio Coordinator',
            'Monitoring and Evaluation Manager',
            'Communication Officer',
            'Communications Officer',
            'Member State Focal Point',
            'API Sync Administrator',
        ];

        foreach ($roles as $role) {
            $attributes = [];

            if ($role === Role::AUDITOR_NAME) {
                $attributes = [
                    'description' => 'System-wide read-only audit access. Business data and workflow actions cannot be changed.',
                    'is_read_only_auditor' => true,
                ];
            }

            Role::updateOrCreate(['name' => $role], $attributes);
        }
    }
}
