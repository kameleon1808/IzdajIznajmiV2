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
            'full_name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'phone' => '+385991000001',
            'password' => Hash::make('password'),
        ]);

        foreach ([
            ['name' => 'Lana Landlord', 'email' => 'lana@demo.com', 'phone' => '+385991000002'],
            ['name' => 'Leo Landlord', 'email' => 'leo@demo.com', 'phone' => '+385991000003'],
        ] as $landlord) {
            User::create([
                'name' => $landlord['name'],
                'full_name' => $landlord['name'],
                'email' => $landlord['email'],
                'role' => 'landlord',
                'phone' => $landlord['phone'],
                'password' => Hash::make('password'),
            ]);
        }

        foreach ([
            ['name' => 'Tena Seeker', 'email' => 'tena@demo.com', 'phone' => '+385991000004'],
            ['name' => 'Tomas Seeker', 'email' => 'tomas@demo.com', 'phone' => '+385991000005'],
            ['name' => 'Tara Seeker', 'email' => 'tara@demo.com', 'phone' => '+385991000006'],
        ] as $tenant) {
            User::create([
                'name' => $tenant['name'],
                'full_name' => $tenant['name'],
                'email' => $tenant['email'],
                'role' => 'seeker',
                'phone' => $tenant['phone'],
                'password' => Hash::make('password'),
            ]);
        }
    }
}
