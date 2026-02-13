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
            'email' => 'admin@gmail.com',
            'role' => 'admin',
            'phone' => '+385991000001',
            'password' => Hash::make('password'),
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $isFirst = $i === 1;
            User::create([
                'name' => "Landlord {$i}",
                'full_name' => "Landlord {$i}",
                'email' => "stanodavac{$i}@gmail.com",
                'role' => 'landlord',
                'phone' => '+385991000'.str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT),
                'email_verified_at' => $isFirst ? now() : null,
                'email_verified' => $isFirst,
                'phone_verified' => $isFirst,
                'address_verified' => $isFirst,
                'verification_status' => $isFirst ? 'approved' : 'none',
                'verified_at' => $isFirst ? now() : null,
                'verification_notes' => null,
                'password' => Hash::make('password'),
            ]);
        }

        for ($i = 1; $i <= 10; $i++) {
            $isFirst = $i === 1;
            User::create([
                'name' => "Seeker {$i}",
                'full_name' => "Seeker {$i}",
                'email' => $isFirst ? 'trazilac1@gmail.com' : "trazilac{$i}@gmail.com",
                'role' => 'seeker',
                'phone' => '+385991001'.str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT),
                'email_verified_at' => $isFirst ? now() : null,
                'email_verified' => $isFirst,
                'phone_verified' => $isFirst,
                'address_verified' => $isFirst,
                'verification_status' => $isFirst ? 'approved' : 'none',
                'verified_at' => $isFirst ? now() : null,
                'verification_notes' => null,
                'password' => Hash::make('password'),
            ]);
        }
    }
}
