<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilitiesSeeder extends Seeder
{
    public function run(): void
    {
        Facility::truncate();

        $items = [
            'Pool', 'Wi-Fi', 'Breakfast', 'Parking', 'Spa', 'Gym', 'Kitchen', 'Ocean View', 'Mountain View', 'Workspace', 'Bike Rental', 'Bar',
        ];

        foreach ($items as $name) {
            Facility::firstOrCreate(['name' => $name]);
        }
    }
}
