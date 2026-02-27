<?php

namespace App\Console\Commands;

use App\Models\KycSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredKycDocumentsCommand extends Command
{
    protected $signature = 'kyc:purge-expired {--dry-run : List eligible submissions without deleting anything}';

    protected $description = 'Delete KYC document files from storage for submissions whose retention period has expired.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        $query = KycSubmission::with('documents')
            ->whereNotNull('purge_after')
            ->where('purge_after', '<=', $now)
            ->whereHas('documents');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired KYC submissions with documents found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] {$count} submission(s) eligible for document purge:");
            $query->chunkById(100, function ($submissions) {
                foreach ($submissions as $submission) {
                    $docCount = $submission->documents->count();
                    $this->line("  - Submission #{$submission->id} (status: {$submission->status}, purge_after: {$submission->purge_after}, documents: {$docCount})");
                }
            });

            return self::SUCCESS;
        }

        $purgedSubmissions = 0;
        $purgedFiles = 0;
        $errors = 0;

        $query->chunkById(100, function ($submissions) use (&$purgedSubmissions, &$purgedFiles, &$errors) {
            foreach ($submissions as $submission) {
                $submissionErrors = 0;

                foreach ($submission->documents as $document) {
                    if (! $document->path) {
                        continue;
                    }

                    try {
                        $disk = $document->disk ?? 'private';
                        if (Storage::disk($disk)->exists($document->path)) {
                            Storage::disk($disk)->delete($document->path);
                        }
                        $purgedFiles++;
                    } catch (\Throwable $e) {
                        $submissionErrors++;
                        $errors++;
                        Log::error('kyc.purge.file_delete_failed', [
                            'document_id' => $document->id,
                            'submission_id' => $submission->id,
                            'path' => $document->path,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($submissionErrors === 0) {
                    // Only remove DB records when all files were deleted successfully
                    $submission->documents()->delete();
                    $purgedSubmissions++;
                }
            }
        });

        $this->info("Purged documents for {$purgedSubmissions} submission(s), {$purgedFiles} file(s) deleted.");

        if ($errors > 0) {
            $this->warn("{$errors} file deletion error(s) — check logs for details.");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
