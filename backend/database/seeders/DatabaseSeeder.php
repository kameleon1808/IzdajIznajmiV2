<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\BookingRequestsSeeder;
use Database\Seeders\ConversationsSeeder;
use Database\Seeders\FacilitiesSeeder;
use Database\Seeders\ListingsSeeder;
use Database\Seeders\RolesUsersSeeder;

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
            BookingRequestsSeeder::class,
            ConversationsSeeder::class,
        ]);
    }
}
