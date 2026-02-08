<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_their_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
            'title' => 'New Application',
            'body' => 'Someone applied',
            'is_read' => false,
        ]);

        Notification::create([
            'user_id' => $otherUser->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
            'title' => 'Other User Notification',
            'body' => 'Should not see',
            'is_read' => false,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/notifications?status=all');

        $response->assertOk();
        $data = $response->json('data') ?? [];
        $this->assertCount(1, $data);
        $this->assertSame('New Application', $data[0]['title']);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_MESSAGE_RECEIVED,
            'title' => 'New Message',
            'body' => 'You have a message',
            'is_read' => false,
        ]);

        $this->actingAs($user);

        $response = $this->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $otherUser->id,
            'type' => Notification::TYPE_MESSAGE_RECEIVED,
            'title' => 'Not yours',
            'body' => 'Should fail',
            'is_read' => false,
        ]);

        $this->actingAs($user);

        $response = $this->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertForbidden();
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
            'title' => 'First',
            'body' => 'Unread',
            'is_read' => false,
        ]);
        Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_MESSAGE_RECEIVED,
            'title' => 'Second',
            'body' => 'Also unread',
            'is_read' => false,
        ]);

        $this->actingAs($user);

        $response = $this->patchJson('/api/v1/notifications/read-all');

        $response->assertOk();
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'is_read' => false,
        ]);
    }

    public function test_user_can_fetch_unread_count(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
            'title' => 'Unread',
            'body' => 'Test',
            'is_read' => false,
        ]);
        Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_MESSAGE_RECEIVED,
            'title' => 'Read',
            'body' => 'Test',
            'is_read' => true,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()->assertJson(['count' => 1]);
    }

    public function test_application_created_triggers_notification_to_landlord(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);

        NotificationPreference::create([
            'user_id' => $landlord->id,
            'type_settings' => [Notification::TYPE_APPLICATION_CREATED => true],
            'digest_frequency' => NotificationPreference::DIGEST_NONE,
            'digest_enabled' => false,
        ]);

        $this->actingAs($seeker);

        $this->postJson("/api/v1/listings/{$listing->id}/apply", [
            'message' => 'I want to apply',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $landlord->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
            'is_read' => false,
        ]);
    }

    public function test_notification_not_created_when_preference_disabled(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create([
            'owner_id' => $landlord->id,
            'status' => ListingStatusService::STATUS_ACTIVE,
        ]);

        NotificationPreference::create([
            'user_id' => $landlord->id,
            'type_settings' => [Notification::TYPE_APPLICATION_CREATED => false],
            'digest_frequency' => NotificationPreference::DIGEST_NONE,
            'digest_enabled' => false,
        ]);

        $this->actingAs($seeker);

        $this->postJson("/api/v1/listings/{$listing->id}/apply", [
            'message' => 'I want to apply',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $landlord->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
        ]);
    }

    public function test_user_can_fetch_and_update_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $getResponse = $this->getJson('/api/v1/notification-preferences');
        $getResponse->assertOk();

        $updateResponse = $this->putJson('/api/v1/notification-preferences', [
            'digest_frequency' => 'daily',
            'digest_enabled' => true,
            'type_settings' => [
                Notification::TYPE_APPLICATION_CREATED => false,
                Notification::TYPE_MESSAGE_RECEIVED => true,
            ],
        ]);

        $updateResponse->assertOk()->assertJsonPath('digest_frequency', 'daily');
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'digest_frequency' => 'daily',
            'digest_enabled' => true,
        ]);
    }
}
