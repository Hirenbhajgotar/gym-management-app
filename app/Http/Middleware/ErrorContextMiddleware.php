<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ErrorContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Store request context for error reporting
        app()->instance('error.context', [
            'request_id' => $request->header('X-Request-ID') ?? uniqid(),
            'session_id' => $request->session()?->getId(),
            'user_id' => $request->user()?->id,
            'request_time' => now(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ]);

        return $next($request);
    }
}
