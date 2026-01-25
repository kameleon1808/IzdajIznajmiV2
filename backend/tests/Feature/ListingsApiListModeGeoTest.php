<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingsApiListModeGeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_view_ignores_geo_radius(): void
    {
        $near = Listing::factory()->create([
            'lat' => 45.05,
            'lng' => 15.05,
            'status' => 'active',
        ]);
        $far = Listing::factory()->create([
            'lat' => 46.2,
            'lng' => 15.0,
            'status' => 'active',
        ]);

        // mapMode not set → list mode; geo filter should be ignored, both appear
        $response = $this->getJson('/api/v1/listings?centerLat=45&centerLng=15&radiusKm=50');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($ids->contains($near->id));
        $this->assertTrue($ids->contains($far->id));
    }
}
