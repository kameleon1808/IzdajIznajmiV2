<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserSession;
use App\Services\FraudSignalService;
use App\Services\SecuritySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private SecuritySessionService $sessions,
        private FraudSignalService $fraudSignals
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = $this->normalizeRole($data['role'] ?? 'seeker');
        $addressBook = $data['address_book'] ?? null;
        if (is_string($addressBook)) {
            $decoded = json_decode($addressBook, true);
            $addressBook = is_array($decoded) ? $decoded : null;
        }

        $user = User::create([
            'name' => $data['name'] ?? $data['full_name'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address_book' => $addressBook,
            'role' => $role,
            'password' => Hash::make($data['password']),
        ])->refresh();

        Role::findOrCreate($role, 'web');
        $user->syncRoles([$role]);

        Auth::login($user);
        $request->session()->regenerate();
        $this->sessions->recordSession($user, $request);

        return response()->json(['user' => new UserResource($user->fresh('roles'))], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $request->session()->regenerate();

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $this->sessions->recordSession($user, $request);
        $this->recordSessionAnomaly($user);

        if ($user->mfa_enabled && $user->mfa_confirmed_at && !$this->sessions->isTrustedDevice($user, $request)) {
            $challengeId = (string) Str::uuid();
            $request->session()->put('mfa_pending', true);
            $request->session()->put('mfa_challenge_id', $challengeId);

            return response()->json([
                'mfa_required' => true,
                'challenge_id' => $challengeId,
            ], 202);
        }

        $request->session()->forget('mfa_pending');
        $request->session()->put('mfa_verified_at', now()->toISOString());

        if ($user->mfa_enabled && $user->mfa_confirmed_at) {
            $this->sessions->rememberDevice($user, $request);
        }

        return response()->json(['user' => new UserResource($user->load('roles'))]);
    }

    public function logout(Request $request): Response
    {
        $sessionId = $request->session()->getId();
        Auth::guard('web')->logout();

        $request->session()->forget(['impersonator_id', 'impersonated_id']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($sessionId) {
            UserSession::where('session_id', $sessionId)->delete();
        }

        $response = response()->noContent();
        $response->withCookie(Cookie::forget(config('session.cookie')));
        $response->withCookie(Cookie::forget('XSRF-TOKEN'));

        return $response;
    }

    public function me(Request $request): JsonResponse
    {
        $impersonatedId = $request->session()->get('impersonated_id');
        $user = $impersonatedId ? User::find($impersonatedId)?->load('roles') : $request->user()?->load('roles');
        $impersonatorId = $request->session()->get('impersonator_id');
        $impersonator = $impersonatorId ? User::find($impersonatorId)?->load('roles') : null;

        if ($request->session()->get('mfa_pending')) {
            return response()->json([
                'mfa_required' => true,
                'challenge_id' => $request->session()->get('mfa_challenge_id'),
            ], 202);
        }

        return response()->json([
            'user' => $user ? new UserResource($user) : null,
            'impersonating' => (bool) $impersonatorId,
            'impersonator' => $impersonator ? new UserResource($impersonator) : null,
        ]);
    }

    private function recordSessionAnomaly(User $user): void
    {
        $settings = config('security.fraud.signals.session_anomaly', []);
        $threshold = (int) ($settings['threshold'] ?? 3);
        $windowHours = (int) ($settings['window_hours'] ?? 24);
        $weight = (int) ($settings['weight'] ?? 6);

        if ($threshold <= 1) {
            return;
        }

        $since = now()->subHours($windowHours);
        $distinctIps = $user->userSessions()
            ->whereNotNull('ip_truncated')
            ->where('created_at', '>=', $since)
            ->distinct('ip_truncated')
            ->count('ip_truncated');
        $distinctAgents = $user->userSessions()
            ->whereNotNull('user_agent')
            ->where('created_at', '>=', $since)
            ->distinct('user_agent')
            ->count('user_agent');

        if ($distinctIps >= $threshold || $distinctAgents >= $threshold) {
            $cooldown = max($windowHours, 1) * 60;
            $this->fraudSignals->recordSignal(
                $user,
                'session_anomaly',
                $weight,
                ['distinctIps' => $distinctIps, 'distinctAgents' => $distinctAgents],
                $cooldown
            );
        }
    }

    private function normalizeRole(string $role): string
    {
        $normalized = $role === 'tenant' ? 'seeker' : $role;

        return in_array($normalized, ['admin', 'landlord', 'seeker'], true) ? $normalized : 'seeker';
    }
}
