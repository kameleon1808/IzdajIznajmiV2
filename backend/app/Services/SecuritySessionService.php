<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecuritySessionService
{
    public function recordSession(User $user, Request $request): ?UserSession
    {
        $sessionId = $request->session()->getId();
        if (!$sessionId) {
            return null;
        }

        $fingerprint = $this->deviceFingerprint($request);
        $deviceLabel = $this->deviceLabel($request);
        $ipTruncated = $this->truncateIp($request->ip());
        $userAgent = $this->normalizeUserAgent($request->userAgent());

        $session = UserSession::firstOrNew(['session_id' => $sessionId]);
        $session->user_id = $user->id;
        $session->device_fingerprint = $fingerprint;
        $session->device_label = $deviceLabel;
        $session->ip_truncated = $ipTruncated;
        $session->user_agent = $userAgent;
        $session->last_active_at = now();
        $session->updated_at = now();
        if (!$session->created_at) {
            $session->created_at = now();
        }
        $session->save();

        return $session;
    }

    public function touchSession(Request $request): void
    {
        $sessionId = $request->session()->getId();
        $user = $request->user();
        if (!$sessionId || !$user) {
            return;
        }

        UserSession::where('session_id', $sessionId)
            ->where('user_id', $user->id)
            ->update([
                'last_active_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function deviceFingerprint(Request $request): ?string
    {
        $deviceId = trim((string) $request->header('X-Device-Id'));
        $userAgent = (string) ($request->userAgent() ?? '');
        $ip = (string) ($this->truncateIp($request->ip()) ?? '');
        $raw = implode('|', array_filter([$deviceId, $userAgent, $ip], fn ($value) => $value !== ''));

        return $raw ? hash('sha256', $raw) : null;
    }

    public function deviceLabel(Request $request): ?string
    {
        $label = trim((string) $request->header('X-Device-Label'));
        if ($label === '') {
            return null;
        }

        return Str::of($label)->limit(120, '')->toString();
    }

    public function truncateIp(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts) . '/24';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $segments = explode(':', $ip);
            $prefix = implode(':', array_slice($segments, 0, 4));
            return $prefix . '::/64';
        }

        return null;
    }

    public function normalizeUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        return Str::of($userAgent)->limit(512, '')->toString();
    }

    public function isTrustedDevice(User $user, Request $request): bool
    {
        $fingerprint = $this->deviceFingerprint($request);
        if (!$fingerprint) {
            return false;
        }

        return TrustedDevice::where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function rememberDevice(User $user, Request $request, ?string $label = null): ?TrustedDevice
    {
        $fingerprint = $this->deviceFingerprint($request);
        if (!$fingerprint) {
            return null;
        }

        $ttlDays = (int) config('security.trusted_device_ttl_days', 30);
        $expiresAt = now()->addDays(max($ttlDays, 1));

        $device = TrustedDevice::firstOrNew([
            'user_id' => $user->id,
            'device_fingerprint' => $fingerprint,
        ]);

        $device->label = $label ?? $device->label;
        $device->last_used_at = now();
        $device->expires_at = $expiresAt;
        if (!$device->created_at) {
            $device->created_at = now();
        }
        $device->save();

        return $device;
    }

    public function revokeSession(UserSession $session): void
    {
        DB::table('sessions')->where('id', $session->session_id)->delete();
        $session->delete();
    }

    public function revokeOtherSessions(User $user, string $currentSessionId): int
    {
        $otherSessionIds = UserSession::where('user_id', $user->id)
            ->where('session_id', '!=', $currentSessionId)
            ->pluck('session_id')
            ->all();

        if (empty($otherSessionIds)) {
            return 0;
        }

        DB::table('sessions')->whereIn('id', $otherSessionIds)->delete();
        UserSession::where('user_id', $user->id)
            ->whereIn('session_id', $otherSessionIds)
            ->delete();

        return count($otherSessionIds);
    }

    public function sessionsForUser(User $user)
    {
        return UserSession::where('user_id', $user->id)->orderByDesc('last_active_at')->get();
    }
}
