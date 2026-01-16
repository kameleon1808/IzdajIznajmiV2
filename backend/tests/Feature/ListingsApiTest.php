<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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

    public function test_landlord_can_create_listing_with_images(): void
    {
        config([
            'app.url' => 'http://localhost',
            'filesystems.disks.public.url' => 'http://localhost/storage',
        ]);
        Storage::fake('public');
        $landlord = User::factory()->create(['role' => 'landlord']);
        Sanctum::actingAs($landlord, ['*']);

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->post('/api/landlord/listings', [
            'title' => 'With Images',
            'pricePerNight' => 150,
            'category' => 'villa',
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'address' => '123 Demo',
            'description' => str_repeat('Nice place. ', 3),
            'beds' => 2,
            'baths' => 2,
            'images' => [$file],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonFragment(['title' => 'With Images']);

        $listingId = $response->json('id');
        $imageUrl = $response->json('images.0');
        $this->assertNotEmpty($imageUrl);
        $this->assertNotNull($listingId);

        $storagePath = str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH));
        Storage::disk('public')->assertExists($storagePath);
    }

    public function test_landlord_can_update_images_keep_and_add(): void
    {
        config([
            'app.url' => 'http://localhost',
            'filesystems.disks.public.url' => 'http://localhost/storage',
        ]);
        Storage::fake('public');
        $landlord = User::factory()->create(['role' => 'landlord']);
        Sanctum::actingAs($landlord, ['*']);

        $listing = Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'Old Listing',
            'address' => '123 Demo St',
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_night' => 120,
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
        ]);
        $listing->update(['cover_image' => $existingUrl]);

        $newFile = UploadedFile::fake()->image('new.jpg');

        $response = $this->put("/api/landlord/listings/{$listing->id}", [
            'title' => 'Updated',
            'keepImageUrls' => [$existingUrl],
            'images' => [$newFile],
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonFragment(['title' => 'Updated']);
        $imageUrls = $response->json('images');
        $this->assertCount(2, $imageUrls);

        $newUrl = collect($imageUrls)->first(fn ($url) => $url !== $existingUrl);
        $this->assertNotEmpty($newUrl);

        Storage::disk('public')->assertExists(str_replace('/storage/', '', parse_url($newUrl, PHP_URL_PATH)));
        Storage::disk('public')->assertExists($existingPath);
    }

    public function test_update_removes_deleted_images(): void
    {
        config([
            'app.url' => 'http://localhost',
            'filesystems.disks.public.url' => 'http://localhost/storage',
        ]);
        Storage::fake('public');
        $landlord = User::factory()->create(['role' => 'landlord']);
        Sanctum::actingAs($landlord, ['*']);

        $listing = Listing::create([
            'owner_id' => $landlord->id,
            'title' => 'To remove',
            'address' => '123 Demo St',
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_night' => 120,
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

        $response = $this->put("/api/landlord/listings/{$listing->id}", [
            'removeImageUrls' => [$url],
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->assertEmpty($response->json('images'));
    }
}
