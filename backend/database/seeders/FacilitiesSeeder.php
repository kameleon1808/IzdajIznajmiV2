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
            'Basement',
            'Garage',
            'Terrace',
            'Yard',
            'Internet',
            'Cable TV',
            'Phone',
            'Air conditioning',
            'Elevator',
        ];

        foreach ($items as $name) {
            Facility::firstOrCreate(['name' => $name]);
        }
    }
}
