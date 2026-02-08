<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->mfa_enabled || ! $user->mfa_confirmed_at) {
            return $next($request);
        }

        if ($request->session()->get('mfa_pending') || ! $request->session()->has('mfa_verified_at')) {
            return response()->json([
                'message' => 'Multi-factor authentication required.',
                'mfa_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
