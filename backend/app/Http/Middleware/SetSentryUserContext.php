<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set user context on the Sentry scope for authenticated requests.
 *
 * Only user_id and role are sent — no PII (email, name, phone) is included.
 * The middleware is a no-op when Sentry is disabled or unbound.
 */
class SetSentryUserContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('sentry') && config('services.sentry.enabled', false)) {
            $user = $request->user();
            if ($user) {
                $hub = app('sentry');
                if (method_exists($hub, 'configureScope')) {
                    $hub->configureScope(function ($scope) use ($user): void {
                        if (method_exists($scope, 'setUser')) {
                            $scope->setUser([
                                'id' => $user->id,
                                'segment' => $user->role ?? null,
                            ]);
                        }
                    });
                }
            }
        }

        return $next($request);
    }
}
