<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    public const TYPE_LISTING_NEW_MATCH = 'listing.new_match';
    public const TYPE_APPLICATION_CREATED = 'application.created';
    public const TYPE_APPLICATION_STATUS_CHANGED = 'application.status_changed';
    public const TYPE_MESSAGE_RECEIVED = 'message.received';
    public const TYPE_RATING_RECEIVED = 'rating.received';
    public const TYPE_REPORT_UPDATE = 'report.update';
    public const TYPE_ADMIN_NOTICE = 'admin.notice';
    public const TYPE_DIGEST_DAILY = 'digest.daily';
    public const TYPE_DIGEST_WEEKLY = 'digest.weekly';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'url',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

