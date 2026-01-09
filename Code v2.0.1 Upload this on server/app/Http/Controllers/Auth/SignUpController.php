<?php

namespace App\Http\Controllers\Auth;

use App\Models\Client;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\VerifyEmail;
use App\Models\Template;
use App\Notifications\AccountCreation;
use Illuminate\Support\Facades\Session;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;
use Illuminate\Validation\ValidationException;
use App\Rules\UniqueEmailPassword;
use Illuminate\Support\Facades\Validator;

class SignUpController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    public function index()
    {
        $roles = Role::where('guard_name', 'web')->where('name', '!=', 'admin')->get();
        return view('auth.signup', ['roles' => $roles]);
    }

    /**
     * Register a new user.
     *
     * This endpoint allows a new user to sign up by providing necessary details.
     *
     * @group User Authentication
     * @bodyParam type string required The type of account ('member' for team member, 'client' for client). Example: member
     * @bodyParam first_name string required The first name of the user. Example: John
     * @bodyParam last_name string required The last name of the user. Example: Doe
     * @bodyParam email string required The email address of the user or client. Example: john.doe@example.com
     * @bodyParam password string required The password for the account. Example: password123
     * @bodyParam password_confirmation string required The confirmation of the password. Must match 'password'. Example: password123
     * @bodyParam company string nullable The company name. Example: Acme Inc.
     * @bodyParam fcm_token string nullable The optional FCM token for push notifications. Example: cXJ1AqT6B...
     *
     * @response 200 {
     * "error": false,
     * "message": "Account created successfully.",
     * "data": {
     * "id": 225,
     * "first_name": "Test",
     * "last_name": "User",
     * "role": "admin",
     * "email": "test.user@example.com",
     * "phone": null,
     * "dob": null,
     * "doj": null,
     * "address": null,
     * "city": null,
     * "state": null,
     * "country": null,
     * "zip": null,
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg",
     * "status": 0,
     * "created_at": "13-08-2024 14:59:38",
     * "updated_at": "13-08-2024 14:59:38"
     * "assigned": {
     * "projects": 0,
     * "tasks": 0
     * }
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "email": ["The email field is required.", "The email has already been taken."],
     *     "password": ["The password must be at least 6 characters."],
     *     "role": ["The role field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Account couldn't be created, please contact the admin for assistance."
     * }
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  bool  $isApi
     * @return \Illuminate\Http\Response
     */

    public function create_account(Request $request)
    {
        ini_set('max_execution_time', 300);
        $isApi = request()->get('isApi', false);
        $isTeamMember = $request->input('type') === 'member';
        $rules = [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'password' => ['required', 'min:6'],
            'password_confirmation' => ['required', 'same:password'],
            'company' => 'nullable',

        ];

        $settings = get_settings('general_settings');

        if (!empty($settings['recaptcha_enabled']) && $settings['recaptcha_enabled'] && !$isApi) {
            $rules['g-recaptcha-response'] = 'required|captcha';
        }

        if ($isTeamMember) {
            // $rules['role'] = 'required';
            $rules['email'] = ['required', 'email', 'unique:users,email'];
        } else {
            $rules['email'] = ['required', 'email', 'unique:clients,email'];
        }
        try {
            $formFields = $request->validate($rules);

            $uniqueEmailPasswordRule = new UniqueEmailPassword($isTeamMember ? 'user' : 'client');
            if (!$uniqueEmailPasswordRule->passes('password', $request->input('password'))) {
                return formatApiValidationError($isApi, ['email' => [$uniqueEmailPasswordRule->message()]]);
            }
            $primaryWorkspaceId = hasPrimaryWorkspace();
            if (!$primaryWorkspaceId) {
                return response()->json(['error' => true, 'message' => 'Primary workspace is not set, which is required for signup. Please contact the admin for assistance.']);
            } else {
                $workspace = Workspace::find($primaryWorkspaceId);
            }

            $password = $request->input('password');
            $formFields['password'] = bcrypt($password);
            $formFields['photo'] = 'photos/no-image.jpg';

            $formFields['status'] = 0;
            $user = $isTeamMember ? User::create($formFields) : Client::create($formFields);
            if ($request->filled('fcm_token')) {
                storeFcmToken($user, $request->input('fcm_token'));
            }
            if ($isTeamMember) {
                // $user->assignRole($request->input('role'));
            } else {
                $role_id = Role::where('guard_name', 'client')->first()->id;
                $user->assignRole($role_id);
            }
            try {
                $user->notify(new VerifyEmail($user));
                $isTeamMember ? $workspace->users()->attach($user->id) : $workspace->clients()->attach($user->id);
                if (!$isTeamMember) {
                    $user->update(['email_verification_mail_sent' => 1]);
                }

                if (isEmailConfigured()) {
                    $account_creation_template = Template::where('type', 'email')
                        ->where('name', 'account_creation')
                        ->first();
                    if (!$account_creation_template || ($account_creation_template->status !== 0)) {
                        $user->notify(new AccountCreation($user, $password));
                        $user->update(['acct_create_mail_sent' => 1]);
                    }
                }
                // Session::flash('message', 'Account created successfully.');
                $user->password = $request->input('password');
                return formatApiResponse(
                    false,
                    'Account created successfully.',
                    ['data' => $isTeamMember ? formatUser($user, true) : formatClient($user, true)]
                );
            } catch (TransportExceptionInterface $e) {

                $user = $isTeamMember ? User::findOrFail($user->id) : Client::findOrFail($user->id);
                $user->delete();
                return response()->json(['error' => true, 'message' => 'Account couldn\'t be created. An error occurred while sending the verification email. Please contact the admin for assistance.']);
            } catch (Throwable $e) {
                // dd($e->getMessage());
                // Catch any other throwable, including non-Exception errors

                $user = $isTeamMember ? User::findOrFail($user->id) : Client::findOrFail($user->id);
                $user->delete();
                return response()->json(['error' => true, 'message' => 'Account couldn\'t be created, please contact the admin for assistance.']);
            }
        } catch (ValidationException $e) {
            // Check if the email has already been taken
            if ($isApi && isset($e->errors()['email']) && in_array('The email has already been taken.', $e->errors()['email'])) {
                return response()->json(['error' => true, 'message' => 'The email has already been taken.'], 200);
            }
            return formatApiValidationError($isApi, $e->errors());
        }
    }
}
