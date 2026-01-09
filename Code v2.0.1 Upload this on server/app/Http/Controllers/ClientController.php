<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Template;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\DeletionService;
use App\Notifications\VerifyEmail;
use Spatie\Permission\Models\Role;
use App\Models\UserClientPreference;
use App\Notifications\AccountCreation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use App\Rules\UniqueEmailPassword;
use App\Imports\ClientsImport;
use Maatwebsite\Excel\Facades\Excel;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $workspace = Workspace::find(getWorkspaceId());
        $clients = $workspace->clients ?? [];
        return view('clients.clients', ['clients' => $clients]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('clients.create_client');
    }

    /**
     * Store a new client.
     *
     * This endpoint creates a new client. The client must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Client Management
     *
     * @bodyParam first_name string required The first name of the client. Example: John
     * @bodyParam last_name string required The last name of the client. Example: Doe
     * @bodyParam company string nullable The company of the client. Example: Example Corp
     * @bodyParam email string required The email address of the client. Example: john.doe@example.com
     * @bodyParam phone string nullable The phone number of the client. Example: 1234567890
     * @bodyParam country_code string nullable The country code for the phone number. Example: +91
     * @bodyParam country_iso_code string nullable The ISO code for the phone number. Example: in
     * @bodyParam password string required The password for the client. Must be confirmed and at least 6 characters long. Example: password123
     * @bodyParam password_confirmation string required The password confirmation. Required if password is provided. Example: password123
     * @bodyParam address string nullable The address of the client. Example: 123 Main St
     * @bodyParam city string nullable The city of the client. Example: New York
     * @bodyParam state string nullable The state of the client. Example: NY
     * @bodyParam country string nullable The country of the client. Example: USA
     * @bodyParam zip string nullable The ZIP code of the client. Example: 10001
     * @bodyParam dob string nullable The date of birth of the user in the format specified in the general settings. Example: 1990-01-01
     * @bodyParam doj string nullable The date of joining in the format specified in the general settings. Example: 2024-01-01
     * @bodyParam internal_purpose string nullable Set to 'on' if the client is for internal purposes. Example: on
     * @bodyParam profile file nullable The profile photo of the client.
     * @bodyParam status boolean required 0 or 1. If Deactivated (0), the client won't be able to log in to their account.
     * Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user, else 0 will be considered by default. Example: 1
     * @bodyParam require_ev boolean required 0 or 1. If Yes (1) is selected, the client will receive a verification link via email.
     * Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user, else 1 will be considered by default. Example: 1
     *
     * @response 200 {
     * "error": false,
     * "message": "Client created successfully.",
     * "data": {
     * "id": 183,
     * "first_name": "API",
     * "last_name": "Client",
     * "company": "test",
     * "email": "777@gmail.com",
     * "phone": "+91 1111111111",
     * "address": "Test adr",
     * "city": "Test cty",
     * "state": "Test ct",
     * "country": "test ctr",
     * "zip": "111-111",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/a5xT73btrbk7sybc0768Bv8xlBn16ROK1Znf1Ddc.webp",
     * "status": "1",
     * "internal_purpose": 0,
     * "created_at": "09-08-2024 19:22:17",
     * "updated_at": "09-08-2024 20:10:06",
     * "assigned": {
     * "projects": 0,
     * "tasks": 0
     * }
     * }
     * }
     * @response 422 {
     * "error": true,
     * "message": "Validation errors occurred",
     * "errors": {
     *   "first_name": [
     *     "The first name field is required."
     *   ],
     *   "last_name": [
     *     "The last name field is required."
     *   ],
     *   "email": [
     *     "The email has already been taken."
     *   ]
     * }
     * }
     * @response 500 {
     *   "error": true,
     *   "message": "Client couldn’t be created, please make sure email settings are operational."
     * }
     */
    public function store(Request $request)
    {
        ini_set('max_execution_time', 300);
        $isApi = request()->get('isApi', false);
        $internal_purpose = $request->has('internal_purpose') && $request->input('internal_purpose') == 'on' ? 1 : 0;

        $require_ev = $request->has('require_ev') ? $request->input('require_ev') : 1;
        if ($require_ev == 1 && !isEmailConfigured()) {
            return response()->json(
                [
                    'error' => true,
                    'message' => 'Email settings are not configured. Please configure email settings to enable email verification.'
                ]
            );
        }

        try {
            $request->merge([
                'phone' => str_replace(' ', '', $request->input('phone')),
                'country_code' => str_replace(' ', '', $request->input('country_code')),
            ]);
            $formFields = $request->validate([
                'first_name' => 'required',
                'last_name' => 'required',
                'company' => 'nullable',
                'email' => ['required', 'email', 'unique:clients,email'],
                'phone' => 'nullable|required_with:country_code|unique:clients,phone,NULL,id,country_code,' . $request->country_code,
                'country_code' => 'nullable|required_with:phone',
                'country_iso_code' => 'nullable',
                'password' => $internal_purpose ? 'nullable|confirmed|min:6' : 'required|min:6',
                'password_confirmation' => $internal_purpose ? 'nullable' : 'required_with:password|same:password',
                'address' => 'nullable',
                'city' => 'nullable',
                'state' => 'nullable',
                'country' => 'nullable',
                'zip' => 'nullable',
                'profile' => 'file|image',
                'dob' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($isApi) {
                        $dob = request()->input('dob');
                        $errors = validate_date_format_and_order($value, $dob, $isApi ? 'Y-m-d' : null, startDateLabel: 'DOB', startDateKey: 'dob');

                        if (!empty($errors['dob'])) {
                            foreach ($errors['dob'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
                'doj' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($isApi) {
                        $doj = request()->input('doj');
                        $errors = validate_date_format_and_order($doj, $value, $isApi ? 'Y-m-d' : null, endDateLabel: 'DOJ', endDateKey: 'doj');

                        if (!empty($errors['doj'])) {
                            foreach ($errors['doj'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
            ], [
                'phone.required_with' => 'The phone number must be provided when the country code is present.',
                'country_code.required_with' => 'The country code must be provided when the phone number is present.',
                'phone.unique' => 'The combination of this phone number and country code is already in use.',
                'profile.image' => 'The file must be a valid image (jpg, jpeg, png, gif, bmp, webp).'
            ]);

            $uniqueEmailPasswordRule = new UniqueEmailPassword('client');
            if (!$uniqueEmailPasswordRule->passes('password', $request->input('password'))) {
                return formatApiValidationError($isApi, ['email' => [$uniqueEmailPasswordRule->message()]]);
            }
            if (!$internal_purpose && $request->input('password')) {
                $password = $request->input('password');
                $formFields['password'] = bcrypt($formFields['password']);
            }

            $formFields['internal_purpose'] =  $internal_purpose;

            if ($request->hasFile('profile')) {
                $formFields['photo'] = $request->file('profile')->store('photos', 'public');
            } else {
                $formFields['photo'] = 'photos/no-image.jpg';
            }

            $role_id = Role::where('guard_name', 'client')->first()->id;
            $workspace = Workspace::find(getWorkspaceId());

            $require_ev = isAdminOrHasAllDataAccess() && $request->has('require_ev') && $request->input('require_ev') == 0 ? 0 : 1;
            $status = !$internal_purpose && isAdminOrHasAllDataAccess() && $request->has('status') && $request->input('status') == 1 ? 1 : 0;

            if (!$internal_purpose && $require_ev == 0) {
                $formFields['email_verified_at'] = now()->tz(config('app.timezone'));
            }
            $formFields['status'] = $status;

            $dob = $request->input('dob');
            $doj = $request->input('doj');
            if ($dob) {
                $formFields['dob'] = format_date($dob, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            }
            if ($doj) {
                $formFields['doj'] = format_date($doj, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            }

            $client = Client::create($formFields);

            try {
                if (!$internal_purpose && $require_ev == 1) {
                    $client->notify(new VerifyEmail($client));
                    $client->update(['email_verification_mail_sent' => 1]);
                } else {
                    $client->update(['email_verification_mail_sent' => 0]);
                }
                $workspace->clients()->attach($client->id);
                $client->assignRole($role_id);
                if (!$internal_purpose && isEmailConfigured()) {

                    $account_creation_template = Template::where('type', 'email')
                        ->where('name', 'account_creation')
                        ->first();
                    if (!$account_creation_template || ($account_creation_template->status !== 0)) {
                        $client->notify(new AccountCreation($client, $password));
                        $client->update(['acct_create_mail_sent' => 1]);
                    } else {
                        $client->update(['acct_create_mail_sent' => 0]);
                    }
                } else {
                    $client->update(['acct_create_mail_sent' => 0]);
                }
                $data = formatClient($client);
                $data['require_ev'] = $require_ev;
                return formatApiResponse(false, 'Client created successfully.', ['id' => $client->id, 'data' => $data]);
            } catch (TransportExceptionInterface $e) {

                $client = Client::findOrFail($client->id);
                $client->delete();
                return response()->json(['error' => true, 'message' => 'Client couldn\'t be created, please make sure email settings are oprational.']);
            } catch (Throwable $e) {
                // Catch any other throwable, including non-Exception errors

                $client = Client::findOrFail($client->id);
                $client->delete();
                return response()->json(['error' => true, 'message' => 'Client couldn\'t be created, please try again later.' . $e->getMessage()]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        }
    }

    public function showBulkUploadForm(Request $request)
    {
        $sampleFileUrl = asset('storage/files/Clients bulk upload sample.xlsx');
        $helpUrl = asset('storage/files/Clients bulk upload help and instructions.pdf');
        return view('bulk-upload', [
            'entity' => 'clients',
            'form_action' => route('clients.bulkUpload'),
            'sample_file_url' => $sampleFileUrl,
            'help_url' => $helpUrl
        ]);
    }

    public function importBulkClients(Request $request)
    {
        // Validate file type (ensure it's Excel or CSV)
        $request->validate([
            'bulk_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            // Initialize the import class
            $import = new ClientsImport;

            // Use the import class for bulk upload
            Excel::import($import, $request->file('bulk_file'));

            // Check if there are any validation errors
            $validationErrors = $import->getValidationErrors();
            $validationErrors = array_filter($validationErrors, function ($value) {
                return $value !== null && $value !== '';
            });
            if (!empty($validationErrors)) {
                // Return validation errors if any
                return response()->json([
                    'error' => true,
                    'message' => 'Validation errors occurred.',
                    'validation_errors' => $validationErrors
                ], 400);
            }

            // If no validation errors, return success message
            return response()->json([
                'error' => false,
                'message' => 'Clients imported successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $workspace = Workspace::find(getWorkspaceId());
        $client = Client::findOrFail($id);
        $projects = isAdminOrHasAllDataAccess('client', $id) ? $workspace->projects : $client->projects;
        $tasks = $client->tasks()->count();
        $users = $workspace->users;
        $clients = $workspace->clients;
        return view('clients.client_profile', ['client' => $client, 'projects' => $projects, 'tasks' => $tasks, 'users' => $users, 'clients' => $clients, 'auth_user' => getAuthenticatedUser()]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $client = Client::findOrFail($id);
        return view('clients.update_client')->with('client', $client);
    }

    /**
     * Update an existing client.
     *
     * This endpoint updates the details of an existing client. The client must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Client Management
     *
     * @bodyParam id integer required The ID of the client to be updated. Example: 1
     * @bodyParam first_name string required The first name of the client. Example: John
     * @bodyParam last_name string required The last name of the client. Example: Doe
     * @bodyParam company string nullable The company of the client. Example: XYZ
     * @bodyParam email string required The email address of the client. Example: john.doe@example.com
     * @bodyParam password string nullable The new password for the client. Can only be updated if `is_admin_or_has_all_data_access` is true for the logged-in user. Example: newpassword123
     * @bodyParam password_confirmation string required_with:password The password confirmation. Example: newpassword123
     * @bodyParam address string nullable The address of the client. Example: 123 Main St
     * @bodyParam phone string nullable The phone number of the client. Example: 1234567890
     * @bodyParam country_code string nullable The country code for the phone number. Example: +91
     * @bodyParam country_iso_code string nullable The ISO code for the phone number. Example: in
     * @bodyParam city string nullable The city of the client. Example: New York
     * @bodyParam state string nullable The state of the client. Example: NY
     * @bodyParam country string nullable The country of the client. Example: USA
     * @bodyParam zip string nullable The ZIP code of the client. Example: 10001
     * @bodyParam dob string nullable The date of birth of the user in the format specified in the general settings. Example: 1990-01-01
     * @bodyParam doj string nullable The date of joining in the format specified in the general settings. Example: 2024-01-01
     * @bodyParam internal_purpose string nullable Set to 'on' if the client is for internal purposes. Example: on
     * @bodyParam profile file nullable The new profile photo of the client.
     * @bodyParam status boolean required 0 or 1. If Deactivated (0), the client won't be able to log in to their account.
     * Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user, else the current status will be considered by default. Example: 1
     * @bodyParam require_ev boolean required 0 or 1. If Yes (1) is selected, the client will receive a verification link via email.
     * Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user, else the current require_ev will be considered by default. Example: 1
     *
     * @response 200 {
     * "error": false,
     * "message": "Client updated successfully.",
     * "data": {
     * "id": 183,
     * "first_name": "API",
     * "last_name": "Client",
     * "company": "test",
     * "email": "777@gmail.com",
     * "phone": "+91 1111111111",
     * "address": "Test adr",
     * "city": "Test cty",
     * "state": "Test ct",
     * "country": "test ctr",
     * "zip": "111-111",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/a5xT73btrbk7sybc0768Bv8xlBn16ROK1Znf1Ddc.webp",
     * "status": "1",
     * "internal_purpose": 0,
     * "created_at": "09-08-2024 19:22:17",
     * "updated_at": "09-08-2024 20:10:06",
     * "assigned": {
     * "projects": 0,
     * "tasks": 0
     * }
     * }
     * }
     * @response 422 {
     * "error": true,
     * "message": "Validation errors occurred",
     * "errors": {
     *   "first_name": [
     *     "The first name field is required."
     *   ],
     *   "last_name": [
     *     "The last name field is required."
     *   ],
     *   "email": [
     *     "The email has already been taken."
     *   ]
     * }
     * }
     * @response 500 {
     *   "error": true,
     *   "message": "Client couldn't be updated."
     * }
     */
    public function update(Request $request, $id = null)
    {
        ini_set('max_execution_time', 300);
        $isApi = request()->get('isApi', false);
        if ($id) {
            $request->merge(['id' => $id]);
        } else {
            $id = $request->input('id');
        }
        if ($id) {
            $client = Client::find($id);
            if (!$client) {
                return response()->json(['error' => true, 'message' => 'Client not found.']);
            }
            $internal_purpose = $request->has('internal_purpose') && $request->input('internal_purpose') == 'on' ? 1 : 0;
            if ($internal_purpose && $request->has('password') && !empty($request->input('password'))) {
                $request->merge(['password' => NULL]);
            }
        }
        $request->merge([
            'phone' => str_replace(' ', '', $request->input('phone')),
            'country_code' => str_replace(' ', '', $request->input('country_code')),
        ]);
        try {
            $rules = [
                'id' => 'required|exists:clients,id',
                'first_name' => 'required',
                'last_name' => 'required',
                'company' => 'nullable',
                'email' => [
                    'required',
                    Rule::unique('clients')->ignore($id),
                ],
                'phone' => [
                    'nullable',
                    'required_with:country_code',
                    Rule::unique('clients')->ignore($request->id)->where(function ($query) use ($request) {
                        return $query->where('country_code', $request->country_code);
                    }),
                ],
                'country_code' => 'nullable|required_with:phone',
                'country_iso_code' => 'nullable',
                'address' => 'nullable',
                'city' => 'nullable',
                'state' => 'nullable',
                'country' => 'nullable',
                'zip' => 'nullable',
                'profile' => 'nullable|file|image',
                'dob' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($isApi) {
                        $dob = request()->input('dob');
                        $errors = validate_date_format_and_order($value, $dob, $isApi ? 'Y-m-d' : null, startDateLabel: 'DOB', startDateKey: 'dob');

                        if (!empty($errors['dob'])) {
                            foreach ($errors['dob'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
                'doj' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($isApi) {
                        $doj = request()->input('doj');
                        $errors = validate_date_format_and_order($doj, $value, $isApi ? 'Y-m-d' : null, endDateLabel: 'DOJ', endDateKey: 'doj');

                        if (!empty($errors['doj'])) {
                            foreach ($errors['doj'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
            ];
            if (!$internal_purpose && $client->password === NULL) {
                $rules['password'] = 'required|min:6';
            } else {
                $rules['password'] = 'nullable';
            }
            $rules['password_confirmation'] = 'required_with:password|same:password';

            $formFields = $request->validate($rules, [
                'phone.required_with' => 'The phone number must be provided when the country code is present.',
                'country_code.required_with' => 'The country code must be provided when the phone number is present.',
                'phone.unique' => 'The combination of this phone number and country code is already in use.',
                'profile.image' => 'The file must be a valid image (jpg, jpeg, png, gif, bmp, webp).'
            ]);

            if (request()->filled('password')) {
                $uniqueEmailPasswordRule = new UniqueEmailPassword('client');
                if (!$uniqueEmailPasswordRule->passes('password', $request->input('password'))) {
                    return formatApiValidationError($isApi, ['email' => [$uniqueEmailPasswordRule->message()]]);
                }
            }
            if ($request->hasFile('profile')) {
                if ($client->photo != 'photos/no-image.jpg' && $client->photo !== null)
                    Storage::disk('public')->delete($client->photo);
                $formFields['photo'] = $request->file('profile')->store('photos', 'public');
            }

            $status = $internal_purpose ? $client->status : (isAdminOrHasAllDataAccess() && $request->has('status') ? $request->input('status') : $client->status);
            $formFields['status'] = $status;

            if (!$internal_purpose && isAdminOrHasAllDataAccess() && isset($formFields['password']) && !empty($formFields['password'])) {
                $password = $formFields['password'];
                $formFields['password'] = bcrypt($formFields['password']);
            } else {
                unset($formFields['password']);
            }

            $formFields['internal_purpose'] = $internal_purpose;

            $dob = $request->input('dob');
            $doj = $request->input('doj');
            if ($dob) {
                $formFields['dob'] = format_date($dob, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            }
            if ($doj) {
                $formFields['doj'] = format_date($doj, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            }

            $client->update($formFields);

            $require_ev = 0;

            if (!$internal_purpose && $client->email_verified_at === null && $client->email_verification_mail_sent === 0) {
                $require_ev = isAdminOrHasAllDataAccess() && $request->has('require_ev') && $request->input('require_ev') == 0 ? 0 : 1;
            }

            $send_account_creation_email = 0;

            if (!$internal_purpose && $client->acct_create_mail_sent === 0) {
                $send_account_creation_email = 1;
            }

            try {
                if (!$internal_purpose && $require_ev == 1) {
                    $client->notify(new VerifyEmail($client));
                    $client->update(['email_verification_mail_sent' => 1]);
                }
                if (!$internal_purpose && $send_account_creation_email == 1 && isEmailConfigured()) {
                    $account_creation_template = Template::where('type', 'email')
                        ->where('name', 'account_creation')
                        ->first();
                    if (!$account_creation_template || ($account_creation_template->status !== 0)) {
                        $client->notify(new AccountCreation($client, $password));
                        $client->update(['acct_create_mail_sent' => 1]);
                    }
                }
            } catch (TransportExceptionInterface $e) {
                // dd($e->getMessage());
            } catch (Throwable $e) {
                // Catch any other throwable, including non-Exception errors
                // dd($e->getMessage());
            }
            return formatApiResponse(
                false,
                'Client updated successfully.',
                [
                    'id' => $client->id,
                    'data' => formatClient($client),
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'Client couldn\'t be updated.'
            ], 500);
        }
    }


    public function get($id)
    {
        $client = Client::findOrFail($id);
        return response()->json(['client' => $client]);
    }

    /**
     * Remove the specified client.
     *
     * This endpoint deletes a client based on the provided ID. The request must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Client Management
     *
     * @urlParam id int required The ID of the client to be deleted. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "error": false,
     *     "message": "Client deleted successfully.",
     *     "id": "1",
     *     "title": "Jane Doe",
     *     "data": []
     *   }
     * }
     *
     * @response 200 {
     *   "data": {
     *     "error": true,
     *     "message": "Client not found.",
     *     "data": []
     *   }
     * }
     *
     * @response 500 {
     *   "data": {
     *     "error": true,
     *     "message": "An internal server error occurred."
     *   }
     * }
     */

    public function destroy($id)
    {
        $client = Client::find($id);
        $response = DeletionService::delete(Client::class, $id, 'Client');
        $responseData = json_decode($response->getContent(), true);
        if ($responseData['error']) {
            // Handle error response
            return response()->json($responseData);
        }
        UserClientPreference::where('user_id', 'c_' . $id)->delete();
        $client->todos()->delete();
        return $response;
    }


    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:clients,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedClients = [];
        $deletedClientNames = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $client = Client::findOrFail($id);
            if ($client) {
                $deletedClients[] = $id;
                $deletedClientNames[] = $client->first_name . ' ' . $client->last_name;
                DeletionService::delete(Client::class, $id, 'Client');
                UserClientPreference::where('user_id', 'c_' . $id)->delete();
                $client->todos()->delete();
            }
        }
        return response()->json(['error' => false, 'message' => 'Clients(s) deleted successfully.', 'id' => $deletedClients, 'titles' => $deletedClientNames]);
    }



    public function list()
    {
        $workspace = Workspace::find(getWorkspaceId());
        $search = request('search');
        $sort = request('sort') ?: 'id';
        $order = request('order') ?: 'DESC';
        $type = request('type');
        $typeId = request('typeId');
        $statuses = request('statuses', []);
        $clientTypes = request('clientTypes', []);
        $ev_statuses = request('ev_statuses', []);

        if ($type && $typeId) {
            if ($type == 'project') {
                $project = Project::find($typeId);
                $clients = $project->clients();
            } elseif ($type == 'task') {
                $task = Task::find($typeId);
                $clients = $task->project->clients();
            } else {
                $clients = $workspace->clients();
            }
        } else {
            $clients = $workspace->clients();
        }

        $clients = $clients->when($search, function ($query) use ($search) {
            return $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('company', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('clients.id', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $search . '%']);
            });
        });

        if (!empty($statuses)) {
            $clients = $clients->whereIn('status', $statuses);
        }

        if (!empty($clientTypes)) {
            $clients = $clients->whereIn('internal_purpose', $clientTypes);
        }

        if (!empty($ev_statuses)) {
            // Apply email verification filter
            $clients = $clients->where(function ($query) use ($ev_statuses) {
                // Treat internal_purpose == 1 as not applicable
                $query->where(function ($query) use ($ev_statuses) {
                    if (in_array(1, $ev_statuses)) {
                        $query->orWhereNotNull('email_verified_at');
                    }
                    if (in_array(0, $ev_statuses)) {
                        $query->orWhereNull('email_verified_at');
                    }
                    if (in_array(2, $ev_statuses)) {
                        // Clients with internal_purpose == 1 are treated as not applicable
                        $query->orWhere('internal_purpose', 1); // Only include clients with internal purpose
                    }
                });
            });
        }

        $totalclients = $clients->count();

        $canEdit = checkPermission('edit_clients');
        $canDelete = checkPermission('delete_clients');
        $guardName = getGuardName();
        $authUserId = getAuthenticatedUser()->id;
        $clients = $clients->select('clients.*')
            ->distinct()
            ->orderBy($sort, $order)
            ->paginate(request('limit'))
            ->through(function ($client) use ($workspace, $canEdit, $canDelete, $authUserId, $guardName) {
                $actions = '';
                if ($canEdit) {
                    $actions .= '<a href="' . url("/clients/edit/{$client->id}") . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $client->id . '" data-type="clients">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                $actions = $actions ?: '-';

                $badge = $client->status === 1
                    ? '<span class="badge bg-success">' . get_label('active', 'Active') . '</span>'
                    : '<span class="badge bg-danger">' . get_label('deactive', 'Deactive') . '</span>';

                $profileHtml = "<div class='avatar avatar-sm pull-up' title='{$client->first_name} {$client->last_name}'>
                <a href='" . url("/clients/profile/{$client->id}") . "'>
                    <img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle'>
                </a>
            </div>";

                $emailVerificationBadge = is_null($client->email_verified_at) && $client->internal_purpose == 0
                    ? '<span class="badge bg-danger ms-1">' . get_label('unverified_email', 'Unverified Email') . '</span>'
                    : '';

                $formattedHtml = '<div class="d-flex align-items-center mt-2">' .
                    $profileHtml .
                    '<div class="mx-2">' .
                    '<h6 class="mb-1">' .
                    $client->first_name . ' ' . $client->last_name . ' ' .
                    $badge .
                    '</h6>' .
                    '<div>' .
                    '<small class="text-muted">' . $client->email . '</small>' .
                    (!empty($client->email) ?
                        ' <a href="mailto:' . $client->email . '" class="text-decoration-none" title="' . get_label('send_mail', 'Send Mail') . '"><i class="bx bx-envelope"></i></a>'
                        : '') .
                    '</div>' .
                    $emailVerificationBadge;

                if ($client->internal_purpose == 1) {
                    $formattedHtml .= '<span class="badge bg-info ms-2">' . get_label('internal_purpose', 'Internal Purpose') . '</span>';
                }


                $formattedHtml .= '</div>' .
                    '</div>';

                $phone = (empty($client->country_code) && empty($client->phone)) ? '-' : (
                    // If both country code and phone exist, show both with the call icon
                    (!empty($client->country_code) && !empty($client->phone)) ?
                    $client->country_code . ' ' . $client->phone . ' ' .
                    (
                        // Only show call icon if guard is not 'web' or auth user is different
                        ($guardName !== 'client' || $authUserId !== $client->id) ?
                        '<a href="tel:' . $client->country_code . $client->phone . '" class="text-decoration-none" title="' . get_label('make_call', 'Make Call') . '"><i class="bx bx-phone-call text-primary"></i></a>'
                        : ''
                    )
                    :
                    // If only the phone exists, show phone number with the call icon
                    (!empty($client->phone) ?
                        $client->phone . ' ' .
                        (
                            // Only show call icon if guard is not 'web' or auth user is different
                            ($guardName !== 'client' || $authUserId !== $client->id) ?
                            '<a href="tel:' . $client->phone . '" class="text-decoration-none" title="' . get_label('make_call', 'Make Call') . '"><i class="bx bx-phone-call text-primary"></i></a>'
                            : ''
                        )
                        :
                        // If only the country code exists, show the country code
                        ($client->country_code ? $client->country_code : '')
                    )
                );

                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'company' => $client->company,
                    'email' => $client->email,
                    'phone' => $phone,
                    'profile' => $formattedHtml,
                    'status' => $client->status,
                    'internal_purpose' => $client->internal_purpose,
                    'created_at' => format_date($client->created_at, true),
                    'updated_at' => format_date($client->updated_at, true),
                    'assigned' => '<div class="d-flex justify-content-start align-items-center">' .
                        '<div class="text-center mx-4">' .
                        '<a href="javascript:void(0);" class="viewAssigned" data-type="projects" data-id="' . 'client_' . $client->id . '" data-client="' . $client->first_name . ' ' . $client->last_name . '">' .
                        '<span class="badge rounded-pill bg-primary">' . (isAdminOrHasAllDataAccess('client', $client->id) ? count($workspace->projects) : count($client->projects)) . '</span>' .
                        '</a>' .
                        '<div>' . get_label('projects', 'Projects') . '</div>' .
                        '</div>' .
                        '<div class="text-center">' .
                        '<a href="javascript:void(0);" class="viewAssigned" data-type="tasks" data-id="' . 'client_' . $client->id . '" data-client="' . $client->first_name . ' ' . $client->last_name . '">' .
                        '<span class="badge rounded-pill bg-primary">' . (isAdminOrHasAllDataAccess('client', $client->id) ? count($workspace->tasks) : $client->tasks()->count()) . '</span>' .
                        '</a>' .
                        '<div>' . get_label('tasks', 'Tasks') . '</div>' .
                        '</div>' .
                        '</div>',
                    'actions' => $actions
                ];
            });

        return response()->json([
            'rows' => $clients->items(),
            'total' => $totalclients,
        ]);
    }

    /**
     * List or search clients.
     *
     * This endpoint retrieves a list of clients based on various filters. The user must be authenticated to perform this action. The request allows filtering by status, search term, type, type_id, and other parameters.
     *
     * @authenticated
     *
     * @group Client Management
     *
     * @urlParam id int optional The ID of the client to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter clients by id, first name, last name, comapny, phone, or email. Example: John
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, company, phone, created_at, and updated_at. Example: id
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam status int optional The status ID to filter clients by, either 0 or 1. Example: 1
     * @queryParam type string optional The type of filter to apply, either "project" or "task". Example: project
     * @queryParam type_id int optional The ID associated with the type filter. Example: 3
     * @queryParam limit int optional The number of clients per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     *
     * @response 200 {
     *  "error": false,
     *  "message": "Clients retrieved successfully",
     *  "total": 1,
     *  "clients": [
     *    {
     *      "id": 185,
     *      "first_name": "Client",
     *      "last_name": "Test",
     *      "company": "Test Company",
     *      "email": "client@test.com",
     *      "phone": "1 5555555555",
     *      "status": 1,
     *      "internal_purpose": 1,
     *      "created_at": "10-06-2024",
     *      "updated_at": "29-07-2024",
     *      "assigned": {
     *        "projects": 0,
     *        "tasks": 0
     *      }
     *    }
     *  ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Client not found",
     *   "total": 0,
     *   "clients": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Clients not found",
     *   "total": 0,
     *   "clients": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Project not found",
     *   "total": 0,
     *   "clients": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Task not found",
     *   "total": 0,
     *   "clients": []
     * }
     */

    public function apiList(Request $request, $id = '')
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $status = $request->input('status', '');
        $internal_purpose = $request->input('internal_purpose', '');
        $type = $request->input('type', '');
        $type_id = $request->input('type_id', '');
        $limit = $request->input('limit', 10); // default limit
        $offset = $request->input('offset', 0); // default offset

        if ($id) {
            $client = Client::find($id);
            if (!$client) {
                return formatApiResponse(false, 'Client not found', ['total' => 0, 'data' => []]);
            } else {
                return formatApiResponse(
                    false,
                    'Client retrieved successfully',
                    [
                        'total' => 1,
                        'data' => [formatClient($client)],
                    ]
                );
            }
        } else {
            $workspace = Workspace::find(getWorkspaceId());

            if ($type && $type_id) {
                if ($type == 'project') {
                    $project = Project::find($type_id);
                    if ($project) {
                        $clientsQuery = $project->clients();
                    } else {
                        return formatApiResponse(true, 'Project not found', ['total' => 0, 'data' => []]);
                    }
                } elseif ($type == 'task') {
                    $task = Task::find($type_id);
                    if ($task) {
                        $clientsQuery = $task->project->clients();
                    } else {
                        return formatApiResponse(true, 'Task not found', ['total' => 0, 'data' => []]);
                    }
                } else {
                    $clientsQuery = $workspace->clients();
                }
            } else {
                $clientsQuery = $workspace->clients();
            }

            $clientsQuery->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('company', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('clients.id', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $search . '%']);
                });
            });

            if ($status != '') {
                $clientsQuery->where('status', $status);
            }

            if ($internal_purpose != '') {
                $clientsQuery->where('internal_purpose', $internal_purpose);
            }

            $total = $clientsQuery->count(); // get total count before applying offset and limit

            $clients = $clientsQuery->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get();

            if ($clients->isEmpty()) {
                return formatApiResponse(false, 'Clients not found', ['total' => 0, 'data' => []]);
            }

            $data = $clients->map(function ($client) {
                return formatClient($client);
            });

            return formatApiResponse(
                false,
                'Clients retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data,
                ]
            );
        }
    }



    public function verify_email(EmailVerificationRequest $request)
    {
        $request->fulfill();
        return redirect('/home')->with('message', 'Email verified successfully.');
    }
}
