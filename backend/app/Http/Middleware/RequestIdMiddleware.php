<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        $request->attributes->set('request_id', $requestId);
        $request->headers->set(self::HEADER, $requestId);

        Log::withContext([
            'request_id' => $requestId,
            'release' => config('app.version', 'dev'),
        ]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            $payload = $response->getData(true);
            if (is_array($payload) && ! array_key_exists('request_id', $payload)) {
                $payload['request_id'] = $requestId;
                $response->setData($payload);
            }
        }

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $header = trim((string) $request->header(self::HEADER, ''));

        if ($header !== '' && preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $header)) {
            return $header;
        }

        return (string) Str::uuid();
    }
}
