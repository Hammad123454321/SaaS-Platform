<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;

class CustomThrottleRequests
{
    protected $rateLimiter;

    public function __construct(RateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }

    public function handle($request, Closure $next)
    {
        // Retrieve general settings
        $generalSettings = get_settings('general_settings');

        // Set maxAttempts and decayMinutes from settings or use default values
        $maxAttempts = $generalSettings['max_attempts'] ?? 5;
        $decayMinutes = $generalSettings['lock_time'] ?? 1;

        // If maxAttempts is set but is null or empty, do not apply locking mechanism
        if (isset($generalSettings['max_attempts']) && empty($generalSettings['max_attempts'])) {
            return $next($request);
        }

        // Resolve request signature
        $key = $this->resolveRequestSignature($request);

        // Check if the request has exceeded the rate limit
        if ($this->rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->rateLimiter->availableIn($key);
            $minutes = floor($retryAfter / 60);
            $seconds = $retryAfter % 60;

            $message = "Too many attempts. Please try again in {$minutes} minute(s) and {$seconds} second(s).";
            return response()->json(['error' => true, 'message' => $message]);
        }

        // Proceed with the request and capture the response
        $response = $next($request);

        // Check if the response indicates a successful login
        if ($this->isLoginSuccessful($request, $response)) {
            // Clear the rate limit attempts for the user on successful login
            $this->rateLimiter->clear($key);
        } else {
            // Hit the rate limiter for unsuccessful attempts
            $this->rateLimiter->hit($key, $decayMinutes * 60);
        }

        return $response;
    }


    protected function resolveRequestSignature($request)
    {
        return $request->ip();
    }

    protected function isLoginSuccessful(Request $request, $response)
    {
        // Get the response data as an array
        $responseData = $response->getData(true);

        // Check if the request is to one of the login routes
        return (
            ($request->is('users/authenticate') || $request->is('users/login'))
            && $response->status() === 200
            && isset($responseData['error']) && $responseData['error'] === false
        );
    }
}
