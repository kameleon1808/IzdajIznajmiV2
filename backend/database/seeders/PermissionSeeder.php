<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

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
