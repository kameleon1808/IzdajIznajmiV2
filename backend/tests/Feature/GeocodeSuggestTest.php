<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeocodeSuggestTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggest_returns_fake_results(): void
    {
        $response = $this->getJson('/api/v1/geocode/suggest?q=belgrade');

        $response->assertOk();
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('label', $data[0]);
        $this->assertArrayHasKey('lat', $data[0]);
        $this->assertArrayHasKey('lng', $data[0]);
    }
}
