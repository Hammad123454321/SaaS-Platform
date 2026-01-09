<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SystemIntegrity
{
     public function handle(Request $request, Closure $next)
    {
        // Only check for authenticated users
        if (auth()->check() && !$this->checkSystemHealth()) {
            return redirect()->route('system.health');
        }

        return $next($request);
    }

    private function checkSystemHealth()
    {
        $key = $this->getKey('a1b2c3d4'); // maps to doctor_brown
        $data = get_settings($key);

        // dd($data);
        if (!$data) {
            return false;
        }

        $field = $this->getKey('e5f6g7h8'); // maps to code_bravo
        // dd($field);
        return isset($data[$field]);
    }

    private function getKey($hash)
    {
        $keys = [
            'a1b2c3d4' => 'doctor_brown',
            'e5f6g7h8' => 'code_bravo',
            'i9j0k1l2' => 'time_check',
            'm3n4o5p6' => 'code_adam',
            'q7r8s9t0' => 'dr_firestone'
        ];

        return $keys[$hash] ?? null;
    }
}
