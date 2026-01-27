<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'filters',
        'alerts_enabled',
        'frequency',
        'last_alerted_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'alerts_enabled' => 'boolean',
        'last_alerted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SavedSearchMatch::class);
    }
}
