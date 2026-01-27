<?php

namespace Tests\Feature;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSearchesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_saved_search_stores_normalized_filters(): void
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'My Search',
            'filters' => [
                'category' => 'all',
                'status' => 'all',
                'priceMin' => '50',
                'priceMax' => '200',
                'amenities' => ['Wi-Fi', 'Pool', '', null, 'Pool'],
                'centerLat' => 44.8123456,
                'centerLng' => 20.4612345,
                'radiusKm' => 12.3456,
                'instantBook' => false,
                'mapMode' => true,
            ],
        ];

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/saved-searches', $payload);

        $response->assertCreated();

        $savedSearch = SavedSearch::firstOrFail();

        $this->assertSame([
            'amenities' => ['Pool', 'Wi-Fi'],
            'centerLat' => 44.81235,
            'centerLng' => 20.46123,
            'mapMode' => true,
            'priceMax' => 200,
            'priceMin' => 50,
            'radiusKm' => 12.35,
        ], $savedSearch->filters);
    }

    public function test_duplicate_saved_search_returns_conflict(): void
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'First',
            'filters' => [
                'category' => 'villa',
                'priceMin' => 80,
            ],
        ];

        $this->actingAs($user);
        $this->postJson('/api/v1/saved-searches', $payload)->assertCreated();

        $response = $this->postJson('/api/v1/saved-searches', [
            'name' => 'Duplicate',
            'filters' => [
                'priceMin' => 80,
                'category' => 'villa',
            ],
        ]);

        $response->assertStatus(409);
    }

    public function test_saved_search_ownership_is_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $savedSearch = SavedSearch::create([
            'user_id' => $owner->id,
            'name' => 'Owner Search',
            'filters' => ['category' => 'villa'],
            'alerts_enabled' => true,
            'frequency' => 'instant',
        ]);

        $this->actingAs($other);

        $this->putJson("/api/v1/saved-searches/{$savedSearch->id}", ['name' => 'Nope'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/saved-searches/{$savedSearch->id}")
            ->assertForbidden();
    }
}
