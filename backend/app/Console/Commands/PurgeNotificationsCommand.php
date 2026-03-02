<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * Purge notification records older than the configured retention period.
 *
 * Env var: DATA_RETENTION_NOTIFICATIONS_DAYS (default: 90)
 * Run: daily at 05:30
 *
 * Usage:
 *   php artisan notifications:purge-old
 *   php artisan notifications:purge-old --dry-run
 */
class PurgeNotificationsCommand extends Command
{
    protected $signature = 'notifications:purge-old
                            {--dry-run : Show count without deleting}';

    protected $description = 'Delete notification records older than the retention period.';

    public function handle(): int
    {
        $days = (int) config('data_retention.notifications_days', 90);
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $count = Notification::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info('No notifications eligible for purge.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] {$count} notification(s) older than {$days} days would be purged.");

            return self::SUCCESS;
        }

        Notification::where('created_at', '<', $cutoff)->delete();
        $this->info("Purged {$count} notification record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
