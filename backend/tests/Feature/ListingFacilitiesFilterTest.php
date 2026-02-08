<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingFacilitiesFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_is_not_returned_when_missing_requested_facilities(): void
    {
        $listing = Listing::factory()->create();
        $listing->facilities()->createMany([
            ['name' => 'Pool'],
            ['name' => 'Parking'],
            ['name' => 'Workspace'],
        ]);

        $response = $this->getJson('/api/v1/listings?'.http_build_query([
            'amenities' => ['Wi-Fi', 'Spa', 'Workspace', 'Bike Rental', 'Bar'],
        ]));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($listing->id), 'Listing lacking requested facilities should not be returned');
    }

    public function test_listing_is_returned_when_all_requested_facilities_present(): void
    {
        $listing = Listing::factory()->create();
        $listing->facilities()->createMany([
            ['name' => 'Pool'],
            ['name' => 'Parking'],
            ['name' => 'Workspace'],
            ['name' => 'Spa'],
            ['name' => 'Wi-Fi'],
        ]);

        $response = $this->getJson('/api/v1/listings?'.http_build_query([
            'amenities' => ['Wi-Fi', 'Spa', 'Workspace'],
        ]));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($listing->id), 'Listing with all requested facilities should be returned');
    }
}
