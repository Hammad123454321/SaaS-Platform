<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UniqueEmailPassword implements Rule
{
    protected $type;
    protected $context;

    public function __construct($type = null, $context = null)
    {
        $this->type = $type;
        $this->context = $context;
    }

    public function passes($attribute, $value)
    {
        $email = request()->input('email');
        $password = request()->input('password');

        if ($this->type === 'user') {
            // Check clients table if type is 'user'
            $client = DB::table('clients')->where('email', $email)->first();
            if ($client && Hash::check($password, $client->password)) {
                return false; // Password matches, so this combination is not unique
            }
        } elseif ($this->type === 'client') {
            // Check users table if type is 'client'
            $user = DB::table('users')->where('email', $email)->first();
            if ($user && Hash::check($password, $user->password)) {
                return false; // Password matches, so this combination is not unique
            }
        }

        return true; // Email-password combination is unique
    }

    public function message()
    {
        if ($this->context === 'forgot_password') {
            return 'The combination of this email and password is already in use. Please try a different password.';
        }

        return 'The combination of this email and password is already in use. Please try a different email or password.';
    }
}
