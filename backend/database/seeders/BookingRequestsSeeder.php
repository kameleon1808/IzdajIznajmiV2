<?php

namespace Database\Seeders;

use App\Models\BookingRequest;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BookingRequestsSeeder extends Seeder
{
    public function run(): void
    {
        BookingRequest::truncate();

        $tenants = User::where('role', 'seeker')->pluck('id')->all();
        $listings = Listing::with('owner')->get();
        $statuses = ['pending', 'accepted', 'rejected', 'cancelled'];

        if (count($tenants) === 0) {
            return;
        }

        foreach ($listings->take(20) as $index => $listing) {
            $tenantId = $tenants[$index % count($tenants)];
            $start = Carbon::now()->addDays($index + 1)->toDateString();
            $end = Carbon::parse($start)->addMonthNoOverflow()->toDateString();

            BookingRequest::create([
                'listing_id' => $listing->id,
                'tenant_id' => $tenantId,
                'landlord_id' => $listing->owner_id,
                'start_date' => $start,
                'end_date' => $end,
                'guests' => rand(1, 5),
                'message' => 'Requesting stay with flexible check-in.',
                'status' => $statuses[$index % count($statuses)],
            ]);
        }
    }
}
