<?php

namespace Tests\Unit;

use App\Services\Search\MeiliSearchDriver;
use App\Services\Search\SearchDriver;
use App\Services\Search\SqlSearchDriver;
use Tests\TestCase;

class SearchDriverSelectionTest extends TestCase
{
    public function test_sql_driver_is_selected_by_default(): void
    {
        config(['search.driver' => 'sql']);

        $driver = $this->app->make(SearchDriver::class);

        $this->assertInstanceOf(SqlSearchDriver::class, $driver);
    }

    public function test_meili_driver_is_selected_when_configured(): void
    {
        config([
            'search.driver' => 'meili',
            'search.meili.host' => 'http://localhost:7700',
            'search.meili.key' => null,
        ]);

        $driver = $this->app->make(SearchDriver::class);

        $this->assertInstanceOf(MeiliSearchDriver::class, $driver);
    }
}
