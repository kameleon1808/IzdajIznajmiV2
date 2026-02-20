<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $type,
        public string $title,
        public string $body,
        public array $data = [],
        public ?string $url = null,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'url' => $this->url,
            'is_read' => false,
        ]);

        if ($this->shouldDispatchWebPush($user)) {
            SendWebPushNotificationJob::dispatch($notification->id);
        }
    }

    private function shouldDispatchWebPush(User $user): bool
    {
        if (in_array($this->type, [Notification::TYPE_DIGEST_DAILY, Notification::TYPE_DIGEST_WEEKLY], true)) {
            return false;
        }

        $preferences = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'type_settings' => NotificationPreference::defaultTypeSettings(),
                'digest_frequency' => NotificationPreference::DIGEST_NONE,
                'digest_enabled' => false,
                'push_enabled' => false,
            ]
        );

        if (! $preferences->push_enabled) {
            return false;
        }

        return PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('is_enabled', true)
            ->exists();
    }
}
