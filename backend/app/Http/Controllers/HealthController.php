<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
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
            'queue' => $this->checkQueue(),
        ];

        $ok = collect($checks)->every(fn ($check) => $check['ok']);

        return response()->json([
            'status' => $ok ? 'ok' : 'error',
            'app' => $this->appMeta(),
            'checks' => $checks,
        ], $ok ? 200 : 500);
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
        $key = 'health:' . uniqid('', true);

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

    private function checkQueue(): array
    {
        $driver = config('queue.default');

        try {
            if ($driver === 'database') {
                $table = config('queue.connections.database.table', 'jobs');
                $connection = config('queue.connections.database.connection');
                $schema = $connection ? Schema::connection($connection) : Schema::getFacadeRoot();

                return [
                    'ok' => $schema->hasTable($table),
                    'driver' => $driver,
                    'connection' => $connection ?: config('database.default'),
                    'table' => $table,
                ];
            }

            if ($driver === 'redis') {
                $connectionName = config('queue.connections.redis.connection', 'default');
                $queue = config('queue.connections.redis.queue', 'default');
                $ping = Redis::connection($connectionName)->ping();

                return [
                    'ok' => $ping === true || $ping === 'PONG' || $ping === '+PONG',
                    'driver' => $driver,
                    'connection' => $connectionName,
                    'queue' => $queue,
                ];
            }

            if ($driver === 'sync') {
                return ['ok' => true, 'driver' => $driver];
            }

            return ['ok' => true, 'driver' => $driver];
        } catch (\Throwable $e) {
            return ['ok' => false, 'driver' => $driver, 'error' => $e->getMessage()];
        }
    }
}
