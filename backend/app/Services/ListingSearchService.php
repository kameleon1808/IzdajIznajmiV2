<?php

namespace App\Services;

use App\Models\Listing;
use App\Support\ListingAmenityNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListingSearchService
{
    public function __construct(private readonly ListingStatusService $statusService) {}

    public function search(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->baseQuery();
        $mapMode = (bool) ($filters['mapMode'] ?? false);
        $geoApplied = $this->applyFilters($query, $filters, $mapMode);

        if ($geoApplied) {
            $query->orderBy('distance_km');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($perPage);
    }

    public function baseQuery(): Builder
    {
        // eager-load relations used on listing cards to avoid N+1 fetches
        return Listing::query()
            ->withAvg('listingRatings as listing_rating_avg', 'rating')
            ->withCount('listingRatings as listing_rating_count')
            ->with([
                'images' => function ($q) {
                    $q->where('processing_status', 'done')->orderBy('sort_order');
                },
                'facilities',
                'owner:id,full_name,name,verification_status,verified_at,is_suspicious,badge_override_json',
                'owner.landlordMetric:landlord_id,avg_rating_30d,all_time_avg_rating,ratings_count,median_response_time_minutes,completed_transactions_count,updated_at',
            ]);
    }

    /**
     * @return bool whether geo filtering was applied
     */
    public function applyFilters(Builder $query, array $filters, bool $mapMode = false): bool
    {
        $statuses = array_filter((array) ($filters['status'] ?? []));
        $allowedStatuses = $this->statusService->allowedStatuses();
        if (! empty($statuses) && ! in_array('all', $statuses, true)) {
            $query->whereIn('status', array_intersect($statuses, $allowedStatuses));
        } else {
            $query->where('status', ListingStatusService::STATUS_ACTIVE);
        }

        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if ($this->hasValue($filters['priceMin'] ?? null)) {
            $query->where('price_per_month', '>=', (int) $filters['priceMin']);
        }
        if ($this->hasValue($filters['priceMax'] ?? null)) {
            $query->where('price_per_month', '<=', (int) $filters['priceMax']);
        }

        if ($this->hasValue($filters['rooms'] ?? null)) {
            $query->where('rooms', '>=', (int) $filters['rooms']);
        }
        if ($this->hasValue($filters['baths'] ?? null)) {
            $query->where('baths', '>=', (int) $filters['baths']);
        }
        if ($this->hasValue($filters['floor'] ?? null)) {
            $query->where('floor', '=', (int) $filters['floor']);
        }
        if ($this->hasValue($filters['heating'] ?? null)) {
            $query->where('heating', '=', (string) $filters['heating']);
        }
        if ($this->hasValue($filters['condition'] ?? null)) {
            $query->where('condition', '=', (string) $filters['condition']);
        }
        if ($this->hasValue($filters['furnishing'] ?? null)) {
            $query->where('furnishing', '=', (string) $filters['furnishing']);
        }
        if (! empty($filters['notLastFloor'])) {
            $query->where('not_last_floor', true);
        }
        if (! empty($filters['notGroundFloor'])) {
            $query->where('not_ground_floor', true);
        }

        if ($this->hasValue($filters['areaMin'] ?? null)) {
            $query->where('area', '>=', (int) $filters['areaMin']);
        }
        if ($this->hasValue($filters['areaMax'] ?? null)) {
            $query->where('area', '<=', (int) $filters['areaMax']);
        }

        if (! empty($filters['instantBook'])) {
            $query->where('instant_book', true);
        }

        if ($this->hasValue($filters['rating'] ?? null)) {
            $query->where('rating', '>=', (float) $filters['rating']);
        }

        if (! empty($filters['location'])) {
            $location = $this->normalizeSerbianLatin((string) $filters['location']);
            $columns = ['city', 'country', 'title', 'address'];

            $query->where(function ($builder) use ($location, $columns) {
                $first = true;
                foreach ($columns as $column) {
                    $method = $first ? 'whereRaw' : 'orWhereRaw';
                    $builder->{$method}($this->normalizedColumn($column).' like ?', ["%{$location}%"]);
                    $first = false;
                }
            });
        }

        if (! empty($filters['city'])) {
            $cityInput = $this->normalizeSerbianLatin((string) $filters['city']);
            $tokens = collect(preg_split('/,/', $cityInput))
                ->filter()
                ->map(fn ($token) => $this->normalizeSerbianLatin(trim($token)))
                ->values();

            $columns = ['city', 'country', 'title', 'address'];

            $query->where(function ($builder) use ($cityInput, $tokens, $columns) {
                $first = true;
                foreach ($columns as $column) {
                    $method = $first ? 'whereRaw' : 'orWhereRaw';
                    $builder->{$method}($this->normalizedColumn($column).' like ?', ["%{$cityInput}%"]);
                    $first = false;
                }

                $tokens->each(function ($token) use ($builder, $columns) {
                    foreach ($columns as $column) {
                        $builder->orWhereRaw($this->normalizedColumn($column).' like ?', ["%{$token}%"]);
                    }
                });
            });
        }

        if (! empty($filters['guests'])) {
            $query->where('beds', '>=', (int) $filters['guests']);
        }

        $amenitiesToApply = ListingAmenityNormalizer::canonicalizeMany($filters['amenities'] ?? $filters['facilities'] ?? []);
        if ($amenitiesToApply !== []) {
            foreach ($amenitiesToApply as $amenity) {
                $normalizedVariants = ListingAmenityNormalizer::normalizedFilterVariants($amenity);
                if ($normalizedVariants === []) {
                    continue;
                }

                $query->whereHas('facilities', function ($builder) use ($normalizedVariants) {
                    $normalizedNameExpr = $this->normalizedColumn('facilities.name');
                    $builder->where(function ($variantQuery) use ($normalizedVariants, $normalizedNameExpr) {
                        $isFirst = true;
                        foreach ($normalizedVariants as $variant) {
                            $method = $isFirst ? 'whereRaw' : 'orWhereRaw';
                            $variantQuery->{$method}("{$normalizedNameExpr} = ?", [$variant]);
                            $isFirst = false;
                        }
                    });
                });
            }
        }

        return $this->applyGeoFilter($query, $filters, $mapMode);
    }

    private function applyGeoFilter(Builder $query, array $filters, bool $mapMode = false): bool
    {
        if (! $mapMode) {
            return false;
        }

        $centerLat = $this->toFloat($filters['centerLat'] ?? null, -90, 90);
        $centerLng = $this->toFloat($filters['centerLng'] ?? null, -180, 180);
        $radius = $this->toFloat($filters['radiusKm'] ?? null, 1.0, 50.0);
        $maxRadius = (float) config('search.max_radius_km', 50.0);
        if ($radius !== null) {
            $radius = min($radius, $maxRadius);
        }

        if ($centerLat === null || $centerLng === null) {
            return false;
        }

        $radiusKm = $radius ?? 10.0;
        $earthRadius = 6371; // km
        $cosPart = 'cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))';
        $driver = $query->getConnection()->getDriverName();
        $clampedCosPart = $this->clampUnitRangeExpression($cosPart, $driver);
        $haversine = "({$earthRadius} * acos({$clampedCosPart}))";
        $distanceExpr = "COALESCE({$haversine}, 0)";
        $query->select('*')->selectRaw("{$distanceExpr} as distance_km", [$centerLat, $centerLng, $centerLat]);
        $query->whereRaw("{$haversine} <= ?", [$centerLat, $centerLng, $centerLat, $radiusKm]);

        $deltaLat = $radiusKm / 111.045;
        $deltaLng = $radiusKm / (111.045 * max(cos(deg2rad($centerLat)), 0.1));
        $query->whereBetween('lat', [$centerLat - $deltaLat, $centerLat + $deltaLat])
            ->whereBetween('lng', [$centerLng - $deltaLng, $centerLng + $deltaLng]);

        if ($mapMode) {
            $maxPins = (int) config('search.max_map_results', 300);
            $query->limit($maxPins);
        }

        return true;
    }

    private function toFloat(mixed $value, ?float $min = null, ?float $max = null): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        if ($min !== null) {
            $float = max($min, $float);
        }
        if ($max !== null) {
            $float = min($max, $float);
        }

        return $float;
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    private function normalizeSerbianLatin(string $value): string
    {
        $map = [
            'ć' => 'c',
            'č' => 'c',
            'ž' => 'z',
            'š' => 's',
            'đ' => 'dj',
        ];

        $normalized = mb_strtolower($value);

        return strtr($normalized, $map);
    }

    private function normalizedColumn(string $column): string
    {
        $expr = "LOWER($column)";
        $replacements = [
            'ć' => 'c',
            'č' => 'c',
            'ž' => 'z',
            'š' => 's',
            'đ' => 'dj',
        ];

        foreach ($replacements as $from => $to) {
            $expr = "REPLACE($expr, '$from', '$to')";
        }

        return $expr;
    }

    private function clampUnitRangeExpression(string $expression, string $driver): string
    {
        if ($driver === 'sqlite') {
            return "max(-1.0, min(1.0, {$expression}))";
        }

        return "greatest(-1.0, least(1.0, {$expression}))";
    }
}
