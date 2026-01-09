<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class HasWorkspace
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $workspaceId = getWorkspaceId();

        // Case 1: If no workspace ID is set (0)
        if ($workspaceId == 0) {
            $errorMessage = get_label('must_workspace_participant', 'You must be participant in at least one workspace');

            if ($request->expectsJson()) {
                return formatApiResponse(true, $errorMessage);
            } else {
                if (!$request->ajax()) {
                    return redirect('/home')->with('error', $errorMessage);
                }
                return formatApiResponse(true, $errorMessage);
            }
        }

        // Case 2: If the workspace ID is invalid (doesn't exist in the workspaces table)
        if (!DB::table('workspaces')->where('id', $workspaceId)->exists()) {
            $errorMessage = get_label('invalid_workspace', 'The workspace you selected is invalid');

            if ($request->expectsJson()) {
                return formatApiResponse(true, $errorMessage);
            } else {
                if (!$request->ajax()) {
                    return redirect('/home')->with('error', $errorMessage);
                }
                return formatApiResponse(true, $errorMessage);
            }
        }

        return $next($request);
    }
}
