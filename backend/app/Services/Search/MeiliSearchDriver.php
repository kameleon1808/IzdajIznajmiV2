<?php

namespace App\Services\Search;

use App\Models\Listing;
use App\Services\ListingStatusService;
use MeiliSearch\Client;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Search\SearchResult;

class MeiliSearchDriver implements SearchDriver
{
    private const DEFAULT_FACETS = ['city', 'status', 'rooms', 'amenities', 'price_bucket', 'area_bucket'];

    public function __construct(private readonly Client $client) {}

    public function searchListings(array $filters, int $page, int $perPage): ListingSearchResult
    {
        $index = $this->getIndex();

        $query = trim((string) ($filters['q'] ?? $filters['query'] ?? ''));
        $offset = max(0, ($page - 1) * $perPage);

        $options = [
            'limit' => $perPage,
            'offset' => $offset,
            'filter' => $this->buildFilters($filters),
            'facets' => self::DEFAULT_FACETS,
            'attributesToRetrieve' => $this->displayedAttributes(),
        ];

        $sort = $this->buildSort($filters['sort'] ?? null);
        if ($sort !== null) {
            $options['sort'] = [$sort];
        }

        $result = $index->search($query, $options);
        $payload = $result instanceof SearchResult ? $result->toArray() : (array) $result;
        $hits = $payload['hits'] ?? [];
        $total = $payload['estimatedTotalHits'] ?? $payload['nbHits'] ?? count($hits);
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $meta = [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => $lastPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
        ];

        $facets = $this->formatFacets($payload['facetDistribution'] ?? []);

        return new ListingSearchResult($hits, $meta, $facets);
    }

    public function suggest(string $query, int $limit): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 12));

        $index = $this->getIndex();
        $result = $index->search($query, [
            'limit' => $limit,
            'offset' => 0,
            'filter' => $this->buildFilters(['status' => ListingStatusService::STATUS_ACTIVE]),
            'facets' => ['city', 'amenities'],
            'attributesToRetrieve' => ['city', 'amenities'],
        ]);
        $payload = $result instanceof SearchResult ? $result->toArray() : (array) $result;

        $suggestions = [];
        $seen = [];

        $push = function (string $label, string $type, string $value) use (&$suggestions, &$seen, $limit): void {
            $key = strtolower($type.'|'.$label);
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

        $needle = $this->normalizeSuggestionToken($query);

        $facetDistribution = $payload['facetDistribution'] ?? [];
        $cityFacets = $facetDistribution['city'] ?? [];
        foreach (array_keys($cityFacets) as $city) {
            $value = (string) $city;
            if ($needle === '' || str_contains($this->normalizeSuggestionToken($value), $needle)) {
                $push($value, 'city', $value);
            }
        }

        $amenityFacets = $facetDistribution['amenities'] ?? [];
        foreach (array_keys($amenityFacets) as $amenity) {
            $value = (string) $amenity;
            if ($needle === '' || str_contains($this->normalizeSuggestionToken($value), $needle)) {
                $push($value, 'amenity', $value);
            }
        }

        return $suggestions;
    }

    public function indexListing(Listing $listing): void
    {
        $index = $this->getIndex();
        $document = ListingSearchDocument::fromListing($listing);
        $index->addDocuments([$document]);
    }

    public function removeListing(int $listingId): void
    {
        $index = $this->getIndex();
        $index->deleteDocument($listingId);
    }

    public function configureIndex(): void
    {
        $index = $this->getIndex();
        $task = $index->updateSettings([
            'searchableAttributes' => ['title', 'description', 'city', 'city_normalized', 'amenities_normalized'],
            'filterableAttributes' => [
                'city',
                'status',
                'rooms',
                'beds',
                'category',
                'amenities',
                'price_bucket',
                'area_bucket',
                'price_per_night',
                'area',
                'rating',
                'instant_book',
            ],
            'sortableAttributes' => ['price_per_night', 'created_at', 'rating_avg'],
            'displayedAttributes' => $this->displayedAttributes(),
        ]);
        $taskUid = $task['taskUid'] ?? $task['uid'] ?? null;
        if ($taskUid !== null) {
            $this->client->waitForTask($taskUid);
        }
    }

    public function resetIndex(): void
    {
        $name = config('search.meili.index', 'listings');

        try {
            $task = $this->client->deleteIndex($name);
            $taskUid = $task['taskUid'] ?? $task['uid'] ?? null;
            if ($taskUid !== null) {
                $this->client->waitForTask($taskUid);
            }
        } catch (ApiException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * @return array<string, array<int, array{value: string, count: int}>>
     */
    private function formatFacets(array $facetDistribution): array
    {
        $facets = [];
        foreach (self::DEFAULT_FACETS as $facet) {
            $items = [];
            $values = $facetDistribution[$facet] ?? [];
            foreach ($values as $value => $count) {
                $items[] = ['value' => (string) $value, 'count' => (int) $count];
            }

            if (in_array($facet, ['price_bucket', 'area_bucket'], true)) {
                $order = $facet === 'price_bucket'
                    ? array_column(ListingSearchBuckets::priceBuckets(), 'label')
                    : array_column(ListingSearchBuckets::areaBuckets(), 'label');
                $items = $this->sortFacetBuckets($items, $order);
            } elseif ($facet === 'rooms') {
                usort($items, fn ($a, $b) => (int) $a['value'] <=> (int) $b['value']);
            } else {
                usort($items, fn ($a, $b) => $b['count'] <=> $a['count']);
            }

            $facets[$facet] = $items;
        }

        return $facets;
    }

    /**
     * @param  array<int, array{value: string, count: int}>  $items
     * @param  array<int, string>  $order
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

    private function buildSort(?string $sort): ?string
    {
        return match ($sort) {
            'price_asc' => 'price_per_night:asc',
            'price_desc' => 'price_per_night:desc',
            'rating' => 'rating_avg:desc',
            'newest' => 'created_at:desc',
            default => 'created_at:desc',
        };
    }

    private function buildFilters(array $filters): array|string|null
    {
        $clauses = [];

        $statuses = $this->normalizeArrayInput($filters['status'] ?? []);
        if ($statuses === [] || in_array('all', $statuses, true)) {
            $statuses = [ListingStatusService::STATUS_ACTIVE];
        }
        if (count($statuses) === 1) {
            $clauses[] = sprintf('status = "%s"', $this->escapeFilterValue($statuses[0]));
        } else {
            $clauses[] = 'status IN ['.implode(', ', array_map(fn ($s) => '"'.$this->escapeFilterValue($s).'"', $statuses)).']';
        }

        $cities = $this->normalizeArrayInput($filters['city'] ?? []);
        if ($cities !== []) {
            $clauses[] = 'city IN ['.implode(', ', array_map(fn ($c) => '"'.$this->escapeFilterValue($c).'"', $cities)).']';
        }

        $rooms = $filters['rooms'] ?? null;
        if ($rooms !== null && $rooms !== '') {
            $clauses[] = 'rooms >= '.(int) $rooms;
        }

        $guests = $filters['guests'] ?? null;
        if ($guests !== null && $guests !== '') {
            $clauses[] = 'beds >= '.(int) $guests;
        }

        $category = $filters['category'] ?? null;
        if ($category !== null && $category !== '' && $category !== 'all') {
            $clauses[] = sprintf('category = "%s"', $this->escapeFilterValue((string) $category));
        }

        $amenities = $this->normalizeArrayInput($filters['amenities'] ?? $filters['facilities'] ?? []);
        foreach ($amenities as $amenity) {
            $clauses[] = sprintf('amenities = "%s"', $this->escapeFilterValue($amenity));
        }

        $priceBucket = $this->normalizeArrayInput($filters['priceBucket'] ?? $filters['price_bucket'] ?? []);
        if ($priceBucket !== []) {
            $clauses[] = 'price_bucket IN ['.implode(', ', array_map(fn ($p) => '"'.$this->escapeFilterValue($p).'"', $priceBucket)).']';
        }

        $areaBucket = $this->normalizeArrayInput($filters['areaBucket'] ?? $filters['area_bucket'] ?? []);
        if ($areaBucket !== []) {
            $clauses[] = 'area_bucket IN ['.implode(', ', array_map(fn ($a) => '"'.$this->escapeFilterValue($a).'"', $areaBucket)).']';
        }

        if ($this->hasValue($filters['priceMin'] ?? null)) {
            $clauses[] = 'price_per_night >= '.(int) $filters['priceMin'];
        }
        if ($this->hasValue($filters['priceMax'] ?? null)) {
            $clauses[] = 'price_per_night <= '.(int) $filters['priceMax'];
        }
        if ($this->hasValue($filters['areaMin'] ?? null)) {
            $clauses[] = 'area >= '.(int) $filters['areaMin'];
        }
        if ($this->hasValue($filters['areaMax'] ?? null)) {
            $clauses[] = 'area <= '.(int) $filters['areaMax'];
        }

        if ($this->hasValue($filters['rating'] ?? null)) {
            $clauses[] = 'rating >= '.(float) $filters['rating'];
        }

        if (! empty($filters['instantBook'])) {
            $clauses[] = 'instant_book = true';
        }

        if ($clauses === []) {
            return null;
        }

        return $clauses;
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

    private function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    private function escapeFilterValue(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }

    private function displayedAttributes(): array
    {
        return [
            'id',
            'title',
            'description',
            'city',
            'country',
            'price_per_night',
            'rooms',
            'beds',
            'area',
            'category',
            'amenities',
            'status',
            'owner_id',
            'landlord_verification_status',
            'landlord_verified_at',
            'rating',
            'rating_avg',
            'baths',
            'instant_book',
            'cover_image',
            'cover_image_url',
            'created_at',
            'published_at',
            'price_bucket',
            'area_bucket',
        ];
    }

    private function getIndex(): \Meilisearch\Endpoints\Indexes
    {
        $name = config('search.meili.index', 'listings');

        try {
            return $this->client->getIndex($name);
        } catch (ApiException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        } catch (\Throwable $e) {
            // fall through to create index
        }

        $task = $this->client->createIndex($name, ['primaryKey' => 'id']);
        $taskUid = $task['taskUid'] ?? $task['uid'] ?? null;
        if ($taskUid !== null) {
            $this->client->waitForTask($taskUid);
        }

        return $this->client->getIndex($name);
    }

    private function normalizeSuggestionToken(string $value): string
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
