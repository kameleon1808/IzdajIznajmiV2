<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class SendWebPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $notificationId) {}

    public function handle(): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if (! $notification) {
            return;
        }

        $preferences = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $notification->user_id],
            [
                'type_settings' => NotificationPreference::defaultTypeSettings(),
                'digest_frequency' => NotificationPreference::DIGEST_NONE,
                'digest_enabled' => false,
                'push_enabled' => false,
            ]
        );

        if (! $this->shouldSendPush($notification, $preferences)) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->where('user_id', $notification->user_id)
            ->where('is_enabled', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $vapidPublicKey = (string) config('push.vapid.public_key', '');
        $vapidPrivateKey = (string) config('push.vapid.private_key', '');
        $vapidSubject = (string) config('push.vapid.subject', '');

        if ($vapidPublicKey === '' || $vapidPrivateKey === '' || $vapidSubject === '') {
            Log::warning('push_vapid_not_configured', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
            ]);

            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => $vapidSubject,
                'publicKey' => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ],
        ];

        $options = [
            'TTL' => (int) config('push.notification.ttl', 3600),
        ];

        $webPush = new WebPush($auth, $options);
        $payload = $this->buildPayload($notification);

        foreach ($subscriptions as $subscription) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->p256dh,
                        'authToken' => $subscription->auth,
                    ]),
                    $payload
                );
            } catch (Throwable $e) {
                Log::warning('push_queue_failed', [
                    'notification_id' => $notification->id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = (string) $report->getRequest()->getUri();
            $statusCode = $report->getResponse()?->getStatusCode();

            if ($report->isSuccess()) {
                continue;
            }

            Log::warning('push_send_failed', [
                'notification_id' => $notification->id,
                'endpoint' => $endpoint,
                'status' => $statusCode,
                'reason' => $report->getReason(),
            ]);

            if (in_array($statusCode, [404, 410], true)) {
                PushSubscription::query()
                    ->where('endpoint', $endpoint)
                    ->update(['is_enabled' => false]);
            }
        }
    }

    private function shouldSendPush(Notification $notification, NotificationPreference $preferences): bool
    {
        if (! $preferences->push_enabled) {
            return false;
        }

        if (in_array($notification->type, [Notification::TYPE_DIGEST_DAILY, Notification::TYPE_DIGEST_WEEKLY], true)) {
            return false;
        }

        $typeSettings = $preferences->type_settings ?? NotificationPreference::defaultTypeSettings();
        if (array_key_exists($notification->type, $typeSettings) && $typeSettings[$notification->type] === false) {
            return false;
        }

        return true;
    }

    private function buildPayload(Notification $notification): string
    {
        $url = $notification->url ?: '/notifications';
        if ($url[0] !== '/' && ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = '/'.$url;
        }

        return (string) json_encode([
            'title' => $notification->title,
            'body' => $notification->body,
            'icon' => (string) config('push.notification.icon', '/vite.svg'),
            'badge' => (string) config('push.notification.badge', '/vite.svg'),
            'url' => $url,
            'data' => array_merge($notification->data ?? [], [
                'notificationId' => $notification->id,
                'type' => $notification->type,
            ]),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
