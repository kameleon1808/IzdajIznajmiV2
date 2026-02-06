<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandlordMetric extends Model
{
    protected $fillable = [
        'landlord_id',
        'avg_rating_30d',
        'all_time_avg_rating',
        'ratings_count',
        'median_response_time_minutes',
        'completed_transactions_count',
    ];

    protected $casts = [
        'avg_rating_30d' => 'decimal:2',
        'all_time_avg_rating' => 'decimal:2',
        'ratings_count' => 'integer',
        'median_response_time_minutes' => 'integer',
        'completed_transactions_count' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
