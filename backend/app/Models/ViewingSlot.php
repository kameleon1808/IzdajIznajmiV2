<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViewingSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'landlord_id',
        'starts_at',
        'ends_at',
        'capacity',
        'is_active',
        'pattern',
        'days_of_week',
        'time_from',
        'time_to',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'days_of_week' => 'array',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ViewingRequest::class);
    }
}
