<?php

namespace App\Http\Middleware;

use Closure;

class CheckSignupEnabled
{
    public function handle($request, Closure $next)
    {
        $general_settings = get_settings('general_settings');

        if (isset($general_settings['allowSignup']) && $general_settings['allowSignup'] == 0) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => true, 'message' => get_label('action_not_allowed', 'This action is not allowed.')]);
            }
            return redirect('/')->with('error', get_label('action_not_allowed', 'This action is not allowed.'));
        }

        return $next($request);
    }
}
