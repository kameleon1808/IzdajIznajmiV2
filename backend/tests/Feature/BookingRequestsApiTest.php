<?php

namespace Tests\Feature;

use App\Models\BookingRequest;
use App\Models\Listing;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingRequestsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createListing(User $owner): Listing
    {
        $addressGuard = app(ListingAddressGuardService::class);
        $addressKey = $addressGuard->normalizeAddressKey('1 Beach Way', 'Split', 'Croatia');

        return Listing::create([
            'owner_id' => $owner->id,
            'title' => 'Seaside Villa',
            'address' => '1 Beach Way',
            'address_key' => $addressKey,
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_night' => 250,
            'rating' => 4.9,
            'reviews_count' => 30,
            'beds' => 3,
            'baths' => 2,
            'rooms' => 3,
            'category' => 'villa',
            'instant_book' => false,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_tenant_can_create_booking_request(): void
    {
        $tenant = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $this->actingAs($tenant);

        $response = $this->postJson('/api/v1/booking-requests', [
            'listingId' => $listing->id,
            'landlordId' => $landlord->id,
            'guests' => 2,
            'message' => 'We would like to stay.',
        ]);

        $response->assertCreated()->assertJsonFragment(['status' => 'pending']);
    }

    public function test_tenant_cannot_accept_request(): void
    {
        $tenant = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $request = BookingRequest::create([
            'listing_id' => $listing->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'guests' => 2,
            'message' => 'Hello',
            'status' => 'pending',
        ]);

        $this->actingAs($tenant);

        $response = $this->patchJson('/api/v1/booking-requests/'.$request->id, ['status' => 'accepted']);

        $response->assertForbidden();
    }

    public function test_landlord_can_accept_request(): void
    {
        $tenant = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $requestModel = BookingRequest::create([
            'listing_id' => $listing->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'guests' => 2,
            'message' => 'Hi there',
            'status' => 'pending',
        ]);

        $this->actingAs($landlord);

        $response = $this->patchJson('/api/v1/booking-requests/'.$requestModel->id, ['status' => 'accepted']);

        $response->assertOk()->assertJsonFragment(['status' => 'accepted']);
    }
}
