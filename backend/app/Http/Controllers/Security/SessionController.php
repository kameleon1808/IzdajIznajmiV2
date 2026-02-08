<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use App\Services\SecuritySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(private SecuritySessionService $sessions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $currentSessionId = $request->session()->getId();
        $sessions = $this->sessions->sessionsForUser($user);

        $payload = $sessions->map(fn (UserSession $session) => [
            'id' => $session->id,
            'sessionId' => $session->session_id,
            'deviceLabel' => $session->device_label,
            'ipTruncated' => $session->ip_truncated,
            'userAgent' => $session->user_agent,
            'lastActiveAt' => optional($session->last_active_at)->toISOString(),
            'createdAt' => optional($session->created_at)->toISOString(),
            'isCurrent' => $session->session_id === $currentSessionId,
        ]);

        return response()->json([
            'currentSessionId' => $currentSessionId,
            'sessions' => $payload,
        ]);
    }

    public function revoke(Request $request, UserSession $session): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($session->user_id === $user->id, 403, 'Forbidden');

        $currentSessionId = $request->session()->getId();
        $this->sessions->revokeSession($session);

        if ($currentSessionId === $session->session_id) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Session revoked.']);
    }

    public function revokeOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $currentSessionId = $request->session()->getId();
        $revoked = $this->sessions->revokeOtherSessions($user, $currentSessionId);

        return response()->json(['revoked' => $revoked]);
    }
}
