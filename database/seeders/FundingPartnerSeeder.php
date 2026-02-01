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
                'name' => 'World Bank Group',
                'email' => 'partners@worldbank.org',
                'contact' => 'Maria Kirtley',
                'phone' => '+251911000001',
            ],
            [
                'name' => 'African Development Bank (AfDB)',
                'email' => 'afdb@afdb.org',
                'contact' => 'Mamadou Diallo',
                'phone' => '+251911000002',
            ],
            [
                'name' => 'African Export-Import Bank (Afreximbank)',
                'email' => 'info@afreximbank.org',
                'contact' => 'Chinwe Okenwa',
                'phone' => '+251911000003',
            ],
            [
                'name' => 'Islamic Development Bank',
                'email' => 'project@isdb.org',
                'contact' => 'Nadia Suleiman',
                'phone' => '+251911000004',
            ],
            [
                'name' => 'African Union Development Agency (AUDA-NEPAD)',
                'email' => 'programs@auda.org',
                'contact' => 'Samuel Tadesse',
                'phone' => '+251911000005',
            ],
            [
                'name' => 'European Investment Bank (EIB)',
                'email' => 'africapartners@eib.org',
                'contact' => 'Elena Rossi',
                'phone' => '+251911000006',
            ],
            [
                'name' => 'Export-Import Bank of India',
                'email' => 'india@eximbankindia.in',
                'contact' => 'Rajat Mehta',
                'phone' => '+251911000007',
            ],
            [
                'name' => 'African Export-Import Bank (Roaring Lion Fund)',
                'email' => 'lionfund@afreximbank.org',
                'contact' => 'Helena Okonkwo',
                'phone' => '+251911000008',
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
