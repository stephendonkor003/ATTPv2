<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ChirwaSuperAdminSeeder extends Seeder
{
    public const EMAIL = 'ChirwaT@AfricanUnion.org';
    public const PASSWORD = 'Chirwa@AU2026!';

    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'System Admin'],
            ['description' => 'Full system administrator']
        );

        $admin = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Themba Chirwa',
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

        $permissionIds = Permission::pluck('id')->all();

        $role->permissions()->sync($permissionIds);
        $admin->permissions()->sync($permissionIds);
    }
}
