<?php

namespace App\Models;

use App\Models\Facility;
use App\Models\ListingImage;
use App\Models\User;
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
        'category',
        'instant_book',
    ];

    protected $casts = [
        'instant_book' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'rating' => 'float',
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
}
