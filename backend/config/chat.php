<?php

return [
    'attachments' => [
        'max_files' => (int) env('CHAT_ATTACHMENT_MAX_FILES', 5),
        'max_kb' => (int) env('CHAT_ATTACHMENT_MAX_KB', 10240),
        'thumb_width' => (int) env('CHAT_ATTACHMENT_THUMB_WIDTH', 480),
        'webp_quality' => (int) env('CHAT_ATTACHMENT_WEBP_QUALITY', 80),
    ],
    'typing_ttl_seconds' => (int) env('CHAT_TYPING_TTL', 8),
    'presence_ttl_seconds' => (int) env('CHAT_PRESENCE_TTL', 90),
    'rate_limits' => [
        'messages_per_minute' => (int) env('CHAT_MESSAGES_PER_MINUTE', 30),
        'attachments_per_10_minutes' => (int) env('CHAT_ATTACHMENTS_PER_10_MINUTES', 10),
    ],
];
