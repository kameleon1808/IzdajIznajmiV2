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
    public const TYPE_VIEWING_REQUESTED = 'viewing.requested';
    public const TYPE_VIEWING_CONFIRMED = 'viewing.confirmed';
    public const TYPE_VIEWING_CANCELLED = 'viewing.cancelled';
    public const TYPE_KYC_SUBMISSION_RECEIVED = 'kyc.submission_received';
    public const TYPE_KYC_APPROVED = 'kyc.approved';
    public const TYPE_KYC_REJECTED = 'kyc.rejected';
    public const TYPE_TRANSACTION_CONTRACT_READY = 'transaction.contract_ready';
    public const TYPE_TRANSACTION_SIGNED_BY_OTHER_PARTY = 'transaction.signed_by_other_party';
    public const TYPE_TRANSACTION_FULLY_SIGNED = 'transaction.fully_signed';
    public const TYPE_TRANSACTION_DEPOSIT_PAID = 'transaction.deposit_paid';
    public const TYPE_TRANSACTION_MOVE_IN_CONFIRMED = 'transaction.move_in_confirmed';

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
