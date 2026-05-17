<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MasterAdminSeeder extends Seeder
{
    public const EMAIL = 'stephendonkor03@outlok.com';
    public const ALTERNATE_EMAIL = 'stephendonkor03@outlook.com';
    public const PASSWORD = 'Amodon@3055';

    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'System Admin'],
            ['description' => 'Full system administrator']
        );

        $admins = collect([self::EMAIL, self::ALTERNATE_EMAIL])->map(function (string $email) use ($role) {
            return User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'Master Admin',
                    'password' => Hash::make(self::PASSWORD),
                    'user_type' => 'admin',
                    'role_id' => $role->id,
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
        });

        $role->permissions()->sync(Permission::pluck('id')->all());
        $admins->each(fn (User $admin) => $admin->permissions()->sync(Permission::pluck('id')->all()));
    }
}
