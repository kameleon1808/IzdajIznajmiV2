<?php

namespace App\Console\Commands;

use App\Models\ChatAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Purge chat attachment files (and their DB records) that are older than the
 * configured retention period.
 *
 * Env var: DATA_RETENTION_CHAT_ATTACHMENTS_DAYS (default: 365)
 * Run: daily at 05:00
 *
 * Usage:
 *   php artisan attachments:purge-old
 *   php artisan attachments:purge-old --dry-run
 */
class PurgeChatAttachmentsCommand extends Command
{
    protected $signature = 'attachments:purge-old
                            {--dry-run : List eligible attachments without deleting anything}';

    protected $description = 'Delete chat attachment files and records older than the retention period.';

    public function handle(): int
    {
        $days = (int) config('data_retention.chat_attachments_days', 365);
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $query = ChatAttachment::where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info('No chat attachments eligible for purge.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] {$count} chat attachment(s) older than {$days} days would be purged.");

            return self::SUCCESS;
        }

        $deletedFiles = 0;
        $deletedRecords = 0;
        $errors = 0;

        $query->chunkById(200, function ($attachments) use (&$deletedFiles, &$deletedRecords, &$errors) {
            foreach ($attachments as $attachment) {
                $disk = $attachment->disk ?? 'private';
                $fileErrors = 0;

                foreach ([$attachment->path_original, $attachment->path_thumb] as $path) {
                    if (! $path) {
                        continue;
                    }

                    try {
                        if (Storage::disk($disk)->exists($path)) {
                            Storage::disk($disk)->delete($path);
                        }
                        $deletedFiles++;
                    } catch (\Throwable $e) {
                        $fileErrors++;
                        $errors++;
                        Log::error('attachments.purge.file_delete_failed', [
                            'attachment_id' => $attachment->id,
                            'path' => $path,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($fileErrors === 0) {
                    $attachment->delete();
                    $deletedRecords++;
                }
            }
        });

        $this->info("Purged {$deletedRecords} chat attachment record(s), {$deletedFiles} file(s) deleted.");

        if ($errors > 0) {
            $this->warn("{$errors} file deletion error(s) — check logs for details.");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
