<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'listing_id',
        'seeker_id',
        'landlord_id',
        'message',
        'status',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seeker_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function scopeWithCompletedTransactionFlag(Builder $query): Builder
    {
        return $query->addSelect([
            'has_completed_transaction' => RentalTransaction::query()
                ->selectRaw('1')
                ->whereColumn('rental_transactions.listing_id', 'applications.listing_id')
                ->whereColumn('rental_transactions.seeker_id', 'applications.seeker_id')
                ->whereColumn('rental_transactions.landlord_id', 'applications.landlord_id')
                ->where('status', RentalTransaction::STATUS_COMPLETED)
                ->limit(1),
        ]);
    }
}
