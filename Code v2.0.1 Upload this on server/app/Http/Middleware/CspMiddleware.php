<?php

namespace App\Http\Middleware;

use Closure;

class CspMiddleware
{
    public function handle($request, Closure $next)
    {
        $domain = url('/'); // Get current application URL dynamically

        $response = $next($request);

        // Set CSP header with dynamic domain
        $response->header('Content-Security-Policy', "connect-src 'self' $domain");
        return $response;
    }
}
