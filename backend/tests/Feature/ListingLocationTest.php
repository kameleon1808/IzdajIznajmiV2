<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_set_manual_coordinates(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'location_source' => 'geocoded',
            'geocoded_at' => now(),
        ]);

        $response = $this->actingAs($landlord)->patchJson(
            "/api/v1/listings/{$listing->id}/location",
            ['latitude' => 12.3456, 'longitude' => 45.6789]
        );

        $response->assertOk()->assertJsonPath('data.locationSource', 'manual');

        $fresh = $listing->fresh();
        $this->assertEquals(12.3456, $fresh->lat);
        $this->assertEquals(45.6789, $fresh->lng);
        $this->assertEquals('manual', $fresh->location_source);
        $this->assertNull($fresh->geocoded_at);
        $this->assertNotNull($fresh->location_overridden_at);
    }

    public function test_non_owner_cannot_set_manual_coordinates(): void
    {
        $owner = User::factory()->create(['role' => 'landlord']);
        $other = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create([
            'owner_id' => $owner->id,
            'location_source' => 'geocoded',
        ]);

        $this->actingAs($other)
            ->patchJson("/api/v1/listings/{$listing->id}/location", ['latitude' => 1, 'longitude' => 2])
            ->assertStatus(403);
    }

    public function test_admin_can_set_manual_coordinates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = Listing::factory()->create(['location_source' => 'geocoded']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/listings/{$listing->id}/location", ['latitude' => 9.1, 'longitude' => 8.2])
            ->assertOk()
            ->assertJsonPath('data.locationSource', 'manual');

        $fresh = $listing->fresh();
        $this->assertEquals('manual', $fresh->location_source);
    }

    public function test_manual_location_is_preserved_until_reset(): void
    {
        config(['geocoding.driver' => 'fake']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'lat' => 1.2345678,
            'lng' => 2.3456789,
            'geocoded_at' => now(),
            'location_source' => 'geocoded',
        ]);

        $this->actingAs($landlord)
            ->patchJson("/api/v1/listings/{$listing->id}/location", ['latitude' => 10.0, 'longitude' => 20.0])
            ->assertOk();

        $listing->refresh();
        $this->assertEquals('manual', $listing->location_source);

        $this->actingAs($landlord)
            ->putJson("/api/v1/landlord/listings/{$listing->id}", ['title' => 'Updated title'])
            ->assertOk();

        $listing->refresh();
        $this->assertEquals(10.0, $listing->lat);
        $this->assertEquals(20.0, $listing->lng);
        $this->assertEquals('manual', $listing->location_source);
        $this->assertNull($listing->geocoded_at);

        $this->actingAs($landlord)
            ->postJson("/api/v1/listings/{$listing->id}/location/reset")
            ->assertOk();

        $listing->refresh();
        $this->assertEquals('geocoded', $listing->location_source);
        $this->assertNotNull($listing->geocoded_at);
        $this->assertNotEquals(10.0, round((float) $listing->lat, 1));
        $this->assertNotEquals(20.0, round((float) $listing->lng, 1));
    }
}
