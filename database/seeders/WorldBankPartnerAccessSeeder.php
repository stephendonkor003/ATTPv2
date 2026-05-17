<?php

namespace Database\Seeders;

use App\Models\Funder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WorldBankPartnerAccessSeeder extends Seeder
{
    public const EMAIL = 'partners@worldbank.org';
    public const PASSWORD = 'ChangeMe2026!';

    public function run(): void
    {
        $role = Role::where('name', 'Funding Partner')->first();

        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'World Bank Partner',
                'password' => Hash::make(self::PASSWORD),
                'user_type' => 'funding_partner',
                'role_id' => $role?->id,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'otp_verified_at' => now(),
                'is_disabled' => false,
                'disabled_at' => null,
                'disabled_until' => null,
                'disabled_reason' => null,
                'is_blacklisted' => false,
                'blacklisted_at' => null,
                'blacklisted_reason' => null,
            ]
        );

        $funder = Funder::where('name', 'World Bank')
            ->orWhere('name', 'World Bank Group')
            ->orWhere('contact_email', self::EMAIL)
            ->first();

        if (! $funder) {
            $funder = new Funder([
                'name' => 'World Bank',
                'type' => 'donor',
                'currency' => 'USD',
            ]);
        }

        $funder->fill([
            'name' => 'World Bank',
            'contact_person' => 'World Bank Partner',
            'contact_email' => self::EMAIL,
            'has_portal_access' => true,
            'user_id' => $user->id,
        ]);

        $funder->save();
    }
}
