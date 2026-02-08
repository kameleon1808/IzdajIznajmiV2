<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'landlord', 'seeker'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $users = User::all();
        foreach ($users as $user) {
            $roleName = $user->role === 'tenant' ? 'seeker' : $user->role;
            if (in_array($roleName, $roles, true)) {
                $user->syncRoles([$roleName]);
            }
        }
    }
}
