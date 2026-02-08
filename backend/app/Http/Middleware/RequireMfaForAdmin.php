<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMfaForAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('security.require_mfa_for_admins')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $isAdmin = ($user->role ?? null) === 'admin' || (method_exists($user, 'hasRole') && $user->hasRole('admin'));
        if ($isAdmin && (!$user->mfa_enabled || !$user->mfa_confirmed_at)) {
            return response()->json([
                'message' => 'Multi-factor authentication is required for admin access.',
                'mfa_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
