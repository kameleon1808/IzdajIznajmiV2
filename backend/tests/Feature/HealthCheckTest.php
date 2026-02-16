<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_returns_ok_with_db_check(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'checks' => ['db' => ['ok' => true]],
            ]);
    }

    public function test_readiness_endpoint_covers_cache_and_queue(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.cache.ok', true)
            ->assertJsonPath('checks.queue.ok', true);
    }

    public function test_queue_health_endpoint_reports_failed_jobs_count(): void
    {
        $response = $this->getJson('/api/v1/health/queue');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.queue.ok', true)
            ->assertJsonPath('checks.queue.failed_jobs.count', 0);
    }
}
