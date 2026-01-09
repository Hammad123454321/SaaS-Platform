<?php

namespace App\Imports;

use App\Models\User;
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

class UsersImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithEvents
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
            'password' => bcrypt($row['password']),
            'phone' => $row['phone'] ?? null,
            'country_code' => $row['country_code'] ?? null,
            'country_iso_code' => $row['country_iso_code'] ?? null,
            'address' => $row['address'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'country' => $row['country'] ?? null,
            'zip' => $row['zip_code'] ?? null,
            'dob' => isset($row['dob']) ? $row['dob'] : null,
            'doj' => isset($row['doj']) ? $row['doj'] : null,
            'role' => $row['role_id']
        ];
        $require_ev = $this->isAdminOrHasAllDataAccess && $row['require_email_verification'] == 0 ? 0 : 1;
        $status = $this->isAdminOrHasAllDataAccess && $row['status'] == 1 ? 1 : 0;
        $data['email_verified_at'] = $require_ev == 0 ? now()->tz(config('app.timezone')) : null;
        $data['status'] = $status;
        // Create the user
        try {
            // Create the user
            $user = User::create($data);
            $user->assignRole($data['role']);

            // Notify the user if email verification is required
            if ($require_ev == 1) {
                $user->notify(new VerifyEmail($user));
            }

            // Attach user to the workspace
            $workspace = Workspace::find($this->workspaceId);
            $workspace->users()->attach($user->id);

            // Send account creation notification if email is configured
            if (isEmailConfigured()) {
                $account_creation_template = Template::where('type', 'email')
                    ->where('name', 'account_creation')
                    ->first();
                if (!$account_creation_template || ($account_creation_template->status !== 0)) {
                    $user->notify(new AccountCreation($user, $row['password']));
                }
            }
            logActivity('user', $user->id, $user->first_name . ' ' . $user->last_name);
            return $user;
        } catch (TransportExceptionInterface $e) {
            // Rollback user creation on email transport failure
            if (isset($user)) {
                $user->delete();
            }
            throw new \Exception('Users couldn\'t be created, please make sure email settings are operational.');
        } catch (Throwable $e) {
            // Rollback user creation on other errors
            if (isset($user)) {
                $user->delete();
            }
            throw new \Exception('Error creating users: ' . $e->getMessage());
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
            'password' => 'required|min:6',
            'password_confirmation' => 'required|min:6',
            'role_id' => 'required|exists:roles,id',
            'dob' => 'nullable|date_format:Y-m-d',
            'doj' => 'nullable|date_format:Y-m-d',
            'status' => 'required|boolean',
            'require_email_verification' => 'required|boolean'
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
            $role_id = null;

            // Iterate over each cell in the row
            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $value = $cell->getValue();

                // Check column and assign values
                if ($column === 'D') {
                    $cell->setValue((string) $value); // Ensure password is treated as a string
                    $password = $value;
                } elseif ($column === 'E') { // Password confirmation column
                    $cell->setValue((string) $value);
                    $password_confirmation = $value;
                } elseif ($column === 'G') { // Phone column
                    $phone = $value;
                } elseif ($column === 'H') { // Country code column
                    $country_code = $value;
                } elseif ($column === 'I') { // Country ISO code column
                    $country_iso_code = $value;
                } elseif ($column === 'C') { // Email column
                    $email = $value;
                } elseif ($column === 'F') { // Role ID column
                    $role_id = $value;
                }
            }

            // Skip the first row (headers)
            if ($rowIndex === 1) {
                continue;
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
                $exists = DB::table('users')
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
            $user = DB::table('users')->where('email', $email)->first();
            if ($user) {
                $manualValidationErrors[] = "The email has already been taken at Row " . ($rowIndex) . ". Please try a different email.";
            }

            // Validation: Unique email and password combination in the database
            $client = DB::table('clients')->where('email', $email)->first();
            if ($client && Hash::check($password, $client->password)) {
                $manualValidationErrors[] = "The combination of this email and password is already in use at Row " . ($rowIndex) . ". Please try a different email or password.";
            }

            // Validation: Role ID must exist in the roles table with guard_name 'web'
            if ($role_id) {
                $roleExists = DB::table('roles')
                    ->where('id', $role_id)
                    ->where('guard_name', 'web')
                    ->exists();

                if (!$roleExists) {
                    $manualValidationErrors[] = "The Role ID $role_id is not associated with users at Row " . ($rowIndex) . ".";
                }
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
                if (str_contains($error, 'required')) {
                    return "Password is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'min')) {
                    return "Password at Row {$rowNumber} must be at least 6 characters long.";
                }
                break;

            case 'password_confirmation':
                if (str_contains($error, 'required')) {
                    return "Password confirmation is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'min')) {
                    return "Password confirmation at Row {$rowNumber} must be at least 6 characters long.";
                }
                break;

            case 'role_id':
                if (str_contains($error, 'required')) {
                    return "Role ID is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'invalid')) {
                    return "Invalida Role ID {$value} at Row {$rowNumber}. Role does not exist.";
                }
                break;

            case 'dob':
            case 'doj':
                if (str_contains($error, 'date_format')) {
                    return "Invalid date format for {$field} at Row {$rowNumber}. Expected format: YYYY-MM-DD (e.g., 2024-01-01).";
                }
                break;

            case 'status':
                if (str_contains($error, 'required')) {
                    return "Status is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'boolean')) {
                    return "Status must be a boolean value (0 or 1) at Row {$rowNumber}.";
                }
                break;

            case 'require_email_verification':
                if (str_contains($error, 'required')) {
                    return "Require email verification is required at Row {$rowNumber}.";
                }
                if (str_contains($error, 'boolean')) {
                    return "Require email verification must be a boolean value (0 or 1) at Row {$rowNumber}.";
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
