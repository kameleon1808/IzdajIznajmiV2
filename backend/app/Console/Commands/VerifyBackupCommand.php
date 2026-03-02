<?php

namespace App\Console\Commands;

use App\Services\SentryReporter;
use App\Services\StructuredLogger;
use Illuminate\Console\Command;

/**
 * Verify that the most recent PostgreSQL backup exists and is not stale.
 *
 * Checks BACKUP_DIR for the newest *.sql.gz file. If a matching *.sha256
 * checksum file exists, it is verified with sha256sum.
 *
 * Configuration env vars:
 *   BACKUP_DIR                     (default: /var/backups/izdaji/postgres)
 *   BACKUP_STALENESS_ALERT_HOURS   (default: 26 — allows for a 2-hour late-run window)
 *
 * Run: daily at 06:00 (after the backup cron which typically runs at 03:00)
 *
 * Usage:
 *   php artisan backup:verify
 *   php artisan backup:verify --backup-dir=/custom/path
 */
class VerifyBackupCommand extends Command
{
    protected $signature = 'backup:verify
                            {--backup-dir= : Override the backup directory}';

    protected $description = 'Verify that the latest PostgreSQL backup exists and is not stale.';

    public function __construct(
        private readonly StructuredLogger $log,
        private readonly SentryReporter $sentry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dir = $this->option('backup-dir')
            ?? env('BACKUP_DIR', '/var/backups/izdaji/postgres');

        $stalenessHours = (int) env('BACKUP_STALENESS_ALERT_HOURS', 26);

        if (! is_dir($dir)) {
            $this->alert("Backup directory does not exist: {$dir}");
            $this->log->error('backup.verify_failed', [
                'severity' => 'error',
                'security_event' => true,
                'reason' => 'backup_dir_missing',
                'backup_dir' => $dir,
            ]);
            $this->sentry->captureMessage('Backup directory missing', 'error', [
                'backup_dir' => $dir,
                'flow' => 'backup_verify',
            ]);

            return self::FAILURE;
        }

        $backupFile = $this->findLatestBackup($dir);

        if ($backupFile === null) {
            $this->error("No backup file (*.sql.gz) found in {$dir}");
            $this->log->error('backup.verify_failed', [
                'severity' => 'error',
                'security_event' => true,
                'reason' => 'no_backup_found',
                'backup_dir' => $dir,
            ]);
            $this->sentry->captureMessage('No backup file found', 'error', [
                'backup_dir' => $dir,
                'flow' => 'backup_verify',
            ]);

            return self::FAILURE;
        }

        $modifiedAt = filemtime($backupFile);
        $ageHours = (time() - $modifiedAt) / 3600;

        if ($ageHours > $stalenessHours) {
            $this->error(sprintf(
                'Latest backup is stale (%.1f hours old, threshold: %d hours): %s',
                $ageHours,
                $stalenessHours,
                basename($backupFile)
            ));
            $this->log->error('backup.stale', [
                'severity' => 'error',
                'security_event' => true,
                'reason' => 'stale_backup',
                'backup_file' => basename($backupFile),
                'age_hours' => round($ageHours, 2),
                'threshold_hours' => $stalenessHours,
            ]);
            $this->sentry->captureMessage('Backup is stale', 'error', [
                'backup_file' => basename($backupFile),
                'age_hours' => round($ageHours, 2),
                'threshold_hours' => $stalenessHours,
                'flow' => 'backup_verify',
            ]);

            return self::FAILURE;
        }

        // Verify checksum if a *.sha256 sidecar exists.
        $checksumOk = $this->verifyChecksum($backupFile);
        if ($checksumOk === false) {
            $this->error('Backup checksum verification failed: '.basename($backupFile));
            $this->log->error('backup.checksum_failed', [
                'severity' => 'error',
                'security_event' => true,
                'reason' => 'checksum_mismatch',
                'backup_file' => basename($backupFile),
            ]);
            $this->sentry->captureMessage('Backup checksum mismatch', 'critical', [
                'backup_file' => basename($backupFile),
                'flow' => 'backup_verify',
            ]);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Backup OK — %s (%.1f hours old%s)',
            basename($backupFile),
            $ageHours,
            $checksumOk ? ', checksum verified' : ''
        ));

        $this->log->info('backup.verified', [
            'backup_file' => basename($backupFile),
            'age_hours' => round($ageHours, 2),
            'checksum_verified' => $checksumOk,
        ]);

        return self::SUCCESS;
    }

    private function findLatestBackup(string $dir): ?string
    {
        $files = glob($dir.'/*.sql.gz');
        if (! $files) {
            return null;
        }

        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        return $files[0];
    }

    /**
     * Verify the SHA-256 checksum of $backupFile using its *.sha256 sidecar.
     *
     * Returns true  — checksum matches
     *         null  — no sidecar file (skip verification)
     *         false — checksum mismatch or error
     */
    private function verifyChecksum(string $backupFile): ?bool
    {
        $checksumFile = $backupFile.'.sha256';
        if (! file_exists($checksumFile)) {
            return null;
        }

        $expected = trim((string) file_get_contents($checksumFile));
        // sha256sum output format: "<hash>  <filename>"
        // Extract just the hash portion.
        $expected = explode(' ', $expected)[0];

        $actual = hash_file('sha256', $backupFile);
        if ($actual === false) {
            return false;
        }

        return hash_equals($expected, $actual);
    }
}
