<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', config('security.headers.x_content_type_options', 'nosniff'));
        $headers->set('X-Frame-Options', config('security.headers.x_frame_options', 'SAMEORIGIN'));
        $headers->set('Referrer-Policy', config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));

        $cspEnabled = (bool) config('security.headers.csp.enabled', false);
        $cspPolicy = trim((string) config('security.headers.csp.policy', ''));
        if ($cspEnabled && $cspPolicy !== '') {
            $headerName = config('security.headers.csp.report_only', true)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            $headers->set($headerName, $cspPolicy);
        }

        $hstsEnabled = (bool) config('security.headers.hsts.enabled', false);
        $hstsProdOnly = (bool) config('security.headers.hsts.only_in_production', true);
        if ($hstsEnabled
            && $this->isHttpsRequest($request)
            && (! $hstsProdOnly || app()->environment('production'))) {
            $maxAge = (int) config('security.headers.hsts.max_age', 31536000);
            $includeSubdomains = config('security.headers.hsts.include_subdomains', true) ? '; includeSubDomains' : '';
            $preload = config('security.headers.hsts.preload', false) ? '; preload' : '';
            $headers->set('Strict-Transport-Security', "max-age={$maxAge}{$includeSubdomains}{$preload}");
        }

        return $response;
    }

    private function isHttpsRequest(Request $request): bool
    {
        if ($request->isSecure()) {
            return true;
        }

        return strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    }
}
