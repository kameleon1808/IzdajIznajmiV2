<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StructuredLogger
{
    public function __construct(private readonly ?Request $request = null)
    {
    }

    public function info(string $action, array $context = []): void
    {
        $this->log('info', $action, $context);
    }

    public function warning(string $action, array $context = []): void
    {
        $this->log('warning', $action, $context);
    }

    public function error(string $action, array $context = []): void
    {
        $this->log('error', $action, $context);
    }

    private function log(string $level, string $action, array $context = []): void
    {
        $request = request() ?? $this->request;
        $user = $request?->user();

        $payload = array_merge([
            'action' => $action,
            'user_id' => $context['user_id'] ?? $user?->id,
            'listing_id' => $context['listing_id'] ?? null,
            'ip' => $context['ip'] ?? $request?->ip(),
            'user_agent' => $context['user_agent'] ?? $request?->userAgent(),
            'route' => $context['route'] ?? $request?->path(),
            'request_id' => $context['request_id'] ?? $request?->header('X-Request-Id'),
        ], $context);

        Log::channel('structured')->{$level}($action, $payload);
    }
}
