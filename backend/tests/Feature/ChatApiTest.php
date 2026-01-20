<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Models\Application;
use App\Services\ListingAddressGuardService;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private function createListing(User $owner, string $status = ListingStatusService::STATUS_ACTIVE): Listing
    {
        $addressGuard = app(ListingAddressGuardService::class);
        $address = 'Chat Street ' . uniqid();
        $addressKey = $addressGuard->normalizeAddressKey($address, 'Zagreb', 'Croatia');

        return Listing::create([
            'owner_id' => $owner->id,
            'title' => 'Chat Ready Home',
            'address' => $address,
            'address_key' => $addressKey,
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_night' => 150,
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

    public function test_conversation_created_once_per_listing_and_seeker(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $this->actingAs($seeker);

        $first = $this->getJson("/api/v1/listings/{$listing->id}/conversation");
        $first->assertOk()->assertJsonPath('listingId', $listing->id);

        $second = $this->getJson("/api/v1/listings/{$listing->id}/conversation");
        $second->assertOk();

        $this->assertSame(1, Conversation::count());
    }

    public function test_landlord_can_create_conversation_from_application(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $application = Application::create([
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'message' => 'Interested',
            'status' => Application::STATUS_SUBMITTED,
        ]);

        $this->actingAs($landlord);
        $response = $this->postJson("/api/v1/applications/{$application->id}/conversation");
        $response->assertOk()->assertJsonPath('listingId', $listing->id);
        $this->assertSame(1, Conversation::count());
    }

    public function test_landlord_cannot_create_conversation_for_foreign_application(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($otherLandlord);
        $application = Application::create([
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $otherLandlord->id,
            'message' => 'Hello',
            'status' => Application::STATUS_SUBMITTED,
        ]);

        $this->actingAs($landlord);
        $this->postJson("/api/v1/applications/{$application->id}/conversation")->assertForbidden();
    }

    public function test_participants_only_can_read_conversation_messages(): void
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

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $seeker->id,
            'body' => 'Hello!',
        ]);

        $this->actingAs($intruder);

        $response = $this->getJson("/api/v1/conversations/{$conversation->id}/messages");
        $response->assertForbidden();
    }

    public function test_spam_rule_blocks_fourth_message_until_landlord_replies(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        for ($i = 0; $i < 3; $i++) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $seeker->id,
                'body' => 'Ping ' . $i,
            ]);
        }

        $this->actingAs($seeker);
        $blocked = $this->postJson("/api/v1/listings/{$listing->id}/messages", ['message' => 'Should block']);
        $blocked->assertStatus(429);

        $this->actingAs($landlord);
        $landlordReply = $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['message' => 'Reply']);
        $landlordReply->assertCreated();

        $this->actingAs($seeker);
        $allowed = $this->postJson("/api/v1/listings/{$listing->id}/messages", ['message' => 'Now allowed']);
        $allowed->assertCreated();
    }

    public function test_read_markers_update_unread_count(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Update 1',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Update 2',
        ]);

        $this->actingAs($seeker);

        $listResponse = $this->getJson('/api/v1/conversations');
        $listResponse->assertOk();
        $payload = $listResponse->json('data') ?? $listResponse->json();
        $this->assertSame(2, $payload[0]['unreadCount']);

        $read = $this->postJson("/api/v1/conversations/{$conversation->id}/read");
        $read->assertOk();

        $afterRead = $this->getJson('/api/v1/conversations');
        $afterRead->assertOk();
        $payloadAfter = $afterRead->json('data') ?? $afterRead->json();
        $this->assertSame(0, $payloadAfter[0]['unreadCount']);
    }

    public function test_participant_can_fetch_single_conversation(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $this->actingAs($seeker);
        $response = $this->getJson("/api/v1/conversations/{$conversation->id}");
        $response->assertOk()->assertJsonPath('id', $conversation->id);

        $this->actingAs(User::factory()->create(['role' => 'seeker']));
        $this->getJson("/api/v1/conversations/{$conversation->id}")->assertForbidden();
    }
}
