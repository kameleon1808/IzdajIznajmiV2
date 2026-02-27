<?php

return [
    'max_file_size_kb' => (int) env('KYC_MAX_FILE_SIZE_KB', 10240),
    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],

    /**
     * Number of days after a submission reaches a terminal state (approved/rejected/withdrawn/quarantined)
     * before its documents are eligible for purging from storage.
     * Run `php artisan kyc:purge-expired` to perform the actual purge.
     */
    'document_retention_days' => (int) env('KYC_DOCUMENT_RETENTION_DAYS', 90),

    /**
     * Enable ClamAV malware scanning for uploaded KYC documents.
     * Requires clamscan to be installed and accessible on the server PATH.
     * When disabled, documents are stored with av_status = 'pending' and no scanning occurs.
     */
    'av_scan_enabled' => (bool) env('ENABLE_AV_SCAN', false),

    /**
     * Full path to the clamscan binary. Defaults to searching PATH.
     */
    'clamscan_binary' => env('CLAMSCAN_BINARY', 'clamscan'),

    /**
     * Timeout in seconds for a single clamscan invocation.
     */
    'clamscan_timeout' => (int) env('CLAMSCAN_TIMEOUT', 60),

    /**
     * Magic-byte MIME type allowlist. Files must match one of these server-detected
     * MIME types (via finfo) in addition to passing the extension/mime validator.
     */
    'allowed_magic_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ],
];
