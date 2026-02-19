<?php

namespace Tests\Unit;

use App\Services\Search\MeiliSearchDriver;
use MeiliSearch\Client;
use Meilisearch\Exceptions\ApiException;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class MeiliSearchDriverRecoveryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_search_recovers_when_filterable_attributes_are_missing(): void
    {
        config(['search.meili.index' => 'listings']);

        $client = Mockery::mock(Client::class);
        $index = Mockery::mock(\Meilisearch\Endpoints\Indexes::class);

        $client->shouldReceive('getIndex')
            ->once()
            ->with('listings')
            ->andReturn($index);

        $index->shouldReceive('search')
            ->once()
            ->andThrow($this->buildApiException('Attribute `status` is not filterable. This index does not have configured filterable attributes.'));

        $index->shouldReceive('updateSettings')
            ->once()
            ->with(Mockery::on(function (array $settings): bool {
                return in_array('status', $settings['filterableAttributes'] ?? [], true)
                    && in_array('created_at', $settings['sortableAttributes'] ?? [], true);
            }))
            ->andReturn(['taskUid' => 42]);

        $client->shouldReceive('waitForTask')
            ->once()
            ->with(42);

        $index->shouldReceive('search')
            ->once()
            ->andReturn([
                'hits' => [['id' => 101, 'status' => 'active']],
                'estimatedTotalHits' => 1,
                'facetDistribution' => ['status' => ['active' => 1]],
            ]);

        $driver = new MeiliSearchDriver($client);
        $result = $driver->searchListings([], 1, 10);

        $this->assertSame(1, $result->meta['total']);
        $this->assertSame('active', $result->items[0]['status']);
    }

    public function test_search_builds_amenity_alias_or_filter_for_garage(): void
    {
        config(['search.meili.index' => 'listings']);

        $capturedFilter = null;

        $client = Mockery::mock(Client::class);
        $index = Mockery::mock(\Meilisearch\Endpoints\Indexes::class);

        $client->shouldReceive('getIndex')
            ->once()
            ->with('listings')
            ->andReturn($index);

        $index->shouldReceive('search')
            ->once()
            ->with('', Mockery::on(function (array $options) use (&$capturedFilter): bool {
                $capturedFilter = $options['filter'] ?? null;

                return true;
            }))
            ->andReturn([
                'hits' => [],
                'estimatedTotalHits' => 0,
                'facetDistribution' => ['amenities' => []],
            ]);

        $driver = new MeiliSearchDriver($client);
        $driver->searchListings(['amenities' => ['Garage']], 1, 10);

        $this->assertIsArray($capturedFilter);
        $joined = implode(' ', $capturedFilter);
        $this->assertStringContainsString('amenities = "Garage"', $joined);
        $this->assertStringContainsString('amenities = "Garaza"', $joined);
        $this->assertStringContainsString('amenities = "Parking"', $joined);
    }

    private function buildApiException(string $message): ApiException
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(400);
        $response->shouldReceive('getReasonPhrase')->andReturn('Bad Request');

        return new ApiException($response, ['message' => $message, 'code' => 'invalid_search_filter']);
    }
}
