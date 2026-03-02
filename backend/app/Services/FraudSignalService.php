<?php

namespace App\Services;

use App\Models\FraudScore;
use App\Models\FraudSignal;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FraudSignalService
{
    public function __construct(private NotificationService $notifications) {}

    public function recordSignal(User $user, string $key, int $weight, array $meta = [], ?int $cooldownMinutes = null): ?FraudSignal
    {
        if ($cooldownMinutes) {
            $recent = FraudSignal::where('user_id', $user->id)
                ->where('signal_key', $key)
                ->where('created_at', '>=', now()->subMinutes($cooldownMinutes))
                ->exists();
            if ($recent) {
                return null;
            }
        }

        $signal = FraudSignal::create([
            'user_id' => $user->id,
            'signal_key' => $key,
            'weight' => $weight,
            'meta' => $meta,
            'created_at' => now(),
        ]);

        $this->recalculateScore($user);

        return $signal;
    }

    public function recalculateScore(User $user): FraudScore
    {
        $windowDays = (int) config('security.fraud.window_days', 30);
        $since = now()->subDays(max($windowDays, 1));
        $score = (int) FraudSignal::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->sum('weight');

        $fraudScore = FraudScore::updateOrCreate(
            ['user_id' => $user->id],
            ['score' => $score, 'last_calculated_at' => now()]
        );

        $threshold = (int) config('security.fraud.score_threshold', 60);
        if ($score >= $threshold && ! $user->is_suspicious) {
            $user->is_suspicious = true;
            $user->save();
            $this->notifyAdmins($user, $score);
        }

        return $fraudScore;
    }

    public function recordFailedMfaAttempt(User $user): void
    {
        $settings = config('security.fraud.signals.failed_mfa');
        $threshold = (int) ($settings['threshold'] ?? 3);
        $windowMinutes = (int) ($settings['window_minutes'] ?? 10);
        $cooldown = (int) ($settings['cooldown_minutes'] ?? 30);
        $weight = (int) ($settings['weight'] ?? 8);

        $key = 'fraud:failed_mfa:'.$user->id;
        $count = Cache::increment($key);
        if (! is_int($count)) {
            $count = (int) Cache::get($key, 0) + 1;
        }
        Cache::put($key, $count, now()->addMinutes($windowMinutes));

        if ($count >= $threshold) {
            $signal = $this->recordSignal($user, 'failed_mfa', $weight, ['count' => $count], $cooldown);
            if ($signal) {
                $this->notifyAdminsOfSignal($user, 'Failed MFA attempts', [
                    'signal' => 'failed_mfa',
                    'count' => $count,
                ]);
            }
        }
    }

    public function recordFailedMfaRateLimit(User $user, array $meta = []): void
    {
        $settings = config('security.fraud.signals.failed_mfa');
        $cooldown = (int) ($settings['cooldown_minutes'] ?? 30);
        $weight = (int) ($settings['weight'] ?? 8);

        $signal = $this->recordSignal($user, 'failed_mfa_rate_limited', $weight, $meta, $cooldown);
        if ($signal) {
            $this->notifyAdminsOfSignal($user, 'MFA rate limit reached', [
                'signal' => 'failed_mfa_rate_limited',
                'count' => $meta['count'] ?? null,
            ]);
        }
    }

    /**
     * Record an IP-level failed login anomaly.
     *
     * Tracks failed login attempts from the same IP in a rolling window.
     * When the threshold is exceeded the event is written to the structured
     * security log and sent to Sentry. Because no authenticated user is
     * involved we cannot create a user-scoped FraudSignal here.
     */
    public function recordFailedLoginIp(string $ip): void
    {
        $settings = config('security.fraud.signals.failed_login_ip', []);
        $threshold = (int) ($settings['threshold'] ?? 20);
        $windowMinutes = (int) ($settings['window_minutes'] ?? 10);

        $cacheKey = 'fraud:failed_login_ip:'.hash('sha256', $ip);
        $count = Cache::increment($cacheKey);
        if (! is_int($count)) {
            $count = (int) Cache::get($cacheKey, 0) + 1;
        }
        Cache::put($cacheKey, $count, now()->addMinutes($windowMinutes));

        if ($count === $threshold) {
            Log::channel('structured')->warning('security.ip_failed_logins_threshold', [
                'action' => 'security.ip_failed_logins_threshold',
                'severity' => 'warning',
                'security_event' => true,
                'ip_hash' => hash('sha256', $ip),
                'attempt_count' => $count,
                'window_minutes' => $windowMinutes,
            ]);
        }
    }

    /**
     * Record a KYC multi-user IP signal on the submitting user.
     *
     * When the same IP is used to submit KYC for multiple distinct users
     * within the configured window we flag the submitting user with a
     * fraud signal and notify admins.
     */
    public function recordKycMultiUserIp(User $user, string $ip): void
    {
        $settings = config('security.fraud.signals.kyc_multi_user_ip', []);
        $threshold = (int) ($settings['threshold'] ?? 2);
        $windowHours = (int) ($settings['window_hours'] ?? 24);
        $cooldown = (int) ($settings['cooldown_minutes'] ?? 240);
        $weight = (int) ($settings['weight'] ?? 20);

        $ipHash = hash('sha256', $ip);
        $cacheKey = 'fraud:kyc_submit_ip:'.$ipHash;

        $userIds = Cache::get($cacheKey, []);
        if (! in_array($user->id, $userIds, true)) {
            $userIds[] = $user->id;
        }
        Cache::put($cacheKey, $userIds, now()->addHours($windowHours));

        if (count($userIds) >= $threshold) {
            $signal = $this->recordSignal($user, 'kyc_multi_user_ip', $weight, [
                'ip_hash' => $ipHash,
                'distinct_users' => count($userIds),
            ], $cooldown);

            if ($signal) {
                $this->notifyAdminsOfSignal($user, 'KYC from shared IP', [
                    'signal' => 'kyc_multi_user_ip',
                    'ip_hash' => $ipHash,
                    'distinct_users' => count($userIds),
                ]);
            }
        }
    }

    /**
     * Record a rapid chat-attachment upload signal on the authenticated user.
     *
     * Called when the ChatAttachmentRateLimit middleware blocks a request.
     */
    public function recordRapidUploads(User $user, array $meta = []): void
    {
        $settings = config('security.fraud.signals.rapid_uploads', []);
        $cooldown = (int) ($settings['cooldown_minutes'] ?? 60);
        $weight = (int) ($settings['weight'] ?? 5);

        $signal = $this->recordSignal($user, 'rapid_uploads', $weight, $meta, $cooldown);
        if ($signal) {
            $this->notifyAdminsOfSignal($user, 'Rapid file uploads blocked', [
                'signal' => 'rapid_uploads',
            ]);
        }
    }

    private function notifyAdmins(User $user, int $score): void
    {
        $admins = User::query()
            ->where('role', 'admin')
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->get();

        foreach ($admins as $admin) {
            $this->notifications->createNotification($admin, Notification::TYPE_ADMIN_NOTICE, [
                'title' => 'Fraud risk flagged',
                'body' => sprintf('User %s (ID %d) reached fraud score %d.', $user->full_name ?? $user->name ?? 'User', $user->id, $score),
                'data' => [
                    'userId' => $user->id,
                    'score' => $score,
                    'signal' => 'fraud_threshold',
                ],
                'url' => '/admin/users/'.$user->id,
            ]);
        }
    }

    private function notifyAdminsOfSignal(User $user, string $title, array $data = []): void
    {
        $admins = User::query()
            ->where('role', 'admin')
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->get();

        foreach ($admins as $admin) {
            $this->notifications->createNotification($admin, Notification::TYPE_ADMIN_NOTICE, [
                'title' => $title,
                'body' => sprintf('User %s (ID %d) triggered %s.', $user->full_name ?? $user->name ?? 'User', $user->id, $data['signal'] ?? 'fraud signal'),
                'data' => array_merge([
                    'userId' => $user->id,
                ], $data),
                'url' => '/admin/users/'.$user->id,
            ]);
        }
    }
}
