<?php

namespace App\Imports;

use App\Models\Client;
use App\Notifications\VerifyEmail;
use App\Models\Template;
use App\Models\Workspace;
use App\Notifications\AccountCreation;
use Throwable;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ClientsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithEvents
{
    use SkipsFailures;
    private $workspaceId;
    private $authenticatedUser;
    private $isAdminOrHasAllDataAccess;
    // To store validation errors
    public $validationErrors = [];
    public static $manualValidationErrors = [];

    public function __construct()
    {
        $this->workspaceId = getWorkspaceId();
        $this->isAdminOrHasAllDataAccess = isAdminOrHasAllDataAccess();
    }
    public function model(array $row)
    {
        if (!empty(self::$manualValidationErrors)) {
            return;
        }
        $row = $this->sanitizeAndTrim($row);
        // Pre-process row data
        $data = [
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'internal_purpose' => $row['is_for_internal_purpose'],
            'password' => $row['is_for_internal_purpose'] == 0 && $row['password'] ? bcrypt($row['password']) : null,
            'phone' => $row['phone'] ?? null,
            'country_code' => $row['country_code'] ?? null,
            'country_iso_code' => $row['country_iso_code'] ?? null,
            'company' => $row['company'] ?? null,
            'address' => $row['address'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'country' => $row['country'] ?? null,
            'zip' => $row['zip_code'] ?? null,
            'dob' => isset($row['dob']) ? $row['dob'] : null,
            'doj' => isset($row['doj']) ? $row['doj'] : null
        ];
        $require_ev = $this->isAdminOrHasAllDataAccess && $row['require_email_verification'] == 0 ? 0 : 1;
        $status = $data['internal_purpose'] == 0 && $this->isAdminOrHasAllDataAccess && $row['status'] && $row['status'] == 1 ? 1 : 0;
        $data['email_verified_at'] = $require_ev == 0 ? now()->tz(config('app.timezone')) : null;
        $data['status'] = $status;
        $role_id = Role::where('guard_name', 'client')->first()->id;
        // Create the client
        try {
            // Create the client
            $client = Client::create($data);
            $client->assignRole($role_id);

            // Notify the client if email verification is required
            if ($data['internal_purpose'] == 0 && $require_ev == 1) {
                $client->notify(new VerifyEmail($client));
                $client->update(['email_verification_mail_sent' => 1]);
            } else {
                $client->update(['email_verification_mail_sent' => 0]);
            }

            // Attach client to the workspace
            $workspace = Workspace::find($this->workspaceId);
            $workspace->clients()->attach($client->id);

            // Send account creation notification if email is configured
            if ($data['internal_purpose'] == 0 && isEmailConfigured()) {
                $account_creation_template = Template::where('type', 'email')
                    ->where('name', 'account_creation')
                    ->first();
                if (!$account_creation_template || ($account_creation_template->status !== 0)) {
                    $client->notify(new AccountCreation($client, $row['password']));
                    $client->update(['acct_create_mail_sent' => 1]);
                } else {
                    $client->update(['acct_create_mail_sent' => 0]);
                }
            } else {
                $client->update(['acct_create_mail_sent' => 0]);
            }
            logActivity('client', $client->id, $client->first_name . ' ' . $client->last_name);
            return $client;
        } catch (TransportExceptionInterface $e) {
            // Rollback client creation on email transport failure
            if (isset($client)) {
                $client->delete();
            }
            throw new \Exception('Clients couldn\'t be created, please make sure email settings are operational.');
        } catch (Throwable $e) {
            // Rollback client creation on other errors
            if (isset($client)) {
                $client->delete();
            }
            throw new \Exception('Error creating clients: ' . $e->getMessage());
        }
    }

    private function sanitizeAndTrim(array $row): array
    {
        $allowedTags = '
            <a><abbr><acronym><address><b><bdo><blockquote><br><caption><cite>
            <code><col><colgroup><dd><del><dfn><div><dl><dt><em><h1><h2><h3><h4>
            <h5><h6><hr><i><img><ins><kbd><label><legend><li><object><ol><p>
            <pre><q><s><samp><small><span><strike><strong><sub><sup><table>
            <tbody><td><tfoot><th><thead><tr><tt><u><ul><var>';

        return Arr::map($row, function ($value) use ($allowedTags) {
            // Only sanitize and trim strings
            if (is_string($value)) {
                return trim(strip_tags($value, $allowedTags));
            }
            return $value;
        });
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'password' => 'nullable|min:6',
            'password_confirmation' => 'nullable|min:6',
            'dob' => 'nullable|date_format:Y-m-d',
            'doj' => 'nullable|date_format:Y-m-d',
            'status' => 'nullable|boolean',
            'require_email_verification' => 'nullable|boolean',
            'is_for_internal_purpose' => 'required|boolean'
        ];
    }

    // Handle validation failures
    public function onFailure(...$failures)
    {
        foreach ($failures as $failure) {
            foreach ($failure->errors() as $error) {
                $rowNumber = $failure->row(); // Get row number
                $field = $failure->attribute(); // Get the field name
                $value = $failure->values()[$field] ?? null; // Get the invalid value

                // Format error message
                $message = $this->formatErrorMessage($field, $error, $rowNumber, $value);

                // Store the error message in the validationErrors array
                $this->validationErrors[] = $message;
            }
        }
    }


    public static function beforeImport(BeforeImport $event)
    {
        // Access the reader and get the first sheet
        $sheet = $event->getReader()->getSheet(0);

        // Initialize arrays to store manual validation errors and seen entries
        $manualValidationErrors = [];
        $seenPhoneCountryCombinations = [];
        $seenEmails = [];
        $seenEmailPasswordCombinations = [];

        // Iterate over each row in the sheet
        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            // Get the row's cell iterator (to access each cell)
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Iterate over all cells

            // Initialize variables for required fields
            $email = null;
            $password = null;
            $password_confirmation = null;
            $phone = null;
            $country_code = null;
            $country_iso_code = null;
            $isForInternalPurpose = null;
            $status = null;
            $requireEmailVerification = null;

            // Iterate over each cell in the row
            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $value = $cell->getValue();

                // Check column and assign values
                if ($column === 'E') {
                    $cell->setValue((string) $value); // Ensure password is treated as a string
                    $password = $value;
                } elseif ($column === 'F') { // Password confirmation column
                    $cell->setValue((string) $value);
                    $password_confirmation = $value;
                } elseif ($column === 'I') { // Phone column
                    $phone = $value;
                } elseif ($column === 'J') { // Country code column
                    $country_code = $value;
                } elseif ($column === 'K') { // Country ISO code column
                    $country_iso_code = $value;
                } elseif ($column === 'C') { // Email column
                    $email = $value;
                } elseif ($column === 'D') { // Is for internal purpose column
                    $isForInternalPurpose = $value;
                } elseif ($column === 'G') { // Status column
                    $status = $value;
                } elseif ($column === 'H') { // Require email verification column
                    $requireEmailVerification = $value;
                }
            }

            // Skip the first row (headers)
            if ($rowIndex === 1) {
                continue;
            }

            // Validation: Password and Password Confirmation are required if "Is for internal purpose" is 0
            if ($isForInternalPurpose == 0) {
                if (!$password || !$password_confirmation) {
                    $manualValidationErrors[] = "Password and password confirmation are required when 'Is for internal purpose' is 0 at Row " . ($rowIndex) . ".";
                }
                // Validation: Status and Require email verification are required if "Is for internal purpose" is 0
                if (!isset($status) || ($status != 0 && $status != 1)) {
                    $manualValidationErrors[] = "Status is required when 'Is for internal purpose' is 0 at Row " . ($rowIndex) . ".";
                }
                if (!isset($requireEmailVerification) || ($requireEmailVerification != 0 && $requireEmailVerification != 1)) {
                    $manualValidationErrors[] = "Require email verification is required when 'Is for internal purpose' is 0 at Row " . ($rowIndex) . ".";
                }
            }

            // Validation: Password and Password Confirmation must match
            if ($password && $password_confirmation && $password !== $password_confirmation) {
                $manualValidationErrors[] = "Password and password confirmation must match at Row " . ($rowIndex) . ".";
            }

            // Validation: Phone and Country Code dependencies
            if ($phone && !$country_code) {
                $manualValidationErrors[] = "Country code is required if phone is provided at Row " . ($rowIndex);
            } elseif ($country_code && !$phone) {
                $manualValidationErrors[] = "Phone is required if country code is provided at Row " . ($rowIndex);
            }

            // Validation: Country ISO Code required with Country Code
            if ($country_code && !$country_iso_code) {
                $manualValidationErrors[] = "Country ISO code is required if country code is provided at Row " . ($rowIndex);
            }

            // Check for duplicate phone and country_code combination in the sheet itself
            if ($phone && $country_code) {
                $phoneCountryKey = $phone . '-' . $country_code;

                if (in_array($phoneCountryKey, $seenPhoneCountryCombinations)) {
                    $manualValidationErrors[] = "The combination of phone and country code is duplicated in the sheet at Row " . ($rowIndex);
                } else {
                    $seenPhoneCountryCombinations[] = $phoneCountryKey;
                }
            }

            // Validation: Unique phone and country_code combination in the database
            if ($phone && $country_code) {
                $exists = DB::table('clients')
                    ->where('phone', $phone)
                    ->where('country_code', $country_code)
                    ->exists();

                if ($exists) {
                    $manualValidationErrors[] = "The combination of phone and country code has already been taken at Row " . ($rowIndex);
                }
            }

            // Check for duplicate emails in the sheet
            if ($email) {
                if (in_array($email, $seenEmails)) {
                    $manualValidationErrors[] = "The email is duplicated in the sheet at Row " . ($rowIndex) . ".";
                } else {
                    $seenEmails[] = $email;
                }
            }

            // Check for duplicate email-password combinations in the sheet
            if ($email && $password) {
                $emailPasswordKey = $email . '-' . $password;

                if (in_array($emailPasswordKey, $seenEmailPasswordCombinations)) {
                    $manualValidationErrors[] = "The combination of email and password is duplicated in the sheet at Row " . ($rowIndex) . ".";
                } else {
                    $seenEmailPasswordCombinations[] = $emailPasswordKey;
                }
            }

            // Validation: Unique email in the database
            $client = DB::table('clients')->where('email', $email)->first();
            if ($client) {
                $manualValidationErrors[] = "The email has already been taken at Row " . ($rowIndex) . ". Please try a different email.";
            }

            // Validation: Unique email and password combination in the database
            $user = DB::table('users')->where('email', $email)->first();
            if ($user && Hash::check($password, $user->password)) {
                $manualValidationErrors[] = "The combination of this email and password is already in use at Row " . ($rowIndex) . ". Please try a different email or password.";
            }
        }

        // After processing all rows, assign the errors to the static property if needed
        self::$manualValidationErrors = $manualValidationErrors;
    }


    public function registerEvents(): array
    {
        return [
            BeforeImport::class => [self::class, 'beforeImport'],
        ];
    }

    private function formatErrorMessage($field, $error, $rowNumber, $value = null)
    {
        switch ($field) {
            case 'first_name':
            case 'last_name':
                if (str_contains($error, 'required')) {
                    return ucfirst(str_replace('_', ' ', $field)) . " is required at Row {$rowNumber}.";
                }
                break;

            case 'email':
                if (str_contains($error, 'required')) {
                    return "Email is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'format')) {
                    return "Invalid email format at Row {$rowNumber}.";
                }
                break;

            case 'password':
                if (str_contains($error, 'min')) {
                    return "Password at Row {$rowNumber} must be at least 6 characters long.";
                }
                break;

            case 'password_confirmation':
                if (str_contains($error, 'min')) {
                    return "Password confirmation at Row {$rowNumber} must be at least 6 characters long.";
                }
                break;

            case 'dob':
            case 'doj':
                if (str_contains($error, 'date_format')) {
                    return "Invalid date format for {$field} at Row {$rowNumber}. Expected format: YYYY-MM-DD (e.g., 2024-01-01).";
                }
                break;

            case 'status':
                if (str_contains($error, 'boolean')) {
                    return "Status must be a boolean value (0 or 1) at Row {$rowNumber}.";
                }
                break;

            case 'require_email_verification':
                if (str_contains($error, 'boolean')) {
                    return "Require email verification must be a boolean value (0 or 1) at Row {$rowNumber}.";
                }
                break;

            case 'is_for_internal_purpose':
                if (str_contains($error, 'required')) {
                    return "Is for internal purpose is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'boolean')) {
                    return "Is for internal purpose must be a boolean value (0 or 1) at Row {$rowNumber}.";
                }
                break;

            default:
                return "Invalid data in field '{$field}' at Row {$rowNumber}.";
        }
        // Default fallback for other cases
        return null;
    }
    // Method to fetch all validation errors (including manual errors)
    public function getValidationErrors()
    {
        // Merge validation errors with the flattened manual validation errors
        $mergedErrors = array_merge($this->validationErrors, self::$manualValidationErrors);

        return $mergedErrors;
    }

    // Method to get manual validation errors
    public function getManualValidationErrors()
    {
        return self::$manualValidationErrors;
    }
}
