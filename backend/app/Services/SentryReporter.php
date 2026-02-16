<?php

namespace App\Services;

use Illuminate\Support\Arr;

class SentryReporter
{
    public function captureException(\Throwable $exception, array $context = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $hub = app('sentry');
        $this->captureWithScope($hub, function ($hub) use ($exception): void {
            if (method_exists($hub, 'captureException')) {
                $hub->captureException($exception);
            }
        }, $context);
    }

    public function captureMessage(string $message, string $level = 'error', array $context = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $hub = app('sentry');
        $this->captureWithScope($hub, function ($hub) use ($message, $level): void {
            if (method_exists($hub, 'captureMessage')) {
                $hub->captureMessage($message, $level);
            }
        }, $context);
    }

    private function enabled(): bool
    {
        return (bool) config('services.sentry.enabled', false) && app()->bound('sentry');
    }

    private function captureWithScope(object $hub, \Closure $capture, array $context): void
    {
        $tags = $this->buildTags($context);
        $extra = Arr::except($context, array_keys($tags));

        if (method_exists($hub, 'withScope')) {
            $hub->withScope(function ($scope) use ($capture, $hub, $tags, $extra): void {
                foreach ($tags as $key => $value) {
                    if (method_exists($scope, 'setTag')) {
                        $scope->setTag((string) $key, (string) $value);
                    }
                }
                if ($extra !== [] && method_exists($scope, 'setExtras')) {
                    $scope->setExtras($extra);
                }
                $capture($hub);
            });

            return;
        }

        $capture($hub);
    }

    /**
     * @return array<string, scalar>
     */
    private function buildTags(array $context): array
    {
        $request = request();
        $requestId = $context['request_id']
            ?? $request?->attributes->get('request_id')
            ?? $request?->header('X-Request-Id');

        $tags = array_filter([
            'release' => config('services.sentry.release', config('app.version', 'dev')),
            'environment' => config('services.sentry.environment', config('app.env')),
            'request_id' => $requestId,
            'queue' => $context['queue'] ?? null,
            'connection' => $context['connection'] ?? null,
            'flow' => $context['flow'] ?? null,
        ], fn ($value) => is_scalar($value) && $value !== '');

        foreach ($context as $key => $value) {
            if (str_starts_with((string) $key, 'tag_') && is_scalar($value) && $value !== '') {
                $tags[substr((string) $key, 4)] = $value;
            }
        }

        return $tags;
    }
}
