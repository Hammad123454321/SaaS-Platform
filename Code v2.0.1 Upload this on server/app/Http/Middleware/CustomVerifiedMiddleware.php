<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomVerifiedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $guard = getGuardName();
        $user = getAuthenticatedUser();
        // Check if the 'web' guard (for users) email is verified
        if ($guard == 'web') {
            $mainAdminId = getMainAdminId();
            if (!$user->hasVerifiedEmail() && $user->id != $mainAdminId) {
                return $this->handleUnverifiedEmail($request);
            }
        } else if ($guard == 'client') {
            if (!$user->hasVerifiedEmail()) {
                return $this->handleUnverifiedEmail($request);
            }
        }

        return $next($request);
    }

    protected function handleUnverifiedEmail(Request $request)
    {
        if ($request->expectsJson()) {
            return formatApiResponse(true, 'Email not verified.');
        } else {
            return redirect()->route('verification.notice'); // Customize this route
        }
    }
}
