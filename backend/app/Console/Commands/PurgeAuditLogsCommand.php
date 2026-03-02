<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Purge audit log records older than the configured retention period.
 *
 * Env var: DATA_RETENTION_AUDIT_LOGS_DAYS (default: 730 — 2 years)
 * Run: daily at 05:15
 *
 * Usage:
 *   php artisan audit-logs:purge-old
 *   php artisan audit-logs:purge-old --dry-run
 */
class PurgeAuditLogsCommand extends Command
{
    protected $signature = 'audit-logs:purge-old
                            {--dry-run : Show count without deleting}';

    protected $description = 'Delete audit log records older than the retention period.';

    public function handle(): int
    {
        $days = (int) config('data_retention.audit_logs_days', 730);
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $count = AuditLog::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info('No audit log records eligible for purge.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] {$count} audit log record(s) older than {$days} days would be purged.");

            return self::SUCCESS;
        }

        AuditLog::where('created_at', '<', $cutoff)->delete();
        $this->info("Purged {$count} audit log record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
