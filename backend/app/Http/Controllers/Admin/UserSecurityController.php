<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\FraudSignal;
use App\Models\LandlordMetric;
use App\Models\User;
use App\Models\UserSession;
use App\Services\BadgeService;
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

        $landlordMetrics = null;
        $landlordBadges = null;
        if ($this->isLandlord($user)) {
            $metrics = LandlordMetric::where('landlord_id', $user->id)->first();
            $landlordMetrics = $metrics ? [
                'avgRating30d' => $metrics->avg_rating_30d !== null ? (float) $metrics->avg_rating_30d : null,
                'allTimeAvgRating' => $metrics->all_time_avg_rating !== null ? (float) $metrics->all_time_avg_rating : null,
                'ratingsCount' => $metrics->ratings_count,
                'medianResponseTimeMinutes' => $metrics->median_response_time_minutes,
                'completedTransactionsCount' => $metrics->completed_transactions_count,
                'updatedAt' => optional($metrics->updated_at)->toISOString(),
            ] : null;

            $landlordBadges = [
                'badges' => app(BadgeService::class)->badgesFor($user, $metrics),
                'override' => $user->badge_override_json,
                'suppressed' => (bool) $user->is_suspicious,
            ];
        }

        return response()->json([
            'user' => new UserResource($user->loadMissing('roles')),
            'fraudScore' => [
                'score' => $score->score,
                'lastCalculatedAt' => optional($score->last_calculated_at)->toISOString(),
            ],
            'fraudSignals' => $signals,
            'sessions' => $sessions,
            'landlordMetrics' => $landlordMetrics,
            'landlordBadges' => $landlordBadges,
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

    public function updateBadgeOverride(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($this->isLandlord($user), 422, 'User is not a landlord.');

        $data = $request->validate([
            'topLandlord' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('topLandlord', $data) && $data['topLandlord'] !== null) {
            $user->badge_override_json = [BadgeService::BADGE_TOP_LANDLORD => (bool) $data['topLandlord']];
        } else {
            $user->badge_override_json = null;
        }

        $user->save();

        $metrics = LandlordMetric::where('landlord_id', $user->id)->first();

        return response()->json([
            'badges' => app(BadgeService::class)->badgesFor($user, $metrics),
            'override' => $user->badge_override_json,
            'suppressed' => (bool) $user->is_suspicious,
        ]);
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($user->hasRole('admin') || $user->role === 'admin', 403, 'Forbidden');

        return $user;
    }

    private function isLandlord(User $user): bool
    {
        return (method_exists($user, 'hasRole') && $user->hasRole('landlord')) || $user->role === 'landlord';
    }
}
