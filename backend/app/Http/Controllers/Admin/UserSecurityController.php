<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\FraudSignal;
use App\Models\User;
use App\Models\UserSession;
use App\Services\FraudSignalService;
use App\Services\SecuritySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserSecurityController extends Controller
{
    public function __construct(
        private SecuritySessionService $sessions,
        private FraudSignalService $fraudSignals
    ) {
    }

    public function overview(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $score = $this->fraudSignals->recalculateScore($user);
        $signals = FraudSignal::where('user_id', $user->id)
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(fn (FraudSignal $signal) => [
                'id' => $signal->id,
                'signalKey' => $signal->signal_key,
                'weight' => $signal->weight,
                'meta' => $signal->meta,
                'createdAt' => optional($signal->created_at)->toISOString(),
            ]);

        $sessions = $this->sessions->sessionsForUser($user)->map(fn (UserSession $session) => [
            'id' => $session->id,
            'sessionId' => $session->session_id,
            'deviceLabel' => $session->device_label,
            'ipTruncated' => $session->ip_truncated,
            'userAgent' => $session->user_agent,
            'lastActiveAt' => optional($session->last_active_at)->toISOString(),
            'createdAt' => optional($session->created_at)->toISOString(),
        ]);

        return response()->json([
            'user' => new UserResource($user->loadMissing('roles')),
            'fraudScore' => [
                'score' => $score->score,
                'lastCalculatedAt' => optional($score->last_calculated_at)->toISOString(),
            ],
            'fraudSignals' => $signals,
            'sessions' => $sessions,
        ]);
    }

    public function sessions(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $sessions = $this->sessions->sessionsForUser($user)->map(fn (UserSession $session) => [
            'id' => $session->id,
            'sessionId' => $session->session_id,
            'deviceLabel' => $session->device_label,
            'ipTruncated' => $session->ip_truncated,
            'userAgent' => $session->user_agent,
            'lastActiveAt' => optional($session->last_active_at)->toISOString(),
            'createdAt' => optional($session->created_at)->toISOString(),
        ]);

        return response()->json(['sessions' => $sessions]);
    }

    public function revokeAllSessions(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $sessionIds = UserSession::where('user_id', $user->id)->pluck('session_id')->all();
        if (!empty($sessionIds)) {
            DB::table('sessions')->whereIn('id', $sessionIds)->delete();
        }
        UserSession::where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Sessions revoked.']);
    }

    public function clearSuspicion(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $user->is_suspicious = false;
        $user->save();

        return response()->json(['message' => 'Suspicion cleared.', 'isSuspicious' => false]);
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($user->hasRole('admin') || $user->role === 'admin', 403, 'Forbidden');

        return $user;
    }
}
