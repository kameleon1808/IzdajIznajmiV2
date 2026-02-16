<?php

return [
    'require_mfa_for_admins' => (bool) env('REQUIRE_MFA_FOR_ADMINS', false),
    'trusted_device_ttl_days' => (int) env('TRUSTED_DEVICE_TTL_DAYS', 30),
    'headers' => [
        'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),
        'x_content_type_options' => env('SECURITY_X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'SAMEORIGIN'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'hsts' => [
            'enabled' => (bool) env('SECURITY_HSTS_ENABLED', false),
            'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
            'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
            'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
            'only_in_production' => (bool) env('SECURITY_HSTS_PROD_ONLY', true),
        ],
        'csp' => [
            'enabled' => (bool) env('SECURITY_CSP_ENABLED', false),
            'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', true),
            'policy' => env(
                'SECURITY_CSP_POLICY',
                "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'"
            ),
        ],
    ],
    'mfa' => [
        'issuer' => env('MFA_ISSUER', env('APP_NAME', 'IzdajIznajmi')),
        'recovery_codes' => (int) env('MFA_RECOVERY_CODES', 8),
    ],
    'fraud' => [
        'score_threshold' => (int) env('FRAUD_SCORE_THRESHOLD', 60),
        'window_days' => (int) env('FRAUD_SCORE_WINDOW_DAYS', 30),
        'signals' => [
            'failed_mfa' => [
                'weight' => (int) env('FRAUD_SIGNAL_FAILED_MFA_WEIGHT', 8),
                'cooldown_minutes' => (int) env('FRAUD_SIGNAL_FAILED_MFA_COOLDOWN', 30),
                'threshold' => (int) env('FRAUD_SIGNAL_FAILED_MFA_THRESHOLD', 3),
                'window_minutes' => (int) env('FRAUD_SIGNAL_FAILED_MFA_WINDOW', 10),
            ],
            'rapid_messages' => [
                'weight' => (int) env('FRAUD_SIGNAL_RAPID_MESSAGES_WEIGHT', 5),
                'threshold' => (int) env('FRAUD_SIGNAL_RAPID_MESSAGES_THRESHOLD', 12),
                'window_minutes' => (int) env('FRAUD_SIGNAL_RAPID_MESSAGES_WINDOW', 5),
            ],
            'rapid_applications' => [
                'weight' => (int) env('FRAUD_SIGNAL_RAPID_APPLICATIONS_WEIGHT', 10),
                'threshold' => (int) env('FRAUD_SIGNAL_RAPID_APPLICATIONS_THRESHOLD', 4),
                'window_minutes' => (int) env('FRAUD_SIGNAL_RAPID_APPLICATIONS_WINDOW', 30),
            ],
            'duplicate_address_attempt' => [
                'weight' => (int) env('FRAUD_SIGNAL_DUPLICATE_ADDRESS_WEIGHT', 15),
                'cooldown_minutes' => (int) env('FRAUD_SIGNAL_DUPLICATE_ADDRESS_COOLDOWN', 60),
            ],
            'session_anomaly' => [
                'weight' => (int) env('FRAUD_SIGNAL_SESSION_ANOMALY_WEIGHT', 6),
                'threshold' => (int) env('FRAUD_SIGNAL_SESSION_ANOMALY_THRESHOLD', 3),
                'window_hours' => (int) env('FRAUD_SIGNAL_SESSION_ANOMALY_WINDOW_HOURS', 24),
            ],
        ],
    ],
];
