<?php

namespace App\Models;

use App\Models\Facility;
use App\Models\ListingImage;
use App\Models\User;
use App\Models\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    protected $fillable = [
        'owner_id',
        'title',
        'address',
        'address_key',
        'city',
        'country',
        'lat',
        'lng',
        'price_per_night',
        'rating',
        'reviews_count',
        'cover_image',
        'description',
        'beds',
        'baths',
        'rooms',
        'area',
        'category',
        'instant_book',
        'status',
        'published_at',
        'archived_at',
        'expired_at',
    ];

    protected $casts = [
        'instant_book' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'rating' => 'float',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
