<?php
// app/Http/Middleware/SanitizeInput.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanitizeInput
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
        // Define allowed HTML tags commonly used in TinyMCE
        $allowedTags = '
            <a><abbr><acronym><address><b><bdo><blockquote><br><caption><cite>
            <code><col><colgroup><dd><del><dfn><div><dl><dt><em><h1><h2><h3><h4>
            <h5><h6><hr><i><img><ins><kbd><label><legend><li><object><ol><p>
            <pre><q><s><samp><small><span><strike><strong><sub><sup><table>
            <tbody><td><tfoot><th><thead><tr><tt><u><ul><var><svg>';

        // Retrieve all inputs and sanitize them
        $inputs = $request->all();
        array_walk_recursive($inputs, function (&$value) use ($allowedTags) {
            // Only sanitize strings
            if (is_string($value)) {
                $value = strip_tags($value, $allowedTags);
            }
        });

        // Merge sanitized inputs back into the request
        $request->merge($inputs);

        return $next($request);
    }
}
