<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Jobs\DispatchNotificationJob;

class NotificationService
{
    public function createNotification(User $recipient, string $type, array $payload): void
    {
        $preferences = $this->getPreferences($recipient);
        $typeSettings = $preferences->type_settings ?? NotificationPreference::defaultTypeSettings();

        if (array_key_exists($type, $typeSettings) && $typeSettings[$type] === false) {
            return;
        }

        // Run synchronously so notifications are created immediately even when no queue worker is running
        DispatchNotificationJob::dispatchSync(
            $recipient->id,
            $type,
            $payload['title'] ?? '',
            $payload['body'] ?? '',
            $payload['data'] ?? [],
            $payload['url'] ?? null
        );
    }

    public function getPreferences(User $user): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'type_settings' => NotificationPreference::defaultTypeSettings(),
                'digest_frequency' => NotificationPreference::DIGEST_NONE,
                'digest_enabled' => false,
            ]
        );
    }
}

