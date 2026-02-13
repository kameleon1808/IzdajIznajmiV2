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

        $faker = fake();
        $genders = ['muski', 'zenski'];
        $employmentStatuses = ['zaposlen', 'nezaposlen', 'student'];

        User::create([
            'name' => 'Admin User',
            'full_name' => 'Admin User',
            'date_of_birth' => $faker->dateTimeBetween('-60 years', '-30 years')->format('Y-m-d'),
            'gender' => 'muski',
            'residential_address' => $faker->streetAddress(),
            'employment_status' => 'zaposlen',
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
                'date_of_birth' => $faker->dateTimeBetween('-60 years', '-21 years')->format('Y-m-d'),
                'gender' => $faker->randomElement($genders),
                'residential_address' => $faker->streetAddress(),
                'employment_status' => $faker->randomElement($employmentStatuses),
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
                'date_of_birth' => $faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
                'gender' => $faker->randomElement($genders),
                'residential_address' => $faker->streetAddress(),
                'employment_status' => $faker->randomElement($employmentStatuses),
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
