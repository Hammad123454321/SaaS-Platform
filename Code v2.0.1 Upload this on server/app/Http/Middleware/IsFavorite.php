<?php
namespace App\Http\Middleware;

use Closure;

class IsFavorite
{
    public function handle($request, Closure $next)
    {
        $request->attributes->set('isFavorites', true);
        return $next($request);
    }
}