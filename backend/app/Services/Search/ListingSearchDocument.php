<?php

namespace App\Services\Search;

use App\Models\Listing;
use Illuminate\Support\Str;

class ListingSearchDocument
{
    /**
     * @return array<string, mixed>
     */
    public static function fromListing(Listing $listing): array
    {
        $amenities = $listing->relationLoaded('facilities')
            ? $listing->facilities->pluck('name')->filter()->values()->all()
            : $listing->facilities()->pluck('name')->filter()->values()->all();

        $owner = $listing->relationLoaded('owner') ? $listing->owner : null;
        $city = $listing->city ?? '';

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'description' => Str::limit(trim((string) $listing->description), 200),
            'city' => $city,
            'city_normalized' => self::normalizeText($city),
            'country' => $listing->country,
            'price_per_night' => $listing->price_per_night,
            'rooms' => $listing->rooms,
            'area' => $listing->area,
            'category' => $listing->category,
            'amenities' => $amenities,
            'amenities_normalized' => array_values(array_filter(array_map(fn ($item) => self::normalizeText((string) $item), $amenities))),
            'status' => $listing->status,
            'owner_id' => $listing->owner_id,
            'landlord_id' => $listing->owner_id,
            'landlord_verification_status' => $owner?->landlord_verification_status,
            'landlord_verified_at' => optional($owner?->landlord_verified_at)->toISOString(),
            'rating' => $listing->rating,
            'rating_avg' => $listing->rating,
            'beds' => $listing->beds,
            'baths' => $listing->baths,
            'instant_book' => (bool) $listing->instant_book,
            'cover_image' => $listing->cover_image,
            'cover_image_url' => $listing->cover_image,
            'created_at' => optional($listing->created_at)->toISOString(),
            'published_at' => optional($listing->published_at)->toISOString(),
            'price_bucket' => ListingSearchBuckets::priceBucketFor($listing->price_per_night),
            'area_bucket' => ListingSearchBuckets::areaBucketFor($listing->area),
        ];
    }

    private static function normalizeText(string $value): string
    {
        $map = [
            'ć' => 'c',
            'č' => 'c',
            'ž' => 'z',
            'š' => 's',
            'đ' => 'dj',
        ];

        $normalized = mb_strtolower(trim($value));

        return strtr($normalized, $map);
    }
}
