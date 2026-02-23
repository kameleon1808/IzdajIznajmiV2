<?php

namespace Tests\Feature;

use App\Jobs\GeocodeListingJob;
use App\Models\Facility;
use App\Models\Listing;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingsApiTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function test_listings_index_returns_data(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Test Stay',
            'address' => '123 Demo St',
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_month' => 120,
            'rating' => 4.5,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'category' => 'villa',
            'instant_book' => false,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/listings');

        $response->assertOk()->assertJsonFragment(['title' => 'Test Stay']);
    }

    public function test_public_listings_excludes_draft(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Draft Stay',
            'address' => '123 Demo St',
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_month' => 120,
            'rating' => 4.5,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'category' => 'villa',
            'instant_book' => false,
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/listings');
        $response->assertOk()->assertJsonMissing(['title' => 'Draft Stay']);
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
            'price_per_month' => 200,
            'rating' => 4.8,
            'reviews_count' => 10,
            'beds' => 3,
            'baths' => 2,
            'category' => 'hotel',
            'instant_book' => true,
        ]);

        $this->actingAs($other);

        $response = $this->putJson('/api/v1/landlord/listings/'.$listing->id, [
            'title' => 'Illegal update',
        ]);

        $response->assertForbidden();
    }

    public function test_landlord_can_create_listing_with_images(): void
    {
        config([
            'app.url' => 'http://localhost',
            'filesystems.disks.public.url' => 'http://localhost/storage',
        ]);
        Storage::fake('public');
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($landlord);

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->post('/api/v1/landlord/listings', [
            'title' => 'With Images',
            'pricePerMonth' => 150,
            'category' => 'villa',
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'address' => '123 Demo',
            'description' => str_repeat('Nice place. ', 3),
            'beds' => 2,
            'baths' => 2,
            'floor' => 3,
            'notLastFloor' => true,
            'notGroundFloor' => true,
            'heating' => 'gas',
            'condition' => 'novogradnja',
            'furnishing' => 'namesten',
            'facilities' => ['Lift', 'Terasa'],
            'images' => [$file],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonFragment(['title' => 'With Images']);
        $response->assertJsonPath('floor', 3);
        $response->assertJsonPath('notLastFloor', true);
        $response->assertJsonPath('notGroundFloor', true);
        $response->assertJsonPath('heating', 'gas');
        $response->assertJsonPath('condition', 'novogradnja');
        $response->assertJsonPath('furnishing', 'namesten');
        $this->assertContains('Elevator', $response->json('facilities'));

        $listingId = $response->json('id');
        $this->assertNotNull($listingId);
        $this->assertTrue(in_array($response->json('imagesDetailed.0.processingStatus'), ['pending', 'done']));
    }

    public function test_landlord_can_update_images_keep_and_add(): void
    {
        config([
            'app.url' => 'http://localhost',
            'filesystems.disks.public.url' => 'http://localhost/storage',
        ]);
        Storage::fake('public');
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($landlord);

        $listing = Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Old Listing',
            'address' => '123 Demo St',
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_month' => 120,
            'rating' => 4.5,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'category' => 'villa',
            'instant_book' => false,
        ]);

        $existingPath = "listings/{$listing->id}/old.jpg";
        Storage::disk('public')->put($existingPath, 'old');
        $existingUrl = Storage::url($existingPath);
        $listing->images()->create([
            'url' => $existingUrl,
            'sort_order' => 0,
            'processing_status' => 'done',
        ]);
        $listing->update(['cover_image' => $existingUrl]);

        $newFile = UploadedFile::fake()->image('new.jpg');

        $response = $this->put("/api/v1/landlord/listings/{$listing->id}", [
            'title' => 'Updated',
            'keepImages' => json_encode([['url' => $existingUrl, 'sortOrder' => 0, 'isCover' => true]]),
            'images' => [$newFile],
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonFragment(['title' => 'Updated']);
        $imagesDetailed = $response->json('imagesDetailed');
        $this->assertCount(2, $imagesDetailed);
        $newPending = collect($imagesDetailed)->first(fn ($img) => $img['url'] !== $existingUrl);
        $this->assertTrue(in_array($newPending['processingStatus'], ['pending', 'done']));
        Storage::disk('public')->assertExists($existingPath);
        $this->assertEquals($existingUrl, $response->json('coverImage'));
    }

    public function test_update_removes_deleted_images(): void
    {
        config([
            'app.url' => 'http://localhost',
            'filesystems.disks.public.url' => 'http://localhost/storage',
        ]);
        Storage::fake('public');
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($landlord);

        $listing = Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'To remove',
            'address' => '123 Demo St',
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_month' => 120,
            'rating' => 4.5,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'category' => 'villa',
            'instant_book' => false,
        ]);

        $path = "listings/{$listing->id}/remove.jpg";
        Storage::disk('public')->put($path, 'remove');
        $url = Storage::url($path);
        $listing->images()->create(['url' => $url, 'sort_order' => 0]);
        $listing->update(['cover_image' => $url]);

        $response = $this->put("/api/v1/landlord/listings/{$listing->id}", [
            'removeImageUrls' => [$url],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->assertEmpty($response->json('images'));
    }

    public function test_landlord_can_publish_unpublish(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($landlord);
        $listing = Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Lifecycle',
            'address' => '123 Demo St',
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_month' => 120,
            'rating' => 4.5,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'category' => 'villa',
            'instant_book' => false,
            'status' => 'draft',
        ]);

        $publish = $this->patchJson("/api/v1/landlord/listings/{$listing->id}/publish");
        $publish->assertOk()->assertJsonFragment(['status' => 'active']);

        $unpublish = $this->patchJson("/api/v1/landlord/listings/{$listing->id}/unpublish");
        $unpublish->assertOk()->assertJsonFragment(['status' => 'paused']);
    }

    public function test_tenant_cannot_publish(): void
    {
        $tenant = User::factory()->create(['role' => 'seeker']);
        $this->actingAs($tenant);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Nope',
            'address' => '123 Demo St',
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_month' => 120,
            'rating' => 4.5,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'category' => 'villa',
            'instant_book' => false,
            'status' => 'draft',
        ]);

        $resp = $this->patchJson("/api/v1/landlord/listings/{$listing->id}/publish");
        $resp->assertForbidden();
    }

    public function test_same_landlord_cannot_activate_duplicate_address(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $guard = app(ListingAddressGuardService::class);
        $addressKey = $guard->normalizeAddressKey('123 Demo St', 'Zagreb', 'Croatia');

        $this->actingAs($landlord);

        Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'First',
            'address' => '123 Demo St',
            'address_key' => $addressKey,
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_month' => 120,
            'rating' => 4.5,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'rooms' => 2,
            'category' => 'villa',
            'instant_book' => false,
            'status' => 'active',
            'published_at' => now()->subDays(2),
        ]);

        $listingTwo = Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Second',
            'address' => '123 Demo St',
            'address_key' => $addressKey,
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_month' => 130,
            'rating' => 4.5,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'rooms' => 2,
            'category' => 'villa',
            'instant_book' => false,
            'status' => 'draft',
        ]);

        $resp = $this->patchJson("/api/v1/landlord/listings/{$listingTwo->id}/publish");
        $resp->assertStatus(409);
    }

    public function test_warns_when_other_landlord_has_active_address(): void
    {
        $guard = app(ListingAddressGuardService::class);
        $addressKey = $guard->normalizeAddressKey('45 Water St', 'Split', 'Croatia');

        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);

        Listing::create([
            'owner_id' => $landlordA->id,
            'title' => 'Original',
            'address' => '45 Water St',
            'address_key' => $addressKey,
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_month' => 150,
            'rating' => 4.6,
            'reviews_count' => 10,
            'beds' => 3,
            'baths' => 2,
            'rooms' => 3,
            'category' => 'villa',
            'instant_book' => true,
            'status' => 'active',
            'published_at' => now()->subDays(5),
        ]);

        $this->actingAs($landlordB);
        $listingB = Listing::create([
            'owner_id' => $landlordB->id,
            'title' => 'New',
            'address' => '45 Water St',
            'address_key' => $addressKey,
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_month' => 155,
            'rating' => 4.4,
            'reviews_count' => 5,
            'beds' => 3,
            'baths' => 2,
            'rooms' => 3,
            'category' => 'villa',
            'instant_book' => true,
            'status' => 'draft',
        ]);

        $resp = $this->patchJson("/api/v1/landlord/listings/{$listingB->id}/publish");
        $resp->assertOk();
        $resp->assertJsonFragment(['status' => 'active']);
        $this->assertNotEmpty($resp->json('warnings'));
    }

    public function test_auto_expire_command_marks_old_active_listings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-01 00:00:00'));
        $landlord = User::factory()->create(['role' => 'landlord']);
        $guard = app(ListingAddressGuardService::class);
        $oldKey = $guard->normalizeAddressKey('Old 1', 'Zagreb', 'Croatia');
        $freshKey = $guard->normalizeAddressKey('Fresh 2', 'Split', 'Croatia');

        $oldListing = Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Old',
            'address' => 'Old 1',
            'address_key' => $oldKey,
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_month' => 100,
            'rating' => 4.4,
            'reviews_count' => 1,
            'beds' => 2,
            'baths' => 1,
            'rooms' => 2,
            'category' => 'apartment',
            'instant_book' => false,
            'status' => 'active',
            'published_at' => Carbon::now()->subDays(35),
        ]);

        $freshListing = Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Fresh',
            'address' => 'Fresh 2',
            'address_key' => $freshKey,
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_month' => 120,
            'rating' => 4.7,
            'reviews_count' => 3,
            'beds' => 2,
            'baths' => 1,
            'rooms' => 2,
            'category' => 'apartment',
            'instant_book' => true,
            'status' => 'active',
            'published_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('listings:expire')->assertSuccessful();

        $this->assertEquals('expired', $oldListing->fresh()->status);
        $this->assertEquals('active', $freshListing->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_discovery_filters_city_rooms_area_price_amenities(): void
    {
        $guard = app(ListingAddressGuardService::class);
        $wifi = Facility::create(['name' => 'wifi']);
        $pool = Facility::create(['name' => 'pool']);

        $listingA = Listing::create([
            'owner_id' => User::factory()->create(['role' => 'landlord'])->id,
            'title' => 'Filter Match',
            'address' => '1 Main',
            'address_key' => $guard->normalizeAddressKey('1 Main', 'Zagreb', 'Croatia'),
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_month' => 150,
            'rating' => 4.8,
            'reviews_count' => 20,
            'beds' => 3,
            'baths' => 2,
            'rooms' => 3,
            'area' => 95,
            'category' => 'villa',
            'instant_book' => true,
            'status' => 'active',
            'published_at' => now()->subDays(2),
        ]);
        $listingA->facilities()->sync([$wifi->id, $pool->id]);

        $listingB = Listing::create([
            'owner_id' => User::factory()->create(['role' => 'landlord'])->id,
            'title' => 'Filter Miss',
            'address' => '2 Side',
            'address_key' => $guard->normalizeAddressKey('2 Side', 'Split', 'Croatia'),
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_month' => 60,
            'rating' => 4.1,
            'reviews_count' => 5,
            'beds' => 1,
            'baths' => 1,
            'rooms' => 1,
            'area' => 40,
            'category' => 'apartment',
            'instant_book' => false,
            'status' => 'active',
            'published_at' => now()->subDays(2),
        ]);
        $listingB->facilities()->sync([$pool->id]);

        $pausedListing = Listing::create([
            'owner_id' => User::factory()->create(['role' => 'landlord'])->id,
            'title' => 'Paused',
            'address' => '3 Pause',
            'address_key' => $guard->normalizeAddressKey('3 Pause', 'Zagreb', 'Croatia'),
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_month' => 110,
            'rating' => 4.2,
            'reviews_count' => 8,
            'beds' => 2,
            'baths' => 1,
            'rooms' => 2,
            'area' => 65,
            'category' => 'apartment',
            'instant_book' => false,
            'status' => 'paused',
        ]);

        $luxListing = Listing::create([
            'owner_id' => User::factory()->create(['role' => 'landlord'])->id,
            'title' => 'Lux Listing',
            'address' => '4 River',
            'address_key' => $guard->normalizeAddressKey('4 River', 'South Ethelville', 'Luxembourg'),
            'city' => 'South Ethelville',
            'country' => 'Luxembourg',
            'price_per_month' => 180,
            'rating' => 4.9,
            'reviews_count' => 15,
            'beds' => 3,
            'baths' => 2,
            'rooms' => 3,
            'area' => 120,
            'category' => 'villa',
            'instant_book' => true,
            'status' => 'active',
            'published_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/v1/listings?city=zag&rooms=2&areaMin=80&areaMax=120&priceMin=120&priceMax=180&amenities[]=wifi');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($ids->contains($listingA->id));
        $this->assertFalse($ids->contains($listingB->id));
        $this->assertFalse($ids->contains($pausedListing->id));

        $countryResponse = $this->getJson('/api/v1/listings?city=Luxembourg');
        $countryIds = collect($countryResponse->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($countryIds->contains($luxListing->id));

        $comboResponse = $this->getJson('/api/v1/listings?city=South%20Ethelville,%20Luxembourg');
        $comboIds = collect($comboResponse->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($comboIds->contains($luxListing->id));

        $statusResponse = $this->getJson('/api/v1/listings?status=paused');
        $statusIds = collect($statusResponse->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($statusIds->contains($pausedListing->id));
    }

    public function test_geo_radius_filter_includes_only_nearby_listings(): void
    {
        $near = Listing::factory()->create([
            'lat' => 45.05,
            'lng' => 15.05,
            'status' => 'active',
        ]);
        $far = Listing::factory()->create([
            'lat' => 46.2,
            'lng' => 15.0,
            'status' => 'active',
        ]);
        $missing = Listing::withoutEvents(function () {
            return Listing::factory()->create([
                'lat' => null,
                'lng' => null,
                'status' => 'active',
            ]);
        });

        $response = $this->getJson('/api/v1/listings?centerLat=45&centerLng=15&radiusKm=50&mapMode=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($ids->contains($near->id));
        $this->assertFalse($ids->contains($far->id));
        $this->assertFalse($ids->contains($missing->id));

        $distance = collect($response->json('data'))->firstWhere('id', $near->id)['distanceKm'] ?? null;
        $this->assertNotNull($distance);
    }

    public function test_geocode_job_populates_missing_coordinates(): void
    {
        config(['geocoding.driver' => 'fake']);
        $listing = Listing::factory()->create([
            'address' => '1 Geo St',
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'lat' => null,
            'lng' => null,
            'geocoded_at' => null,
            'status' => 'draft',
        ]);

        GeocodeListingJob::dispatchSync($listing->id, true);

        $fresh = $listing->fresh();
        $this->assertNotNull($fresh->lat);
        $this->assertNotNull($fresh->lng);
        $this->assertNotNull($fresh->geocoded_at);
    }

    public function test_geocode_command_sets_timestamp_for_existing_coords(): void
    {
        config(['geocoding.driver' => 'fake']);
        $listing = Listing::factory()->create([
            'lat' => 45.1,
            'lng' => 15.2,
            'geocoded_at' => null,
        ]);

        $this->artisan('listings:geocode --missing')->assertSuccessful();

        $this->assertNotNull($listing->fresh()->geocoded_at);
    }
}
