<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'type_settings',
        'digest_frequency',
        'digest_enabled',
        'last_digest_daily_at',
        'last_digest_weekly_at',
    ];

    protected $casts = [
        'type_settings' => 'array',
        'digest_enabled' => 'boolean',
        'last_digest_daily_at' => 'datetime',
        'last_digest_weekly_at' => 'datetime',
    ];

    public const DIGEST_NONE = 'none';
    public const DIGEST_DAILY = 'daily';
    public const DIGEST_WEEKLY = 'weekly';

    public static function defaultTypeSettings(): array
    {
        return [
            Notification::TYPE_LISTING_NEW_MATCH => false,
            Notification::TYPE_APPLICATION_CREATED => true,
            Notification::TYPE_APPLICATION_STATUS_CHANGED => true,
            Notification::TYPE_MESSAGE_RECEIVED => true,
            Notification::TYPE_RATING_RECEIVED => true,
            Notification::TYPE_REPORT_UPDATE => true,
            Notification::TYPE_ADMIN_NOTICE => true,
            Notification::TYPE_VIEWING_REQUESTED => true,
            Notification::TYPE_VIEWING_CONFIRMED => true,
            Notification::TYPE_VIEWING_CANCELLED => true,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
