<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_digest_creates_summary_notification(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'type_settings' => NotificationPreference::defaultTypeSettings(),
            'digest_frequency' => NotificationPreference::DIGEST_DAILY,
            'digest_enabled' => true,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
            'title' => 'App 1',
            'body' => 'Test',
            'created_at' => Carbon::now()->subHours(12),
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_MESSAGE_RECEIVED,
            'title' => 'Message 1',
            'body' => 'Test',
            'created_at' => Carbon::now()->subHours(6),
        ]);

        Artisan::call('notifications:digest', ['--frequency' => 'daily']);

        $digest = Notification::where('user_id', $user->id)
            ->where('type', Notification::TYPE_DIGEST_DAILY)
            ->first();

        $this->assertNotNull($digest);
        $this->assertStringContainsString('Daily Digest', $digest->title);
        $this->assertArrayHasKey('count', $digest->data);
        $this->assertGreaterThanOrEqual(2, $digest->data['count']);
    }

    public function test_digest_is_idempotent(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'type_settings' => NotificationPreference::defaultTypeSettings(),
            'digest_frequency' => NotificationPreference::DIGEST_DAILY,
            'digest_enabled' => true,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
            'title' => 'App 1',
            'body' => 'Test',
            'created_at' => Carbon::now()->subHours(12),
        ]);

        Artisan::call('notifications:digest', ['--frequency' => 'daily']);
        $firstCount = Notification::where('user_id', $user->id)
            ->where('type', Notification::TYPE_DIGEST_DAILY)
            ->count();

        Artisan::call('notifications:digest', ['--frequency' => 'daily']);
        $secondCount = Notification::where('user_id', $user->id)
            ->where('type', Notification::TYPE_DIGEST_DAILY)
            ->count();

        $this->assertSame($firstCount, $secondCount);
    }

    public function test_digest_only_for_enabled_users(): void
    {
        $enabledUser = User::factory()->create();
        $disabledUser = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $enabledUser->id,
            'type_settings' => NotificationPreference::defaultTypeSettings(),
            'digest_frequency' => NotificationPreference::DIGEST_DAILY,
            'digest_enabled' => true,
        ]);

        NotificationPreference::create([
            'user_id' => $disabledUser->id,
            'type_settings' => NotificationPreference::defaultTypeSettings(),
            'digest_frequency' => NotificationPreference::DIGEST_DAILY,
            'digest_enabled' => false,
        ]);

        Notification::create([
            'user_id' => $enabledUser->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
            'title' => 'Test',
            'body' => 'Test',
            'created_at' => Carbon::now()->subHours(12),
        ]);

        Notification::create([
            'user_id' => $disabledUser->id,
            'type' => Notification::TYPE_APPLICATION_CREATED,
            'title' => 'Test',
            'body' => 'Test',
            'created_at' => Carbon::now()->subHours(12),
        ]);

        Artisan::call('notifications:digest', ['--frequency' => 'daily']);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $enabledUser->id,
            'type' => Notification::TYPE_DIGEST_DAILY,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $disabledUser->id,
            'type' => Notification::TYPE_DIGEST_DAILY,
        ]);
    }
}
