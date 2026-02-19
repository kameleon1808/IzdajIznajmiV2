<?php

namespace Tests\Feature;

use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilitiesCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilities_catalog_returns_canonical_english_names(): void
    {
        Facility::query()->firstOrCreate(['name' => 'Garaza']);
        Facility::query()->firstOrCreate(['name' => 'Garaža']);
        Facility::query()->firstOrCreate(['name' => 'Terasa']);
        Facility::query()->firstOrCreate(['name' => 'Internet']);

        $response = $this->getJson('/api/v1/facilities');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertContains('Garage', $names);
        $this->assertContains('Terrace', $names);
        $this->assertContains('Internet', $names);
        $this->assertNotContains('Garaza', $names);
        $this->assertNotContains('Garaža', $names);
    }
}
