<?php
namespace Database\Seeders;

use App\Models\Funder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FundingPartnerSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', 'Funding Partner')->first();

        if (!$role) {
            $this->command->warn('Funding Partner role is missing; run PartnerPortalPermissionsSeeder first.');
            return;
        }

        $partners = [
            [
                'name' => 'African Union Funding Partner',
                'email' => 'donkors@africanunion.org',
                'contact' => 'Don Kors',
                'phone' => '+251000000001',
            ],
            [
                'name' => 'Pan-African Infrastructure Bank',
                'email' => 'infrapartners@africanunion.org',
                'contact' => 'Maya Kinte',
                'phone' => '+251000000002',
            ],
            [
                'name' => 'Continental Climate Resilience Fund',
                'email' => 'climate@africanunion.org',
                'contact' => 'Rasheed Omar',
                'phone' => '+251000000003',
            ],
            [
                'name' => 'East Africa Innovation Fund',
                'email' => 'eastinnovation@africanunion.org',
                'contact' => 'Lena Wanjiru',
                'phone' => '+251000000004',
            ],
            [
                'name' => 'Southern Africa Digital Transformation Fund',
                'email' => 'digital@africanunion.org',
                'contact' => 'Thabo Masego',
                'phone' => '+251000000005',
            ],
            [
                'name' => 'West Africa Energy Access Partner',
                'email' => 'energy@africanunion.org',
                'contact' => 'Fatou Diallo',
                'phone' => '+251000000006',
            ],
            [
                'name' => 'Central Africa Health & Resilience Partner',
                'email' => 'health@africanunion.org',
                'contact' => 'Amina Essien',
                'phone' => '+251000000007',
            ],
        ];

        foreach ($partners as $partner) {
            $user = User::updateOrCreate(
                ['email' => $partner['email']],
                [
                    'name' => $partner['contact'],
                    'password' => Hash::make('ChangeMe2026!'),
                    'user_type' => 'funding_partner',
                    'must_change_password' => true,
                    'role_id' => $role->id,
                ]
            );

            Funder::updateOrCreate(
                ['contact_email' => $partner['email']],
                [
                    'name' => $partner['name'],
                    'type' => 'donor',
                    'currency' => 'USD',
                    'has_portal_access' => true,
                    'user_id' => $user->id,
                    'contact_person' => $partner['contact'],
                    'contact_email' => $partner['email'],
                    'contact_phone' => $partner['phone'],
                    'notes' => 'Seeded funding partner with portal access.',
                ]
            );
        }
    }
}
