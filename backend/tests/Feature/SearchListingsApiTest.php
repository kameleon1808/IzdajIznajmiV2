<?php

namespace Tests\Feature;

use App\Models\Facility;
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

    public function test_search_matches_garage_filter_against_legacy_facility_aliases(): void
    {
        config(['search.driver' => 'sql']);

        $listingGarage = Listing::factory()->create(['status' => ListingStatusService::STATUS_ACTIVE]);
        $listingGarage->facilities()->sync([Facility::firstOrCreate(['name' => 'Garage'])->id]);

        $listingParking = Listing::factory()->create(['status' => ListingStatusService::STATUS_ACTIVE]);
        $listingParking->facilities()->sync([Facility::firstOrCreate(['name' => 'Parking'])->id]);

        $listingAccented = Listing::factory()->create(['status' => ListingStatusService::STATUS_ACTIVE]);
        $listingAccented->facilities()->sync([Facility::firstOrCreate(['name' => 'Garaža'])->id]);

        $listingOther = Listing::factory()->create(['status' => ListingStatusService::STATUS_ACTIVE]);
        $listingOther->facilities()->sync([Facility::firstOrCreate(['name' => 'Internet'])->id]);

        $response = $this->getJson('/api/v1/search/listings?amenities[]=Garage');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($listingGarage->id, $ids);
        $this->assertContains($listingParking->id, $ids);
        $this->assertContains($listingAccented->id, $ids);
        $this->assertNotContains($listingOther->id, $ids);
    }

    public function test_search_amenity_facets_are_returned_in_canonical_english_form(): void
    {
        config(['search.driver' => 'sql']);

        $listingGarage = Listing::factory()->create(['status' => ListingStatusService::STATUS_ACTIVE]);
        $listingGarage->facilities()->sync([Facility::firstOrCreate(['name' => 'Garage'])->id]);

        $listingParking = Listing::factory()->create(['status' => ListingStatusService::STATUS_ACTIVE]);
        $listingParking->facilities()->sync([Facility::firstOrCreate(['name' => 'Parking'])->id]);

        $listingAccented = Listing::factory()->create(['status' => ListingStatusService::STATUS_ACTIVE]);
        $listingAccented->facilities()->sync([Facility::firstOrCreate(['name' => 'Garaža'])->id]);

        $response = $this->getJson('/api/v1/search/listings');
        $response->assertOk();

        $amenities = collect($response->json('facets.amenities'));
        $garageFacet = $amenities->firstWhere('value', 'Garage');

        $this->assertNotNull($garageFacet);
        $this->assertSame(3, (int) ($garageFacet['count'] ?? 0));
        $this->assertNull($amenities->firstWhere('value', 'Parking'));
        $this->assertNull($amenities->firstWhere('value', 'Garaža'));
    }

    public function test_search_filters_by_property_attributes(): void
    {
        config(['search.driver' => 'sql']);

        $match = Listing::factory()->create([
            'status' => ListingStatusService::STATUS_ACTIVE,
            'category' => 'apartment',
            'baths' => 2,
            'floor' => 3,
            'heating' => 'gas',
            'condition' => 'stara_gradnja',
            'furnishing' => 'namesten',
            'not_last_floor' => true,
            'not_ground_floor' => true,
        ]);

        Listing::factory()->create([
            'status' => ListingStatusService::STATUS_ACTIVE,
            'category' => 'apartment',
            'baths' => 1,
            'floor' => 3,
            'heating' => 'gas',
            'condition' => 'stara_gradnja',
            'furnishing' => 'namesten',
            'not_last_floor' => true,
            'not_ground_floor' => true,
        ]);

        Listing::factory()->create([
            'status' => ListingStatusService::STATUS_ACTIVE,
            'category' => 'apartment',
            'baths' => 2,
            'floor' => 3,
            'heating' => 'gas',
            'condition' => 'stara_gradnja',
            'furnishing' => 'namesten',
            'not_last_floor' => false,
            'not_ground_floor' => true,
        ]);

        $response = $this->getJson('/api/v1/search/listings?category=apartment&baths=2&floor=3&heating=gas&condition=stara_gradnja&furnishing=namesten&notLastFloor=1&notGroundFloor=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertSame([$match->id], $ids);
    }
}
