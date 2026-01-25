<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingSearchNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_serbian_latin_characters_are_normalized_in_location_filter(): void
    {
        $listing = Listing::factory()->create([
            'city' => 'Dorćol',
            'title' => 'Central flat Dorćol',
        ]);

        $response = $this->getJson('/api/v1/listings?location=dorcol');

        $response->assertOk();
        $this->assertTrue(
            collect($response->json('data'))->contains(fn ($item) => $item['id'] === $listing->id),
            'Listing with accented city should be matched by unaccented query'
        );
    }

    public function test_serbian_latin_characters_are_normalized_in_city_filter_tokens(): void
    {
        $listing = Listing::factory()->create([
            'city' => 'Dorćol',
            'title' => 'Cozy Dorćol place',
        ]);

        $response = $this->getJson('/api/v1/listings?city=dorcol');

        $response->assertOk();
        $this->assertTrue(
            collect($response->json('data'))->contains(fn ($item) => $item['id'] === $listing->id),
            'Listing with accented city should be matched by unaccented city token'
        );
    }
}
