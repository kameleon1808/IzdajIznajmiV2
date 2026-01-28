<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Listing;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSuggestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggest_returns_city_and_amenity_matches(): void
    {
        config(['search.driver' => 'sql']);

        $listing = Listing::factory()->create([
            'status' => ListingStatusService::STATUS_ACTIVE,
            'city' => 'Belgrade',
        ]);

        $facility = Facility::create(['name' => 'Wi-Fi']);
        $listing->facilities()->sync([$facility->id]);

        $cityResponse = $this->getJson('/api/v1/search/suggest?q=bel&limit=8');
        $cityResponse->assertOk();
        $citySuggestions = collect($cityResponse->json());
        $this->assertTrue(
            $citySuggestions->contains(fn ($item) => ($item['type'] ?? '') === 'city' && ($item['value'] ?? '') === 'Belgrade')
        );

        $amenityResponse = $this->getJson('/api/v1/search/suggest?q=wi&limit=8');
        $amenityResponse->assertOk();
        $amenitySuggestions = collect($amenityResponse->json());
        $this->assertTrue(
            $amenitySuggestions->contains(fn ($item) => ($item['type'] ?? '') === 'amenity' && ($item['value'] ?? '') === 'Wi-Fi')
        );
    }
}
