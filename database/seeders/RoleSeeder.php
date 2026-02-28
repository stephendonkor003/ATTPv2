<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

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
            'Communications Officer',
            'Member State Focal Point',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
