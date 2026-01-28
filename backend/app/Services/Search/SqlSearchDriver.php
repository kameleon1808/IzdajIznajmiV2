<?php

namespace App\Services\Search;

use App\Http\Resources\ListingResource;
use App\Models\Facility;
use App\Models\Listing;
use App\Services\ListingSearchService;
use App\Services\ListingStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SqlSearchDriver implements SearchDriver
{
    public function __construct(private readonly ListingSearchService $searchService)
    {
    }

    public function searchListings(array $filters, int $page, int $perPage): ListingSearchResult
    {
        $filters = $this->normalizeFilters($filters);

        $query = $this->searchService->baseQuery();
        $this->searchService->applyFilters($query, $filters, false);
        $this->applyBucketFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? null);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = ListingResource::collection($paginator)->resolve();

        $meta = [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'lastPage' => $paginator->lastPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];

        $facets = $this->buildFacets($filters);

        return new ListingSearchResult($items, $meta, $facets);
    }

    public function suggest(string $query, int $limit): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 12));
        $suggestions = [];
        $seen = [];

        $push = function (string $label, string $type, string $value) use (&$suggestions, &$seen, $limit): void {
            $key = strtolower($type . '|' . $label);
            if (isset($seen[$key]) || count($suggestions) >= $limit) {
                return;
            }
            $seen[$key] = true;
            $suggestions[] = [
                'label' => $label,
                'type' => $type,
                'value' => $value,
            ];
        };

        $push($query, 'query', $query);

        $cityMatches = Listing::query()
            ->where('status', ListingStatusService::STATUS_ACTIVE)
            ->whereNotNull('city')
            ->where('city', 'like', '%' . $query . '%')
            ->distinct()
            ->limit($limit)
            ->pluck('city');

        foreach ($cityMatches as $city) {
            $push($city, 'city', $city);
        }

        $amenityMatches = Facility::query()
            ->where('name', 'like', '%' . $query . '%')
            ->limit($limit)
            ->pluck('name');

        foreach ($amenityMatches as $amenity) {
            $push($amenity, 'amenity', $amenity);
        }

        return $suggestions;
    }

    public function indexListing(Listing $listing): void
    {
        // SQL driver does not require indexing.
    }

    public function removeListing(int $listingId): void
    {
        // SQL driver does not require indexing.
    }

    public function configureIndex(): void
    {
        // SQL driver has no index to configure.
    }

    public function resetIndex(): void
    {
        // SQL driver has no index to reset.
    }

    /**
     * @return array<string, array<int, array{value: string, count: int}>>
     */
    private function buildFacets(array $filters): array
    {
        $baseQuery = Listing::query();
        $this->searchService->applyFilters($baseQuery, $filters, false);
        $this->applyBucketFilters($baseQuery, $filters);

        $facets = [
            'city' => [],
            'status' => [],
            'rooms' => [],
            'amenities' => [],
            'price_bucket' => [],
            'area_bucket' => [],
        ];

        $cityRows = (clone $baseQuery)
            ->whereNotNull('city')
            ->select('city', DB::raw('count(*) as count'))
            ->groupBy('city')
            ->orderByDesc('count')
            ->limit(12)
            ->get();
        $facets['city'] = $this->mapFacetRows($cityRows, 'city');

        $statusRows = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();
        $facets['status'] = $this->mapFacetRows($statusRows, 'status');

        $roomsRows = (clone $baseQuery)
            ->whereNotNull('rooms')
            ->select('rooms', DB::raw('count(*) as count'))
            ->groupBy('rooms')
            ->orderBy('rooms')
            ->get();
        $facets['rooms'] = $this->mapFacetRows($roomsRows, 'rooms');

        $amenityRows = (clone $baseQuery)
            ->join('facility_listing', 'listings.id', '=', 'facility_listing.listing_id')
            ->join('facilities', 'facilities.id', '=', 'facility_listing.facility_id')
            ->select('facilities.name as name', DB::raw('count(distinct listings.id) as count'))
            ->groupBy('facilities.name')
            ->orderByDesc('count')
            ->limit(15)
            ->get();
        $facets['amenities'] = $this->mapFacetRows($amenityRows, 'name');

        $priceCase = ListingSearchBuckets::caseExpression('price_per_night', ListingSearchBuckets::priceBuckets());
        $priceRows = (clone $baseQuery)
            ->whereNotNull('price_per_night')
            ->selectRaw("{$priceCase} as bucket, count(*) as count")
            ->groupBy('bucket')
            ->get();
        $facets['price_bucket'] = $this->sortFacetBuckets(
            $this->mapFacetRows($priceRows, 'bucket'),
            array_column(ListingSearchBuckets::priceBuckets(), 'label')
        );

        $areaCase = ListingSearchBuckets::caseExpression('area', ListingSearchBuckets::areaBuckets());
        $areaRows = (clone $baseQuery)
            ->whereNotNull('area')
            ->selectRaw("{$areaCase} as bucket, count(*) as count")
            ->groupBy('bucket')
            ->get();
        $facets['area_bucket'] = $this->sortFacetBuckets(
            $this->mapFacetRows($areaRows, 'bucket'),
            array_column(ListingSearchBuckets::areaBuckets(), 'label')
        );

        return $facets;
    }

    /**
     * @param Collection<int, mixed> $rows
     * @return array<int, array{value: string, count: int}>
     */
    private function mapFacetRows(Collection $rows, string $valueKey): array
    {
        return $rows
            ->map(fn ($row) => [
                'value' => (string) $row->{$valueKey},
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array{value: string, count: int}> $items
     * @param array<int, string> $order
     * @return array<int, array{value: string, count: int}>
     */
    private function sortFacetBuckets(array $items, array $order): array
    {
        $lookup = array_flip($order);
        usort($items, function ($a, $b) use ($lookup) {
            $aIndex = $lookup[$a['value']] ?? PHP_INT_MAX;
            $bIndex = $lookup[$b['value']] ?? PHP_INT_MAX;
            return $aIndex <=> $bIndex;
        });

        return $items;
    }

    private function applySort(Builder $query, ?string $sort): void
    {
        switch ($sort) {
            case 'price_asc':
                $query->orderBy('price_per_night');
                break;
            case 'price_desc':
                $query->orderByDesc('price_per_night');
                break;
            case 'rating':
                $query->orderByDesc('rating');
                break;
            case 'newest':
            default:
                $query->orderByDesc('created_at');
                break;
        }
    }

    private function applyBucketFilters(Builder $query, array $filters): void
    {
        $priceBuckets = $this->normalizeBucketSelection($filters['priceBucket'] ?? $filters['price_bucket'] ?? null);
        if ($priceBuckets !== []) {
            $ranges = ListingSearchBuckets::rangesForBuckets($priceBuckets, ListingSearchBuckets::priceBuckets());
            $query->where(function ($builder) use ($ranges) {
                foreach ($ranges as $range) {
                    $min = $range['min'];
                    $max = $range['max'];
                    $builder->orWhere(function ($sub) use ($min, $max) {
                        $sub->where('price_per_night', '>=', $min);
                        if ($max !== null) {
                            $sub->where('price_per_night', '<', $max);
                        }
                    });
                }
            });
        }

        $areaBuckets = $this->normalizeBucketSelection($filters['areaBucket'] ?? $filters['area_bucket'] ?? null);
        if ($areaBuckets !== []) {
            $ranges = ListingSearchBuckets::rangesForBuckets($areaBuckets, ListingSearchBuckets::areaBuckets());
            $query->where(function ($builder) use ($ranges) {
                foreach ($ranges as $range) {
                    $min = $range['min'];
                    $max = $range['max'];
                    $builder->orWhere(function ($sub) use ($min, $max) {
                        $sub->where('area', '>=', $min);
                        if ($max !== null) {
                            $sub->where('area', '<', $max);
                        }
                    });
                }
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeBucketSelection(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }
        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
    }

    private function normalizeFilters(array $filters): array
    {
        $query = trim((string) ($filters['q'] ?? $filters['query'] ?? $filters['location'] ?? ''));
        $city = trim((string) ($filters['city'] ?? ''));

        if ($query !== '' && $city === '') {
            $city = $query;
        }

        $filters['location'] = $query !== '' ? $query : ($filters['location'] ?? null);
        $filters['city'] = $city !== '' ? $city : ($filters['city'] ?? null);

        $filters['amenities'] = $this->normalizeArrayInput($filters['amenities'] ?? $filters['facilities'] ?? []);
        $filters['status'] = $this->normalizeArrayInput($filters['status'] ?? []);

        return $filters;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeArrayInput(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
    }
}
