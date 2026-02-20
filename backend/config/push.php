<?php

return [
    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT'),
    ],

    'notification' => [
        'icon' => env('PUSH_NOTIFICATION_ICON', '/vite.svg'),
        'badge' => env('PUSH_NOTIFICATION_BADGE', '/vite.svg'),
        'ttl' => (int) env('PUSH_NOTIFICATION_TTL', 3600),
    ],
];
