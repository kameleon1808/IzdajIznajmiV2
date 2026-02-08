<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RentalTransaction extends Model
{
    public const STATUS_INITIATED = 'initiated';

    public const STATUS_CONTRACT_GENERATED = 'contract_generated';

    public const STATUS_SEEKER_SIGNED = 'seeker_signed';

    public const STATUS_LANDLORD_SIGNED = 'landlord_signed';

    public const STATUS_DEPOSIT_PAID = 'deposit_paid';

    public const STATUS_MOVE_IN_CONFIRMED = 'move_in_confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DISPUTED = 'disputed';

    protected $fillable = [
        'listing_id',
        'landlord_id',
        'seeker_id',
        'status',
        'deposit_amount',
        'rent_amount',
        'currency',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'deposit_amount' => 'decimal:2',
        'rent_amount' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seeker_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'transaction_id');
    }

    public function latestContract(): HasOne
    {
        return $this->hasOne(Contract::class, 'transaction_id')->latestOfMany('version');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'transaction_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_INITIATED,
            self::STATUS_CONTRACT_GENERATED,
            self::STATUS_SEEKER_SIGNED,
            self::STATUS_LANDLORD_SIGNED,
            self::STATUS_DEPOSIT_PAID,
            self::STATUS_MOVE_IN_CONFIRMED,
            self::STATUS_DISPUTED,
        ], true);
    }
}
