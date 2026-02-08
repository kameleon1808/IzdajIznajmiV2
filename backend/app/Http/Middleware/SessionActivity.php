<?php

namespace App\Http\Middleware;

use App\Services\SecuritySessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionActivity
{
    public function __construct(private SecuritySessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $this->sessions->touchSession($request);

        return $response;
    }
}
