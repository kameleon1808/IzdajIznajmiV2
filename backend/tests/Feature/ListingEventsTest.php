<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\User;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_event_created_for_seeker(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);
        $seeker = User::factory()->create(['role' => 'seeker']);

        $this->actingAs($seeker)
            ->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk();

        $this->assertEquals(1, ListingEvent::where('user_id', $seeker->id)
            ->where('listing_id', $listing->id)
            ->where('event_type', ListingEvent::TYPE_VIEW)
            ->count());
    }

    public function test_view_event_not_created_for_landlord_viewing_own_listing(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);

        $this->actingAs($landlord)
            ->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk();

        $this->assertEquals(0, ListingEvent::count());
    }

    public function test_view_event_dedupes_within_12_hours(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);
        $seeker = User::factory()->create(['role' => 'seeker']);

        $this->actingAs($seeker)
            ->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk();

        $this->actingAs($seeker)
            ->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk();

        $this->assertEquals(1, ListingEvent::where('user_id', $seeker->id)
            ->where('listing_id', $listing->id)
            ->where('event_type', ListingEvent::TYPE_VIEW)
            ->count());
    }
}
