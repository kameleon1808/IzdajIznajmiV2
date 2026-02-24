<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesUsersSeeder::class,
            PermissionSeeder::class,
            FacilitiesSeeder::class,
            ListingsSeeder::class,
            ApplicationsSeeder::class,
            BookingRequestsSeeder::class,
            ConversationsSeeder::class,
            LandlordMetricsSeeder::class,
            RatingsSeeder::class,
        ]);
    }
}
