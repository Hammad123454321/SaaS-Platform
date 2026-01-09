<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class CustomCanMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$permissions
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $routeName = $request->route()->getName();
        $authUser = getAuthenticatedUser();

        // Check specific route conditions
        if ($routeName == 'users.profile' && getGuardName() == 'web' && $request->route('id') == $authUser->id) {
            return $next($request);
        }

        if ($routeName == 'clients.profile' && getGuardName() == 'client' && $request->route('id') == $authUser->id) {
            return $next($request);
        }

        // Check permissions if not matched by specific conditions
        foreach ($permissions as $permission) {
            if (checkPermission($permission)) {
                return $next($request);
            }
        }

        // Handle unauthorized access
        if ($request->ajax() || $request->wantsJson()) {
            return formatApiResponse(
                true,
                get_label('permission_denied', 'You do not have permission to access this resource.')
            );
        } else {
            return response()->view('auth.not-authorized', ['unauthorized' => true], 403);
        }
    }
}
