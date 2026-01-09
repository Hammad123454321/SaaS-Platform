<?php
namespace App\Http\Middleware;

use Closure;

class IsApi
{
    public function handle($request, Closure $next)
    {
        $request->attributes->set('isApi', true);
        return $next($request);
    }
}