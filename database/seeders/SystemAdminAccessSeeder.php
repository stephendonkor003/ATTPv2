<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemAdminAccessSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'System Admin'],
            ['description' => 'Full system administrator']
        );

        $permissionIds = Permission::pluck('id')->all();
        $role->permissions()->sync($permissionIds);

        User::where('user_type', 'admin')
            ->whereNull('role_id')
            ->update(['role_id' => $role->id]);

        User::where('user_type', 'admin')
            ->get()
            ->each(fn (User $admin) => $admin->permissions()->syncWithoutDetaching($permissionIds));
    }
}
