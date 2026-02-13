<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'title',
        'address',
        'address_key',
        'city',
        'country',
        'lat',
        'lng',
        'geocoded_at',
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
        'location_source',
        'location_accuracy_m',
        'location_overridden_at',
    ];

    protected $casts = [
        'instant_book' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'geocoded_at' => 'datetime',
        'rating' => 'float',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'expired_at' => 'datetime',
        'location_overridden_at' => 'datetime',
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

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function listingRatings(): HasMany
    {
        return $this->hasMany(ListingRating::class);
    }

    public function viewingSlots(): HasMany
    {
        return $this->hasMany(ViewingSlot::class);
    }

    public function viewingRequests(): HasMany
    {
        return $this->hasMany(ViewingRequest::class);
    }
}
