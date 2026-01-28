<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ChatSignalController extends Controller
{
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participantOrAbort($request, $conversation);
        $isTyping = filter_var($request->input('is_typing', false), FILTER_VALIDATE_BOOL);
        $ttl = (int) config('chat.typing_ttl_seconds', 8);
        $key = $this->typingKey($conversation->id, $user->id);

        if ($isTyping) {
            $expiresAt = Carbon::now()->addSeconds($ttl);
            Cache::put($key, [
                'user_id' => $user->id,
                'expires_at' => $expiresAt->toISOString(),
            ], $ttl);
        } else {
            Cache::forget($key);
        }

        return response()->json(['status' => 'ok']);
    }

    public function typingStatus(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participantOrAbort($request, $conversation);
        $ttl = (int) config('chat.typing_ttl_seconds', 8);

        $participantIds = array_values(array_filter([
            $conversation->tenant_id,
            $conversation->landlord_id,
        ], fn ($id) => $id !== $user->id));

        $participants = User::query()
            ->whereIn('id', $participantIds)
            ->get(['id', 'name']);

        $typingUsers = [];

        foreach ($participants as $participant) {
            $value = Cache::get($this->typingKey($conversation->id, $participant->id));
            if (! $value) {
                continue;
            }
            $expiresAt = Carbon::parse($value['expires_at'] ?? null);
            $expiresIn = $expiresAt ? max(0, Carbon::now()->diffInSeconds($expiresAt, false)) : $ttl;
            $typingUsers[] = [
                'id' => $participant->id,
                'name' => $participant->name,
                'expiresIn' => $expiresIn,
            ];
        }

        return response()->json([
            'users' => $typingUsers,
            'ttlSeconds' => $ttl,
        ]);
    }

    public function presencePing(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $ttl = (int) config('chat.presence_ttl_seconds', 90);
        $expiresAt = Carbon::now()->addSeconds($ttl);
        Cache::put($this->presenceKey($user->id), [
            'user_id' => $user->id,
            'expires_at' => $expiresAt->toISOString(),
        ], $ttl);

        return response()->json([
            'online' => true,
            'expiresIn' => $ttl,
        ]);
    }

    public function presenceStatus(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401, 'Unauthenticated');

        if ($viewer->id !== $user->id && ! $this->userHasRole($viewer, 'admin')) {
            $shared = Conversation::query()
                ->where(function ($query) use ($viewer, $user) {
                    $query->where('tenant_id', $viewer->id)->where('landlord_id', $user->id);
                })
                ->orWhere(function ($query) use ($viewer, $user) {
                    $query->where('tenant_id', $user->id)->where('landlord_id', $viewer->id);
                })
                ->exists();

            abort_unless($shared, 403, 'Forbidden');
        }

        $value = Cache::get($this->presenceKey($user->id));
        $online = (bool) $value;
        $expiresIn = 0;

        if ($value && isset($value['expires_at'])) {
            $expiresAt = Carbon::parse($value['expires_at']);
            $expiresIn = max(0, Carbon::now()->diffInSeconds($expiresAt, false));
        }

        return response()->json([
            'userId' => $user->id,
            'online' => $online,
            'expiresIn' => $expiresIn,
        ]);
    }

    private function typingKey(int $conversationId, int $userId): string
    {
        return sprintf('typing:%s:%s', $conversationId, $userId);
    }

    private function presenceKey(int $userId): string
    {
        return sprintf('presence:%s', $userId);
    }

    private function participantOrAbort(Request $request, Conversation $conversation): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($conversation->isParticipant($user), 403, 'Forbidden');

        return $user;
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
