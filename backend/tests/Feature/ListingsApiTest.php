<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_listings_index_returns_data(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Test Stay',
            'address' => '123 Demo St',
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_night' => 120,
            'rating' => 4.5,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'category' => 'villa',
            'instant_book' => false,
        ]);

        $response = $this->getJson('/api/listings');

        $response->assertOk()->assertJsonFragment(['title' => 'Test Stay']);
    }

    public function test_landlord_cannot_update_other_listing(): void
    {
        $owner = User::factory()->create(['role' => 'landlord']);
        $other = User::factory()->create(['role' => 'landlord']);

        $listing = Listing::create([
            'owner_id' => $owner->id,
            'title' => 'Owner Stay',
            'address' => '123 Demo St',
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_night' => 200,
            'rating' => 4.8,
            'reviews_count' => 10,
            'beds' => 3,
            'baths' => 2,
            'category' => 'hotel',
            'instant_book' => true,
        ]);

        Sanctum::actingAs($other, ['*']);

        $response = $this->putJson('/api/landlord/listings/'.$listing->id, [
            'title' => 'Illegal update',
        ]);

        $response->assertForbidden();
    }
}
