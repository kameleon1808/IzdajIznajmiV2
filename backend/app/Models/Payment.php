<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PROVIDER_STRIPE = 'stripe';

    public const PROVIDER_CASH = 'cash';

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_RENT = 'rent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'transaction_id',
        'provider',
        'type',
        'amount',
        'currency',
        'status',
        'provider_intent_id',
        'provider_checkout_session_id',
        'receipt_url',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RentalTransaction::class, 'transaction_id');
    }
}
