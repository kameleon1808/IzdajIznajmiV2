<?php

namespace App\Jobs;

use App\Models\KycDocument;
use App\Models\KycSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanKycDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of times the job may be attempted.
     * ClamAV failures are transient; allow 3 tries before giving up.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying after a failure.
     */
    public int $backoff = 30;

    public function __construct(public readonly int $documentId) {}

    public function handle(): void
    {
        if (! config('kyc.av_scan_enabled')) {
            // AV scanning disabled — leave av_status as 'pending' so operators know it was never scanned.
            Log::info('kyc.av_scan.disabled', ['document_id' => $this->documentId]);

            return;
        }

        $document = KycDocument::find($this->documentId);
        if (! $document) {
            Log::warning('kyc.av_scan.document_not_found', ['document_id' => $this->documentId]);

            return;
        }

        // Skip if already scanned
        if ($document->av_status !== KycDocument::AV_PENDING) {
            return;
        }

        $disk = $document->disk ?? 'private';
        $absolutePath = $this->resolveAbsolutePath($disk, $document->path);

        if (! $absolutePath || ! file_exists($absolutePath)) {
            Log::error('kyc.av_scan.file_not_found', [
                'document_id' => $document->id,
                'path' => $document->path,
            ]);
            $document->update([
                'av_status' => KycDocument::AV_ERROR,
                'av_scanned_at' => now(),
            ]);

            return;
        }

        $result = $this->runClamscan($absolutePath);

        match ($result['status']) {
            'clean' => $this->markClean($document),
            'infected' => $this->quarantine($document, $absolutePath, $result['threat'] ?? 'unknown'),
            default => $this->markError($document, $result['error'] ?? 'unknown error'),
        };
    }

    private function runClamscan(string $absolutePath): array
    {
        $binary = escapeshellarg(config('kyc.clamscan_binary', 'clamscan'));
        $timeout = (int) config('kyc.clamscan_timeout', 60);
        $file = escapeshellarg($absolutePath);

        // --no-summary suppresses the summary line; exit code 0 = clean, 1 = infected, 2 = error
        $command = "{$binary} --no-summary {$file} 2>&1";

        $output = [];
        $exitCode = null;

        // proc_open with a timeout is safer than shell_exec for long-running processes
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        if (! is_resource($process)) {
            return ['status' => 'error', 'error' => 'Failed to start clamscan process'];
        }

        fclose($pipes[0]);

        $startTime = time();
        $stdout = '';
        while (! feof($pipes[1])) {
            if ((time() - $startTime) > $timeout) {
                proc_terminate($process, 9);
                proc_close($process);

                return ['status' => 'error', 'error' => "clamscan timed out after {$timeout}s"];
            }
            $stdout .= fread($pipes[1], 8192);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return match ($exitCode) {
            0 => ['status' => 'clean'],
            1 => ['status' => 'infected', 'threat' => trim($stdout)],
            default => ['status' => 'error', 'error' => "clamscan exited with code {$exitCode}: ".trim($stdout)],
        };
    }

    private function markClean(KycDocument $document): void
    {
        $document->update([
            'av_status' => KycDocument::AV_CLEAN,
            'av_scanned_at' => now(),
        ]);

        Log::info('kyc.av_scan.clean', [
            'document_id' => $document->id,
            'submission_id' => $document->submission_id,
        ]);
    }

    private function quarantine(KycDocument $document, string $absolutePath, string $threat): void
    {
        // Move the file to a quarantine subdirectory on the same disk so it is
        // isolated but not permanently destroyed (allows forensic review).
        $disk = $document->disk ?? 'private';
        $quarantinePath = 'kyc_quarantine/'.$document->id.'_'.basename($document->path);

        try {
            Storage::disk($disk)->move($document->path, $quarantinePath);
        } catch (\Throwable $e) {
            Log::error('kyc.av_scan.quarantine_move_failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }

        $document->update([
            'av_status' => KycDocument::AV_INFECTED,
            'av_scanned_at' => now(),
            'path' => $quarantinePath,
        ]);

        // Set the parent submission to quarantined so admins can see it
        $submission = $document->submission;
        if ($submission && in_array($submission->status, [KycSubmission::STATUS_PENDING])) {
            $submission->update([
                'status' => KycSubmission::STATUS_QUARANTINED,
                'reviewer_note' => "Quarantined by AV scan. Threat: {$threat}",
                'reviewed_at' => now(),
            ]);
        }

        Log::warning('kyc.av_scan.infected', [
            'document_id' => $document->id,
            'submission_id' => $document->submission_id,
            'threat' => $threat,
            'quarantine_path' => $quarantinePath,
        ]);
    }

    private function markError(KycDocument $document, string $error): void
    {
        $document->update([
            'av_status' => KycDocument::AV_ERROR,
            'av_scanned_at' => now(),
        ]);

        Log::error('kyc.av_scan.error', [
            'document_id' => $document->id,
            'submission_id' => $document->submission_id,
            'error' => $error,
        ]);
    }

    /**
     * Resolve the absolute filesystem path for a file stored on a local disk.
     * Returns null for non-local drivers (e.g. S3) — those would need a temp download.
     */
    private function resolveAbsolutePath(string $disk, string $relativePath): ?string
    {
        $config = config("filesystems.disks.{$disk}");
        if (! isset($config['root']) || ($config['driver'] ?? '') !== 'local') {
            return null;
        }

        return rtrim($config['root'], '/').'/'.ltrim($relativePath, '/');
    }
}
