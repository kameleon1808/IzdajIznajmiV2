<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesUsersSeeder extends Seeder
{
    public function run(): void
    {
        User::truncate();

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        foreach ([
            ['name' => 'Lana Landlord', 'email' => 'lana@demo.com'],
            ['name' => 'Leo Landlord', 'email' => 'leo@demo.com'],
        ] as $landlord) {
            User::create([
                'name' => $landlord['name'],
                'email' => $landlord['email'],
                'role' => 'landlord',
                'password' => Hash::make('password'),
            ]);
        }

        foreach ([
            ['name' => 'Tena Tenant', 'email' => 'tena@demo.com'],
            ['name' => 'Tomas Tenant', 'email' => 'tomas@demo.com'],
            ['name' => 'Tara Tenant', 'email' => 'tara@demo.com'],
        ] as $tenant) {
            User::create([
                'name' => $tenant['name'],
                'email' => $tenant['email'],
                'role' => 'seeker',
                'password' => Hash::make('password'),
            ]);
        }
    }
}
