<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingEvent extends Model
{
    public const TYPE_VIEW = 'view';
    public const TYPE_FAVORITE = 'favorite';
    public const TYPE_APPLY = 'apply';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'listing_id',
        'event_type',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
