<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniquePhoneCountryCode implements Rule
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
        $phone = request()->input('phone');
        $countryCode = request()->input('country_code');

        // Ensure both phone and country_code are provided
        if (empty($phone) || empty($countryCode)) {
            return true; // Skip validation if phone or country_code is not provided
        }

        // Check the combination of phone and country_code
        if ($this->type === 'user') {
            // Check if the combination exists in the 'clients' table
            $client = DB::table('clients')
                ->where('phone', $phone)
                ->where('country_code', $countryCode)
                ->first();
            if ($client) {
                return false; // Combination already exists
            }
        } elseif ($this->type === 'client') {
            // Check if the combination exists in the 'users' table
            $user = DB::table('users')
                ->where('phone', $phone)
                ->where('country_code', $countryCode)
                ->first();
            if ($user) {
                return false; // Combination already exists
            }
        }

        return true; // Combination is unique
    }

    public function message()
    {
        return 'The combination of this phone number and country code is already in use. Please try a different phone number or country code.';
    }
}
