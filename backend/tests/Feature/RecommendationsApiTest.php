<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['search.driver' => 'sql']);
    }

    public function test_recommendations_requires_seeker(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->getJson('/api/v1/recommendations')
            ->assertForbidden();
    }

    public function test_recommendations_returns_active_listings_from_signals(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);

        $facilityWifi = Facility::create(['name' => 'Wifi']);

        $viewed = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'city' => 'Split',
            'price_per_month' => 120,
            'rooms' => 2,
            'area' => 55,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);
        $viewed->facilities()->attach([$facilityWifi->id]);

        $similar = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'city' => 'Split',
            'price_per_month' => 125,
            'rooms' => 2,
            'area' => 58,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);
        $similar->facilities()->attach([$facilityWifi->id]);

        ListingEvent::create([
            'user_id' => $seeker->id,
            'listing_id' => $viewed->id,
            'event_type' => ListingEvent::TYPE_VIEW,
        ]);

        SavedSearch::create([
            'user_id' => $seeker->id,
            'name' => 'Split stays',
            'filters' => ['city' => 'Split'],
            'alerts_enabled' => true,
            'frequency' => 'instant',
        ]);

        $response = $this->actingAs($seeker)
            ->getJson('/api/v1/recommendations?perPage=10');

        $response->assertOk();

        $data = $response->json('data') ?? [];
        $ids = collect($data)->pluck('id');
        $this->assertTrue($ids->contains($similar->id));
        foreach ($data as $item) {
            $this->assertEquals('active', $item['status']);
        }
    }

    public function test_recommendations_handles_price_window_with_decimal_bounds(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);

        $viewed = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'city' => 'Beograd',
            'price_per_month' => 115,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);

        $similar = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'city' => 'Novi Sad',
            'price_per_month' => 120,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);

        ListingEvent::create([
            'user_id' => $seeker->id,
            'listing_id' => $viewed->id,
            'event_type' => ListingEvent::TYPE_VIEW,
        ]);

        $response = $this->actingAs($seeker)
            ->getJson('/api/v1/recommendations?perPage=10');

        $response->assertOk();
        $ids = collect($response->json('data') ?? [])->pluck('id');
        $this->assertTrue($ids->contains($similar->id));
    }
}
