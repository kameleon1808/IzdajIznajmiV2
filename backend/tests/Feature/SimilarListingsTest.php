<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Listing;
use App\Models\User;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimilarListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_similar_listings_excludes_self_and_inactive_and_orders_by_score(): void
    {
        $facilityWifi = Facility::create(['name' => 'Wifi']);
        $facilityPool = Facility::create(['name' => 'Pool']);

        $landlord = User::factory()->create(['role' => 'landlord']);
        $base = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'city' => 'Split',
            'price_per_night' => 100,
            'rooms' => 2,
            'area' => 50,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);
        $base->facilities()->attach([$facilityWifi->id, $facilityPool->id]);

        $verifiedLandlord = User::factory()->create([
            'role' => 'landlord',
            'landlord_verification_status' => 'approved',
        ]);
        $candidateA = Listing::factory()->create([
            'owner_id' => $verifiedLandlord->id,
            'city' => 'Split',
            'price_per_night' => 105,
            'rooms' => 2,
            'area' => 52,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);
        $candidateA->facilities()->attach([$facilityWifi->id, $facilityPool->id]);

        $candidateB = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'city' => 'Split',
            'price_per_night' => 200,
            'rooms' => 4,
            'area' => 80,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);
        $candidateB->facilities()->attach([$facilityWifi->id]);

        $inactive = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'city' => 'Split',
            'price_per_night' => 90,
            'rooms' => 2,
            'area' => 49,
            'status' => ListingStatusService::STATUS_DRAFT,
        ]);

        $response = $this->getJson('/api/v1/listings/'.$base->id.'/similar?limit=3');

        $response->assertOk();
        $response->assertJsonMissing(['id' => $base->id]);
        $response->assertJsonMissing(['id' => $inactive->id]);
        $response->assertJsonPath('data.0.id', $candidateA->id);
    }
}
