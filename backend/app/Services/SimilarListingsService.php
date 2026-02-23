<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Collection;

class SimilarListingsService
{
    private const WEIGHT_CITY = 30;

    private const WEIGHT_PRICE_CLOSE = 20;

    private const WEIGHT_PRICE_NEAR = 10;

    private const WEIGHT_ROOMS_SAME = 15;

    private const WEIGHT_ROOMS_NEAR = 10;

    private const WEIGHT_AREA_CLOSE = 10;

    private const WEIGHT_AREA_NEAR = 5;

    private const WEIGHT_AMENITY = 20;

    private const WEIGHT_VERIFIED = 3;

    public function __construct(private readonly ListingStatusService $statusService) {}

    /**
     * @return Collection<int, Listing>
     */
    public function similarTo(Listing $listing, int $limit = 8): Collection
    {
        $listing->loadMissing('facilities');

        [$priceMin, $priceMax] = $this->priceRangeForListing($listing);

        $query = Listing::query()
            ->where('status', ListingStatusService::STATUS_ACTIVE)
            ->where('id', '!=', $listing->id)
            ->with([
                'images' => function ($q) {
                    $q->where('processing_status', 'done')->orderBy('sort_order');
                },
                'facilities:id,name',
                'owner:id,full_name,name,verification_status,verified_at,is_suspicious,badge_override_json',
                'owner.landlordMetric:landlord_id,avg_rating_30d,all_time_avg_rating,ratings_count,median_response_time_minutes,completed_transactions_count,updated_at',
            ]);

        if ($listing->city || $priceMin !== null) {
            $query->where(function ($builder) use ($listing, $priceMin, $priceMax) {
                if ($listing->city) {
                    $builder->where('city', $listing->city);
                }
                if ($priceMin !== null && $priceMax !== null) {
                    $builder->orWhereBetween('price_per_month', [$priceMin, $priceMax]);
                }
            });
        }

        $candidates = $query->limit(200)->get();

        $scored = $candidates->map(function (Listing $candidate) use ($listing) {
            [$score, $reasons] = $this->scoreListing($listing, $candidate);
            $candidate->setAttribute('similarity_score', $score);
            if (! empty($reasons)) {
                $candidate->setAttribute('why', $reasons);
            }

            return $candidate;
        })->filter(fn (Listing $candidate) => ($candidate->getAttribute('similarity_score') ?? 0) > 0);

        return $scored
            ->sortByDesc(function (Listing $candidate) {
                return $candidate->getAttribute('similarity_score') ?? 0;
            })
            ->take($limit)
            ->values();
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function priceRangeForListing(Listing $listing): array
    {
        $basePrice = $listing->price_per_month !== null ? (int) $listing->price_per_month : null;
        if ($basePrice === null || $basePrice <= 0) {
            return [null, null];
        }

        $priceMin = max(0, (int) floor($basePrice * 0.75));
        $priceMax = max($priceMin, (int) ceil($basePrice * 1.25));

        return [$priceMin, $priceMax];
    }

    /**
     * @return array{0: float, 1: array<int, string>}
     */
    private function scoreListing(Listing $base, Listing $candidate): array
    {
        $score = 0.0;
        $reasons = [];

        if ($base->city && $candidate->city && $base->city === $candidate->city) {
            $score += self::WEIGHT_CITY;
            $reasons[] = 'Same city';
        }

        $priceReason = $this->priceReason($base->price_per_month, $candidate->price_per_month);
        if ($priceReason !== null) {
            $score += $priceReason['score'];
            $reasons[] = $priceReason['label'];
        }

        $roomsReason = $this->roomsReason($this->roomsValue($base), $this->roomsValue($candidate));
        if ($roomsReason !== null) {
            $score += $roomsReason['score'];
            $reasons[] = $roomsReason['label'];
        }

        $areaReason = $this->areaReason($base->area, $candidate->area);
        if ($areaReason !== null) {
            $score += $areaReason['score'];
            $reasons[] = $areaReason['label'];
        }

        $amenityReason = $this->amenitiesReason($base, $candidate);
        if ($amenityReason !== null) {
            $score += $amenityReason['score'];
            $reasons[] = $amenityReason['label'];
        }

        if (($candidate->owner?->verification_status ?? null) === 'approved') {
            $score += self::WEIGHT_VERIFIED;
            $reasons[] = 'Verified landlord';
        }

        return [$score, array_slice(array_values(array_unique($reasons)), 0, 3)];
    }

    private function roomsValue(Listing $listing): ?int
    {
        $rooms = $listing->rooms ?? $listing->beds;

        return $rooms !== null ? (int) $rooms : null;
    }

    private function priceReason(?float $base, ?float $candidate): ?array
    {
        if ($base === null || $candidate === null || $base <= 0) {
            return null;
        }
        $diff = abs($candidate - $base) / $base;
        if ($diff <= 0.1) {
            return ['score' => self::WEIGHT_PRICE_CLOSE, 'label' => 'Similar price'];
        }
        if ($diff <= 0.2) {
            return ['score' => self::WEIGHT_PRICE_NEAR, 'label' => 'Near your price range'];
        }

        return null;
    }

    private function roomsReason(?int $base, ?int $candidate): ?array
    {
        if ($base === null || $candidate === null) {
            return null;
        }
        $diff = abs($candidate - $base);
        if ($diff === 0) {
            return ['score' => self::WEIGHT_ROOMS_SAME, 'label' => 'Same rooms'];
        }
        if ($diff === 1) {
            return ['score' => self::WEIGHT_ROOMS_NEAR, 'label' => 'Similar rooms'];
        }

        return null;
    }

    private function areaReason(?float $base, ?float $candidate): ?array
    {
        if ($base === null || $candidate === null || $base <= 0) {
            return null;
        }
        $diff = abs($candidate - $base) / $base;
        if ($diff <= 0.1) {
            return ['score' => self::WEIGHT_AREA_CLOSE, 'label' => 'Similar size'];
        }
        if ($diff <= 0.2) {
            return ['score' => self::WEIGHT_AREA_NEAR, 'label' => 'Close in size'];
        }

        return null;
    }

    private function amenitiesReason(Listing $base, Listing $candidate): ?array
    {
        $baseFacilities = $base->facilities?->pluck('id')->all() ?? [];
        $candidateFacilities = $candidate->facilities?->pluck('id')->all() ?? [];

        if (empty($baseFacilities) || empty($candidateFacilities)) {
            return null;
        }

        $intersection = array_intersect($baseFacilities, $candidateFacilities);
        $union = array_unique(array_merge($baseFacilities, $candidateFacilities));
        if (empty($union)) {
            return null;
        }

        $jaccard = count($intersection) / count($union);
        if ($jaccard <= 0) {
            return null;
        }

        $score = self::WEIGHT_AMENITY * $jaccard;
        $label = $jaccard >= 0.3 ? 'Shared amenities' : 'Some shared amenities';

        return ['score' => $score, 'label' => $label];
    }
}
