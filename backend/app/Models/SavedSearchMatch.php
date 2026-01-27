<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearchMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'saved_search_id',
        'listing_id',
        'matched_at',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
    ];

    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
