<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_subscribe_for_push_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/push/subscribe', [
                'endpoint' => 'https://example.test/push/subscription-1',
                'keys' => [
                    'p256dh' => 'test-p256dh',
                    'auth' => 'test-auth',
                ],
                'deviceLabel' => 'Chrome Desktop',
            ])
            ->assertCreated()
            ->assertJsonPath('data.endpoint', 'https://example.test/push/subscription-1')
            ->assertJsonPath('data.deviceLabel', 'Chrome Desktop')
            ->assertJsonPath('data.isEnabled', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://example.test/push/subscription-1',
            'is_enabled' => true,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'push_enabled' => true,
        ]);
    }

    public function test_user_can_list_only_their_push_subscriptions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://example.test/push/subscription-a',
            'p256dh' => 'p-a',
            'auth' => 'a-a',
            'is_enabled' => true,
        ]);

        PushSubscription::create([
            'user_id' => $otherUser->id,
            'endpoint' => 'https://example.test/push/subscription-b',
            'p256dh' => 'p-b',
            'auth' => 'a-b',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/push/subscriptions');

        $response->assertOk();
        $data = $response->json('data') ?? [];

        $this->assertCount(1, $data);
        $this->assertSame('https://example.test/push/subscription-a', $data[0]['endpoint']);
    }

    public function test_user_can_unsubscribe_and_push_channel_is_disabled_without_active_devices(): void
    {
        $user = User::factory()->create();

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://example.test/push/subscription-1',
            'p256dh' => 'p-key',
            'auth' => 'a-key',
            'is_enabled' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/push/subscribe', [
                'endpoint' => 'https://example.test/push/subscription-2',
                'keys' => [
                    'p256dh' => 'test-p256dh',
                    'auth' => 'test-auth',
                ],
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/api/v1/push/unsubscribe', [
                'endpoint' => 'https://example.test/push/subscription-1',
            ])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://example.test/push/subscription-1',
            'is_enabled' => false,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'push_enabled' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/push/unsubscribe', [
                'endpoint' => 'https://example.test/push/subscription-2',
            ])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://example.test/push/subscription-2',
            'is_enabled' => false,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'push_enabled' => false,
        ]);
    }
}
