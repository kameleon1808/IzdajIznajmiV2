<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchListingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_only_returns_active_listings(): void
    {
        config(['search.driver' => 'sql']);

        $active = Listing::factory()->create(['status' => ListingStatusService::STATUS_ACTIVE]);
        Listing::factory()->create(['status' => ListingStatusService::STATUS_DRAFT]);

        $response = $this->getJson('/api/v1/search/listings');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_facets_keys_present_even_when_empty(): void
    {
        config(['search.driver' => 'sql']);

        $response = $this->getJson('/api/v1/search/listings');

        $response->assertOk();
        $facets = $response->json('facets');
        $this->assertIsArray($facets);
        $this->assertArrayHasKey('city', $facets);
        $this->assertArrayHasKey('status', $facets);
        $this->assertArrayHasKey('rooms', $facets);
        $this->assertArrayHasKey('amenities', $facets);
        $this->assertArrayHasKey('price_bucket', $facets);
        $this->assertArrayHasKey('area_bucket', $facets);
    }
}
