<?php

namespace Database\Seeders;

use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\Funder;
use App\Models\ProgramFunding;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ConsortiumThinkTankMembershipSeeder extends Seeder
{
    public function run(): void
    {
        VendorCategory::firstOrCreate(
            ['name' => 'Think Tank Transfer'],
            [
                'description' => 'Think tank vendor accounts used for consortium fund transfer purchase orders and disbursements.',
                'is_active' => true,
            ]
        );

        $thinkTankRole = Role::where('name', 'Think Tank User')->first();
        $worldBank = Funder::where('name', 'World Bank')
            ->orWhere('contact_email', 'partners@worldbank.org')
            ->first();
        $worldBankFunding = $worldBank
            ? ProgramFunding::where('funder_id', $worldBank->id)
                ->where('status', 'approved')
                ->orderByDesc('approved_amount')
                ->first()
            : null;

        $consortia = [
            [
                'code' => 'RAISED-AFRICA',
                'name' => 'RAISED Africa',
                'members' => [
                    ['name' => 'Economic Research Forum', 'country' => 'Egypt'],
                    ['name' => 'Partnership for Economic Policy (PEP)', 'country' => 'Kenya'],
                    ['name' => 'Resource and Environmental Policy Research Centre (REPRC), Environment for Development (EfD) Nigeria', 'country' => 'Nigeria'],
                ],
            ],
            [
                'code' => 'CACEPS',
                'name' => 'CACEPS Consortium',
                'members' => [
                    ['name' => 'African Population and Health Research Center (APHRC)', 'country' => 'Kenya'],
                    ['name' => 'Centro de Integridade Publica', 'country' => 'Mozambique'],
                    ['name' => 'Centre for Population and Environmental Development (CPED)', 'country' => 'Nigeria'],
                    ['name' => 'The Egyptian Center for Economic Studies (ECES)', 'country' => 'Egypt'],
                    ['name' => 'Initiative Prospective Agricole et Rurale', 'country' => 'Senegal'],
                ],
            ],
            [
                'code' => 'BRIDGE-AFRICA',
                'name' => 'Bridge Africa Consortium',
                'members' => [
                    ['name' => 'African Center for Economic Transformation (ACET)', 'country' => 'Ghana'],
                    ['name' => 'African Institute for Development Policy (AFIDEP)', 'country' => 'Kenya'],
                    ['name' => 'Denis and Lenora Foretia Foundation (Nkafu Policy Institute)', 'country' => 'Cameroon'],
                    ['name' => 'Policy Center for the New South (PCNS)', 'country' => 'Morocco'],
                    ['name' => 'South Africa Institute of International Affairs (SAIIA)', 'country' => 'South Africa'],
                ],
            ],
        ];

        foreach ($consortia as $consortiumData) {
            $consortium = Consortium::updateOrCreate(
                ['code' => $consortiumData['code']],
                [
                    'name' => $consortiumData['name'],
                    'currency' => 'USD',
                    'status' => 'active',
                    'funder_id' => $worldBank?->id,
                    'program_funding_id' => $worldBankFunding?->id,
                    'approved_budget' => $worldBankFunding?->approved_amount ?? 0,
                    'notes' => 'Seeded ATTP think tank consortium membership.',
                ]
            );

            foreach ($consortiumData['members'] as $memberData) {
                $portalUser = User::updateOrCreate(
                    ['email' => $this->portalEmailFor($memberData['name'])],
                    [
                        'name' => $memberData['name'],
                        'password' => Hash::make('ThinkTank@2026!'),
                        'user_type' => 'think_tank',
                        'role_id' => $thinkTankRole?->id,
                        'must_change_password' => false,
                        'password_changed_at' => now(),
                        'otp_verified_at' => now(),
                        'is_disabled' => false,
                        'is_blacklisted' => false,
                    ]
                );

                $vendor = User::updateOrCreate(
                    ['email' => $this->vendorEmailFor($memberData['name'])],
                    [
                        'name' => $memberData['name'],
                        'password' => Hash::make('ThinkTank@2026!'),
                        'user_type' => 'vendor',
                        'vendor_category' => 'Think Tank Transfer',
                        'must_change_password' => true,
                        'is_disabled' => false,
                        'is_blacklisted' => false,
                    ]
                );

                ConsortiumThinkTank::updateOrCreate(
                    [
                        'consortium_id' => $consortium->id,
                        'name' => $memberData['name'],
                    ],
                    [
                        'country' => $memberData['country'],
                        'email' => $this->emailFor($memberData['name']),
                        'portal_user_id' => $portalUser->id,
                        'vendor_user_id' => $vendor->id,
                        'role' => 'member',
                        'status' => 'active',
                        'joined_at' => now()->toDateString(),
                    ]
                );
            }
        }
    }

    private function emailFor(string $name): string
    {
        return Str::slug(Str::limit($name, 48, ''), '.') . '@thinktank.attp.local';
    }

    private function portalEmailFor(string $name): string
    {
        return 'portal.' . Str::slug(Str::limit($name, 42, ''), '.') . '@thinktank.attp.local';
    }

    private function vendorEmailFor(string $name): string
    {
        return 'vendor.' . Str::slug(Str::limit($name, 42, ''), '.') . '@thinktank.attp.local';
    }
}
