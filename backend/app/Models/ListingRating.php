<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingRating extends Model
{
    protected $fillable = [
        'listing_id',
        'seeker_id',
        'transaction_id',
        'rating',
        'comment',
        'ip_address',
        'user_agent',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seeker_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RentalTransaction::class, 'transaction_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(RatingReport::class, 'listing_rating_id');
    }
}
