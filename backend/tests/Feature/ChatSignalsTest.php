<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatSignalsTest extends TestCase
{
    use RefreshDatabase;

    private function createListing(User $owner, string $status = ListingStatusService::STATUS_ACTIVE): Listing
    {
        $addressGuard = app(ListingAddressGuardService::class);
        $address = 'Chat Signal Street '.uniqid();
        $addressKey = $addressGuard->normalizeAddressKey($address, 'Zagreb', 'Croatia');

        return Listing::create([
            'owner_id' => $owner->id,
            'title' => 'Chat Ready Home',
            'address' => $address,
            'address_key' => $addressKey,
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_month' => 150,
            'rating' => 4.8,
            'reviews_count' => 12,
            'beds' => 2,
            'baths' => 1,
            'rooms' => 2,
            'category' => 'apartment',
            'instant_book' => false,
            'status' => $status,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_typing_endpoints_require_participant(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $intruder = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $this->actingAs($intruder);
        $this->postJson("/api/v1/conversations/{$conversation->id}/typing", ['is_typing' => true])
            ->assertForbidden();
        $this->getJson("/api/v1/conversations/{$conversation->id}/typing")
            ->assertForbidden();
    }

    public function test_presence_ping_sets_online_state(): void
    {
        config(['cache.default' => 'array']);

        $user = User::factory()->create(['role' => 'seeker']);
        $this->actingAs($user);

        $this->postJson('/api/v1/presence/ping')
            ->assertOk()
            ->assertJsonPath('online', true);

        $this->getJson("/api/v1/users/{$user->id}/presence")
            ->assertOk()
            ->assertJsonPath('online', true);
    }

    public function test_presence_batch_returns_only_allowed_users(): void
    {
        config(['cache.default' => 'array']);

        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $this->actingAs($landlord);
        $this->postJson('/api/v1/presence/ping')->assertOk();

        $this->actingAs($seeker);
        $response = $this->getJson("/api/v1/presence/users?ids[]={$landlord->id}&ids[]={$otherLandlord->id}");
        $response->assertOk();

        $payload = $response->json('data') ?? [];
        $this->assertCount(1, $payload);
        $this->assertSame($landlord->id, $payload[0]['userId']);
        $this->assertTrue((bool) $payload[0]['online']);
    }
}
