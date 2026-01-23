<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingSearchGeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_radius_is_capped(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Listing::factory()->count(2)->create([
            'owner_id' => $landlord->id,
            'status' => 'active',
            'lat' => 44.8,
            'lng' => 20.5,
        ]);

        $response = $this->getJson('/api/v1/listings?centerLat=44.8&centerLng=20.5&radiusKm=999');

        $response->assertOk();
        $meta = $response->json('meta');
        $this->assertNotEmpty($meta);
    }

    public function test_map_mode_returns_lightweight_payload(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'status' => 'active',
            'lat' => 44.8,
            'lng' => 20.5,
            'title' => 'Map Pin',
            'price_per_night' => 120,
            'cover_image' => 'http://example.com/img.jpg',
            'city' => 'Belgrade',
        ]);

        $response = $this->getJson('/api/v1/listings?mapMode=1&centerLat=44.8&centerLng=20.5&radiusKm=5');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($listing->id, $data[0]['id']);
        $this->assertArrayHasKey('lat', $data[0]);
        $this->assertArrayNotHasKey('description', $data[0]);
    }
}
