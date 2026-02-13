<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingReport extends Model
{
    protected $fillable = [
        'rating_id',
        'listing_rating_id',
        'reporter_id',
        'reason',
        'details',
    ];

    public function rating(): BelongsTo
    {
        return $this->belongsTo(Rating::class);
    }

    public function listingRating(): BelongsTo
    {
        return $this->belongsTo(ListingRating::class, 'listing_rating_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
