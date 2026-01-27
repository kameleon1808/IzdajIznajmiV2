<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendNotificationDigestCommand extends Command
{
    protected $signature = 'notifications:digest {--frequency=daily : Frequency (daily|weekly)}';

    protected $description = 'Send notification digests to users based on their preferences';

    public function handle(): int
    {
        $frequency = $this->option('frequency');
        if (!in_array($frequency, ['daily', 'weekly'], true)) {
            $this->error('Frequency must be "daily" or "weekly"');

            return self::FAILURE;
        }

        $isDaily = $frequency === 'daily';
        $windowStart = $isDaily ? Carbon::now()->subDay() : Carbon::now()->subWeek();
        $digestType = $isDaily ? Notification::TYPE_DIGEST_DAILY : Notification::TYPE_DIGEST_WEEKLY;
        $lastDigestColumn = $isDaily ? 'last_digest_daily_at' : 'last_digest_weekly_at';

        $this->info("Processing {$frequency} digests for window starting at {$windowStart->toDateTimeString()}");

        $preferences = NotificationPreference::where('digest_enabled', true)
            ->where('digest_frequency', $frequency)
            ->with('user')
            ->get();

        $processed = 0;
        $skipped = 0;

        foreach ($preferences as $pref) {
            $lastDigestAt = $pref->$lastDigestColumn;
            $windowEnd = $lastDigestAt ? Carbon::parse($lastDigestAt) : $windowStart;

            // Skip if already processed for this window (idempotency)
            if ($lastDigestAt && Carbon::parse($lastDigestAt)->isAfter($windowStart)) {
                $skipped++;
                continue;
            }

            $notifications = Notification::where('user_id', $pref->user_id)
                ->where('created_at', '>=', $windowStart)
                ->where('created_at', '<=', Carbon::now())
                ->where('type', '!=', Notification::TYPE_DIGEST_DAILY)
                ->where('type', '!=', Notification::TYPE_DIGEST_WEEKLY)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($notifications->isEmpty()) {
                $skipped++;
                continue;
            }

            $countsByType = $notifications->groupBy('type')->map->count();
            $topItems = $notifications->take(3)->map(function ($n) {
                return [
                    'type' => $n->type,
                    'title' => $n->title,
                    'created_at' => $n->created_at->toIso8601String(),
                ];
            })->values()->all();

            $typeLabels = [
                Notification::TYPE_LISTING_NEW_MATCH => 'Saved Search Matches',
                Notification::TYPE_APPLICATION_CREATED => 'Applications',
                Notification::TYPE_APPLICATION_STATUS_CHANGED => 'Application Updates',
                Notification::TYPE_MESSAGE_RECEIVED => 'Messages',
                Notification::TYPE_RATING_RECEIVED => 'Ratings',
                Notification::TYPE_REPORT_UPDATE => 'Report Updates',
            ];

            $summaryParts = [];
            foreach ($countsByType as $type => $count) {
                $label = $typeLabels[$type] ?? ucfirst(str_replace('.', ' ', $type));
                $summaryParts[] = "{$count} {$label}";
            }

            $title = $isDaily ? 'Your Daily Digest' : 'Your Weekly Digest';
            $body = sprintf(
                'You have %d new notification%s: %s',
                $notifications->count(),
                $notifications->count() === 1 ? '' : 's',
                implode(', ', $summaryParts)
            );

            DB::transaction(function () use ($pref, $digestType, $title, $body, $topItems, $lastDigestColumn, $notifications, $countsByType) {
                Notification::create([
                    'user_id' => $pref->user_id,
                    'type' => $digestType,
                    'title' => $title,
                    'body' => $body,
                    'data' => [
                        'count' => $notifications->count(),
                        'counts_by_type' => $countsByType->toArray(),
                        'top_items' => $topItems,
                    ],
                    'url' => '/notifications',
                    'is_read' => false,
                ]);

                $pref->$lastDigestColumn = Carbon::now();
                $pref->save();
            });

            $processed++;
        }

        $this->info("Processed {$processed} digests, skipped {$skipped}");

        return self::SUCCESS;
    }
}
