<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApplicationsSeeder extends Seeder
{
    public function run(): void
    {
        Application::truncate();

        $seeker = User::where('role', 'seeker')->first();
        $listing = Listing::where('status', 'active')->first();

        if (!$seeker || !$listing) {
            return;
        }

        Application::create([
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $listing->owner_id,
            'message' => 'I love this place. Is it available next week?',
            'status' => Application::STATUS_SUBMITTED,
        ]);
    }
}
