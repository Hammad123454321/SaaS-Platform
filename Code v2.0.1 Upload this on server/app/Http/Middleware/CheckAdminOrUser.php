<?php

// app/Http/Middleware/CheckAdminOrLeaveEditor.php

namespace App\Http\Middleware;

use Closure;

class CheckAdminOrUser
{
    public function handle($request, Closure $next)
    {
        // Check if the user is an admin or a team member
        if (getGuardName() == 'web') {
            return $next($request);
        }

        // Check if the request expects a JSON response (usually API requests)
        if ($request->expectsJson()) {
            return response()->json([
                'error' => true,
                'message' => get_label('not_authorized', 'You are not authorized to perform this action.')
            ], 403);
        } elseif (!$request->ajax()) {
            return redirect('/home')->with('error', get_label('not_authorized', 'You are not authorized to perform this action.'));
        }
        return response()->json(['error' => true, 'message' => get_label('not_authorized', 'You are not authorized to perform this action.')]);
    }
}
