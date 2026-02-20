<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $items = PushSubscription::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'data' => $items->map(fn (PushSubscription $subscription) => $this->serialize($subscription))->all(),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:5000'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:5000'],
            'keys.auth' => ['required', 'string', 'max:1000'],
            'deviceLabel' => ['nullable', 'string', 'max:255'],
        ]);

        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $user->id,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'device_label' => $data['deviceLabel'] ?? null,
                'user_agent' => $request->userAgent(),
                'is_enabled' => true,
            ]
        );

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
            $preferences->push_enabled = true;
            $preferences->save();
        }

        return response()->json([
            'data' => $this->serialize($subscription->fresh()),
        ], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:5000'],
        ]);

        $subscription = PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('endpoint', $data['endpoint'])
            ->first();

        if ($subscription) {
            $subscription->forceFill(['is_enabled' => false])->save();
        }

        $hasEnabled = PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('is_enabled', true)
            ->exists();

        if (! $hasEnabled) {
            $preferences = NotificationPreference::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'type_settings' => NotificationPreference::defaultTypeSettings(),
                    'digest_frequency' => NotificationPreference::DIGEST_NONE,
                    'digest_enabled' => false,
                    'push_enabled' => false,
                ]
            );

            if ($preferences->push_enabled) {
                $preferences->push_enabled = false;
                $preferences->save();
            }
        }

        return response()->json([
            'message' => 'Push subscription disabled',
        ]);
    }

    private function serialize(PushSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'endpoint' => $subscription->endpoint,
            'keys' => [
                'p256dh' => $subscription->p256dh,
                'auth' => $subscription->auth,
            ],
            'deviceLabel' => $subscription->device_label,
            'userAgent' => $subscription->user_agent,
            'isEnabled' => $subscription->is_enabled,
            'createdAt' => $subscription->created_at?->toIso8601String(),
            'updatedAt' => $subscription->updated_at?->toIso8601String(),
        ];
    }
}
