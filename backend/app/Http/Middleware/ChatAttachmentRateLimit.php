<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChatAttachmentRateLimit
{
    public function __construct(private RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasFile('attachments')) {
            return $next($request);
        }

        $userId = $request->user()?->id ?? $request->ip();
        $threadId = $request->route('conversation')?->id
            ?? $request->route('conversation')
            ?? $request->route('listing')?->id
            ?? $request->route('listing')
            ?? 'unknown';

        $key = sprintf('chat_attachments:%s:%s', $userId, $threadId);
        $max = (int) config('chat.rate_limits.attachments_per_10_minutes', 10);
        $decay = 600;

        if ($this->limiter->tooManyAttempts($key, $max)) {
            $retryAfter = $this->limiter->availableIn($key);
            return response()->json(
                ['message' => 'Too many attachments. Please slow down.'],
                429,
                ['Retry-After' => $retryAfter]
            );
        }

        $this->limiter->hit($key, $decay);

        return $next($request);
    }
}
