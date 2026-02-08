<?php

namespace App\Services;

use App\Models\FraudScore;
use App\Models\FraudSignal;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FraudSignalService
{
    public function __construct(private NotificationService $notifications)
    {
    }

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
        if ($score >= $threshold && !$user->is_suspicious) {
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

        $key = 'fraud:failed_mfa:' . $user->id;
        $count = Cache::increment($key);
        if (!is_int($count)) {
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
                'url' => '/admin/users/' . $user->id,
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
                'url' => '/admin/users/' . $user->id,
            ]);
        }
    }
}
