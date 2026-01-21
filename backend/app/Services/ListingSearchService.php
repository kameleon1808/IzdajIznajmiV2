<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListingSearchService
{
    public function __construct(private readonly ListingStatusService $statusService)
    {
    }

    public function search(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->baseQuery();
        $geoApplied = $this->applyFilters($query, $filters);

        if ($geoApplied) {
            $query->orderBy('distance_km');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($perPage);
    }

    public function baseQuery(): Builder
    {
        return Listing::query()->with([
            'images' => function ($q) {
                $q->where('processing_status', 'done')->orderBy('sort_order');
            },
            'facilities',
            'owner:id,full_name,name',
        ]);
    }

    /**
     * @return bool whether geo filtering was applied
     */
    public function applyFilters(Builder $query, array $filters): bool
    {
        $statuses = array_filter((array) ($filters['status'] ?? []));
        $allowedStatuses = $this->statusService->allowedStatuses();
        if (!empty($statuses) && !in_array('all', $statuses, true)) {
            $query->whereIn('status', array_intersect($statuses, $allowedStatuses));
        } else {
            $query->where('status', ListingStatusService::STATUS_ACTIVE);
        }

        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if ($this->hasValue($filters['priceMin'] ?? null)) {
            $query->where('price_per_night', '>=', (int) $filters['priceMin']);
        }
        if ($this->hasValue($filters['priceMax'] ?? null)) {
            $query->where('price_per_night', '<=', (int) $filters['priceMax']);
        }

        if ($this->hasValue($filters['rooms'] ?? null)) {
            $query->where('rooms', '>=', (int) $filters['rooms']);
        }

        if ($this->hasValue($filters['areaMin'] ?? null)) {
            $query->where('area', '>=', (int) $filters['areaMin']);
        }
        if ($this->hasValue($filters['areaMax'] ?? null)) {
            $query->where('area', '<=', (int) $filters['areaMax']);
        }

        if (!empty($filters['instantBook'])) {
            $query->where('instant_book', true);
        }

        if ($this->hasValue($filters['rating'] ?? null)) {
            $query->where('rating', '>=', (float) $filters['rating']);
        }

        if (!empty($filters['location'])) {
            $location = (string) $filters['location'];
            $query->where(function ($builder) use ($location) {
                $builder->where('city', 'like', "%{$location}%")
                    ->orWhere('country', 'like', "%{$location}%");
            });
        }

        if (!empty($filters['city'])) {
            $cityInput = (string) $filters['city'];
            $tokens = collect(preg_split('/,/', $cityInput))
                ->filter()
                ->map(fn ($token) => trim($token))
                ->values();

            $query->where(function ($builder) use ($cityInput, $tokens) {
                $builder->where('city', 'like', "%{$cityInput}%")
                    ->orWhere('country', 'like', "%{$cityInput}%");

                $tokens->each(function ($token) use ($builder) {
                    $builder->orWhere('city', 'like', "%{$token}%")
                        ->orWhere('country', 'like', "%{$token}%");
                });
            });
        }

        if (!empty($filters['guests'])) {
            $query->where('beds', '>=', (int) $filters['guests']);
        }

        $amenitiesToApply = $filters['amenities'] ?? $filters['facilities'] ?? [];
        if (!empty($amenitiesToApply)) {
            $facilityIds = (array) $amenitiesToApply;
            $query->whereHas('facilities', function ($builder) use ($facilityIds) {
                $builder->whereIn('name', $facilityIds);
            });
        }

        return $this->applyGeoFilter($query, $filters);
    }

    private function applyGeoFilter(Builder $query, array $filters): bool
    {
        $centerLat = $this->toFloat($filters['centerLat'] ?? null, -90, 90);
        $centerLng = $this->toFloat($filters['centerLng'] ?? null, -180, 180);
        $radius = $this->toFloat($filters['radiusKm'] ?? null, 1.0, 50.0);

        if ($centerLat === null || $centerLng === null) {
            return false;
        }

        $radiusKm = $radius ?? 10.0;
        $query->whereNotNull('lat')->whereNotNull('lng');

        $earthRadius = 6371; // km
        $haversine = "({$earthRadius} * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))))";
        $query->select('*')->selectRaw("{$haversine} as distance_km", [$centerLat, $centerLng, $centerLat]);
        $query->whereRaw("{$haversine} <= ?", [$centerLat, $centerLng, $centerLat, $radiusKm]);

        $deltaLat = $radiusKm / 111.045;
        $deltaLng = $radiusKm / (111.045 * max(cos(deg2rad($centerLat)), 0.1));
        $query->whereBetween('lat', [$centerLat - $deltaLat, $centerLat + $deltaLat])
            ->whereBetween('lng', [$centerLng - $deltaLng, $centerLng + $deltaLng]);

        return true;
    }

    private function toFloat(mixed $value, ?float $min = null, ?float $max = null): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
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
}
