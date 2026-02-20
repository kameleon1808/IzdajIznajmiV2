<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $preferences = NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'type_settings' => NotificationPreference::defaultTypeSettings(),
                'digest_frequency' => NotificationPreference::DIGEST_NONE,
                'digest_enabled' => false,
                'push_enabled' => false,
            ]
        );

        $defaults = NotificationPreference::defaultTypeSettings();
        $merged = array_merge($defaults, $preferences->type_settings ?? []);
        if ($merged !== ($preferences->type_settings ?? [])) {
            $preferences->type_settings = $merged;
            $preferences->save();
        }

        return response()->json($preferences);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'type_settings' => ['nullable', 'array'],
            'type_settings.*' => ['boolean'],
            'digest_frequency' => ['required', 'in:none,daily,weekly'],
            'digest_enabled' => ['required', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
        ]);

        $preferences = NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'type_settings' => NotificationPreference::defaultTypeSettings(),
                'digest_frequency' => NotificationPreference::DIGEST_NONE,
                'digest_enabled' => false,
                'push_enabled' => false,
            ]
        );

        if (isset($data['type_settings'])) {
            $preferences->type_settings = array_merge(
                NotificationPreference::defaultTypeSettings(),
                $data['type_settings']
            );
        }

        $preferences->digest_frequency = $data['digest_frequency'];
        $preferences->digest_enabled = $data['digest_enabled'];
        if (array_key_exists('push_enabled', $data)) {
            $preferences->push_enabled = $data['push_enabled'];
        }
        $preferences->save();

        return response()->json($preferences->fresh());
    }
}
