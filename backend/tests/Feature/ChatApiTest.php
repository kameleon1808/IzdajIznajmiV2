<?php

namespace Tests\Feature;

use App\Jobs\GeocodeListingJob;
use App\Jobs\IndexListingJob;
use App\Jobs\RemoveListingFromIndexJob;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private function createListing(User $owner, string $status = ListingStatusService::STATUS_ACTIVE): Listing
    {
        $addressGuard = app(ListingAddressGuardService::class);
        $address = 'Chat Street '.uniqid();
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
                'body' => 'Ping '.$i,
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

    public function test_messages_endpoint_supports_incremental_fetch_with_since_id(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $first = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Older message',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Newer message 1',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Newer message 2',
        ]);

        $this->actingAs($seeker);
        $response = $this->getJson("/api/v1/conversations/{$conversation->id}/messages?since_id={$first->id}");
        $response->assertOk();

        $payload = $response->json('data') ?? $response->json() ?? [];
        $this->assertCount(2, $payload);
        $this->assertSame('Newer message 1', $payload[0]['body'] ?? null);
        $this->assertSame('Newer message 2', $payload[1]['body'] ?? null);
    }

    public function test_messages_endpoint_returns_304_for_matching_etag_and_since_id(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $baseline = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Baseline',
        ]);

        $this->actingAs($seeker);
        $first = $this->getJson("/api/v1/conversations/{$conversation->id}/messages?since_id={$baseline->id}");
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->withHeaders(['If-None-Match' => $etag])
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?since_id={$baseline->id}")
            ->assertStatus(304);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'After 304',
        ]);

        $updated = $this->withHeaders(['If-None-Match' => $etag])
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?since_id={$baseline->id}");
        $updated->assertOk();

        $payload = $updated->json('data') ?? $updated->json() ?? [];
        $this->assertCount(1, $payload);
        $this->assertSame('After 304', $payload[0]['body'] ?? null);
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

    public function test_marking_conversation_read_marks_related_message_notifications_as_read(): void
    {
        Queue::fake([
            GeocodeListingJob::class,
            IndexListingJob::class,
            RemoveListingFromIndexJob::class,
        ]);

        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $matching = Notification::create([
            'user_id' => $seeker->id,
            'type' => Notification::TYPE_MESSAGE_RECEIVED,
            'title' => 'New message',
            'body' => 'Open chat',
            'data' => ['conversation_id' => $conversation->id],
            'url' => "/chat?conversationId={$conversation->id}",
            'is_read' => false,
        ]);

        $other = Notification::create([
            'user_id' => $seeker->id,
            'type' => Notification::TYPE_MESSAGE_RECEIVED,
            'title' => 'Another conversation',
            'body' => 'Should stay unread',
            'data' => ['conversation_id' => 999999],
            'url' => '/chat?conversationId=999999',
            'is_read' => false,
        ]);

        $this->actingAs($seeker)
            ->postJson("/api/v1/conversations/{$conversation->id}/read")
            ->assertOk();

        $this->assertTrue((bool) $matching->fresh()?->is_read);
        $this->assertNotNull($matching->fresh()?->read_at);
        $this->assertFalse((bool) $other->fresh()?->is_read);
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

    public function test_chat_messages_are_rate_limited_per_user(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $this->actingAs($landlord);

        for ($i = 0; $i < 30; $i++) {
            $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['message' => 'ping '.$i])
                ->assertCreated();
        }

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['message' => 'rate limited'])
            ->assertStatus(429);
    }

    public function test_participant_can_send_message_with_image_attachment(): void
    {
        Queue::fake();
        Storage::fake('private');

        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $this->actingAs($landlord);

        $file = UploadedFile::fake()->image('kitchen.jpg', 800, 600);

        $response = $this->post("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'See the kitchen',
            'attachments' => [$file],
        ]);

        $response->assertCreated();
        $payload = $response->json();
        $this->assertNotEmpty($payload['attachments']);
        $this->assertSame('image', $payload['attachments'][0]['kind']);

        $attachment = $conversation->messages()->latest()->first()->attachments()->first();
        $this->assertNotNull($attachment);
        Storage::disk('private')->assertExists($attachment->path_original);
    }

    public function test_participant_can_download_attachment(): void
    {
        Storage::fake('private');

        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Attachment',
        ]);

        $path = 'chat/'.$conversation->id.'/file.pdf';
        Storage::disk('private')->put($path, 'pdf-content');

        $attachment = $message->attachments()->create([
            'conversation_id' => $conversation->id,
            'uploader_id' => $landlord->id,
            'kind' => 'document',
            'original_name' => 'file.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'disk' => 'private',
            'path_original' => $path,
        ]);

        $this->actingAs($seeker);

        $this->get("/api/v1/chat/attachments/{$attachment->id}")
            ->assertOk();
    }

    public function test_non_participant_cannot_download_or_send(): void
    {
        Storage::fake('private');

        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $intruder = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Attachment',
        ]);

        $path = 'chat/'.$conversation->id.'/file.pdf';
        Storage::disk('private')->put($path, 'pdf-content');

        $attachment = $message->attachments()->create([
            'conversation_id' => $conversation->id,
            'uploader_id' => $landlord->id,
            'kind' => 'document',
            'original_name' => 'file.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'disk' => 'private',
            'path_original' => $path,
        ]);

        $this->actingAs($intruder);
        $this->get("/api/v1/chat/attachments/{$attachment->id}")->assertForbidden();

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['message' => 'Nope'])
            ->assertForbidden();
    }

    public function test_message_attachment_validation_rules(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $this->actingAs($landlord);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [])->assertStatus(422);

        $tooLarge = UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf');
        $this->post("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Large file',
            'attachments' => [$tooLarge],
        ])->assertStatus(422);

        $bad = UploadedFile::fake()->create('bad.txt', 10, 'text/plain');
        $this->post("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Bad file',
            'attachments' => [$bad],
        ])->assertStatus(422);

        $many = [];
        for ($i = 0; $i < 6; $i++) {
            $many[] = UploadedFile::fake()->image('img'.$i.'.jpg', 10, 10);
        }
        $this->post("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Too many',
            'attachments' => $many,
        ])->assertStatus(422);
    }

    public function test_chat_attachments_are_rate_limited_per_thread(): void
    {
        Queue::fake();
        Storage::fake('private');

        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $this->actingAs($landlord);

        for ($i = 0; $i < 10; $i++) {
            $file = UploadedFile::fake()->image('img'.$i.'.jpg', 10, 10);
            $this->post("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Ping '.$i,
                'attachments' => [$file],
            ])->assertCreated();
        }

        $file = UploadedFile::fake()->image('overflow.jpg', 10, 10);
        $this->post("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Overflow',
            'attachments' => [$file],
        ])->assertStatus(429);
    }
}
