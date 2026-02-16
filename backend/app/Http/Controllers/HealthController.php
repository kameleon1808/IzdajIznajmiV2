<?php

namespace App\Http\Controllers;

use App\Services\SentryReporter;
use App\Services\StructuredLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    public function __construct(
        private readonly StructuredLogger $log,
        private readonly SentryReporter $sentry
    ) {}

    public function liveness(): JsonResponse
    {
        $dbCheck = $this->checkDatabase();

        return response()->json([
            'status' => $dbCheck['ok'] ? 'ok' : 'error',
            'app' => $this->appMeta(),
            'checks' => [
                'db' => $dbCheck,
            ],
        ], $dbCheck['ok'] ? 200 : 500);
    }

    public function readiness(): JsonResponse
    {
        $checks = [
            'db' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(includeAlertHooks: true),
        ];

        $ok = collect($checks)->every(fn ($check) => $check['ok']);

        return response()->json([
            'status' => $ok ? 'ok' : 'error',
            'app' => $this->appMeta(),
            'checks' => $checks,
        ], $ok ? 200 : 500);
    }

    public function queue(): JsonResponse
    {
        $queue = $this->checkQueue(includeAlertHooks: true);

        return response()->json([
            'status' => $queue['ok'] ? 'ok' : 'error',
            'app' => $this->appMeta(),
            'checks' => [
                'queue' => $queue,
            ],
        ], $queue['ok'] ? 200 : 500);
    }

    private function appMeta(): array
    {
        return [
            'name' => config('app.name'),
            'version' => config('app.version', 'dev'),
            'env' => config('app.env'),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        $driver = Cache::getDefaultDriver();
        $key = 'health:'.uniqid('', true);

        try {
            $store = Cache::store($driver);
            $store->put($key, 'ok', 5);
            $value = $store->get($key);
            $store->forget($key);

            return [
                'ok' => $value === 'ok',
                'driver' => $driver,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'driver' => $driver, 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(bool $includeAlertHooks = false): array
    {
        $driver = config('queue.default');
        $failedJobs = $this->checkFailedJobs();
        $threshold = (int) config('queue.alerts.threshold', 0);
        $alertTriggered = $failedJobs['count'] !== null && $failedJobs['count'] > $threshold;

        try {
            if ($driver === 'database') {
                $table = config('queue.connections.database.table', 'jobs');
                $connection = config('queue.connections.database.connection');
                $schema = $connection ? Schema::connection($connection) : Schema::getFacadeRoot();

                $result = [
                    'ok' => $schema->hasTable($table) && $failedJobs['ok'],
                    'driver' => $driver,
                    'connection' => $connection ?: config('database.default'),
                    'table' => $table,
                    'failed_jobs' => $failedJobs,
                    'alerts' => [
                        'enabled' => (bool) config('queue.alerts.enabled', false),
                        'threshold' => $threshold,
                        'triggered' => $alertTriggered,
                    ],
                ];
            } elseif ($driver === 'redis') {
                $connectionName = config('queue.connections.redis.connection', 'default');
                $queue = config('queue.connections.redis.queue', 'default');
                $ping = Redis::connection($connectionName)->ping();

                $result = [
                    'ok' => ($ping === true || $ping === 'PONG' || $ping === '+PONG') && $failedJobs['ok'],
                    'driver' => $driver,
                    'connection' => $connectionName,
                    'queue' => $queue,
                    'failed_jobs' => $failedJobs,
                    'alerts' => [
                        'enabled' => (bool) config('queue.alerts.enabled', false),
                        'threshold' => $threshold,
                        'triggered' => $alertTriggered,
                    ],
                ];
            } elseif ($driver === 'sync') {
                $result = [
                    'ok' => $failedJobs['ok'],
                    'driver' => $driver,
                    'failed_jobs' => $failedJobs,
                    'alerts' => [
                        'enabled' => (bool) config('queue.alerts.enabled', false),
                        'threshold' => $threshold,
                        'triggered' => $alertTriggered,
                    ],
                ];
            } else {
                $result = [
                    'ok' => $failedJobs['ok'],
                    'driver' => $driver,
                    'failed_jobs' => $failedJobs,
                    'alerts' => [
                        'enabled' => (bool) config('queue.alerts.enabled', false),
                        'threshold' => $threshold,
                        'triggered' => $alertTriggered,
                    ],
                ];
            }

            if ($includeAlertHooks) {
                $this->emitFailedJobsAlert($failedJobs['count'], $threshold);
            }

            return $result;
        } catch (\Throwable $e) {
            return ['ok' => false, 'driver' => $driver, 'error' => $e->getMessage()];
        }
    }

    private function checkFailedJobs(): array
    {
        $connection = config('queue.failed.database', config('database.default'));
        $table = config('queue.failed.table', 'failed_jobs');

        try {
            $schema = $connection ? Schema::connection((string) $connection) : Schema::getFacadeRoot();
            if (! $schema->hasTable($table)) {
                return [
                    'ok' => false,
                    'connection' => $connection,
                    'table' => $table,
                    'count' => null,
                    'error' => 'Failed jobs table is missing',
                ];
            }

            $count = (int) DB::connection($connection)->table($table)->count();

            return [
                'ok' => true,
                'connection' => $connection,
                'table' => $table,
                'count' => $count,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'connection' => $connection,
                'table' => $table,
                'count' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function emitFailedJobsAlert(?int $count, int $threshold): void
    {
        if (! config('queue.alerts.enabled', false) || $count === null) {
            return;
        }

        $cacheKey = 'queue:failed_jobs:alerted';
        $cooldown = (int) config('queue.alerts.cooldown_seconds', 300);

        if ($count <= $threshold) {
            Cache::forget($cacheKey);

            return;
        }

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, now()->toIso8601String(), now()->addSeconds($cooldown));

        $context = [
            'failed_jobs_count' => $count,
            'threshold' => $threshold,
            'flow' => 'queue_health',
        ];

        $this->log->error('queue_failed_jobs_alert', $context);
        $this->sentry->captureMessage('Failed jobs threshold exceeded', 'error', $context);
    }
}
