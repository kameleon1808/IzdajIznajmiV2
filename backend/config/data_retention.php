<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data Retention Periods
    |--------------------------------------------------------------------------
    |
    | All values are in days. Set an env var to override a default.
    | Set a value to 0 to disable automatic purging for that data type.
    |
    */

    // Chat attachment files (private storage) + DB records.
    // Scheduled: daily at 05:00 via `php artisan attachments:purge-old`
    'chat_attachments_days' => (int) env('DATA_RETENTION_CHAT_ATTACHMENTS_DAYS', 365),

    // Audit log DB records (no files).
    // Scheduled: daily at 05:15 via `php artisan audit-logs:purge-old`
    'audit_logs_days' => (int) env('DATA_RETENTION_AUDIT_LOGS_DAYS', 730),

    // Notification DB records.
    // Scheduled: daily at 05:30 via `php artisan notifications:purge-old`
    'notifications_days' => (int) env('DATA_RETENTION_NOTIFICATIONS_DAYS', 90),

    // KYC document files — retention is managed per-submission via `purge_after`
    // timestamp on the kyc_submissions table.
    // Scheduled: daily at 04:00 via `php artisan kyc:purge-expired`
    // Configured via KYC_DOCUMENT_RETENTION_DAYS (see config/kyc.php).

];
