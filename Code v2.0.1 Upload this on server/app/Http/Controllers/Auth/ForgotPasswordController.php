<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Notifications\ForgotPassword;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use App\Rules\UniqueEmailPassword;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
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

    use SendsPasswordResetEmails;

    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send Password Reset Link.
     * 
     * This endpoint allows a user or client to request a password reset link by providing their email and account type.
     *
     * @group User Authentication
     * @bodyParam email string required The email address of the user or client. Example: john.doe@example.com
     * @bodyParam account_type string required The type of account ('user' for normal users, 'client' for clients). Example: user     
     *
     * @response 200 {
     * "error": false,
     * "message": "Password reset link emailed successfully."
     * }
     *
     * @response 404 {
     * "error": true,
     * "message": "Account not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Password reset link couldn't be sent, please check email settings."
     * }
     *
     * @param  \Illuminate\Http\Request  $request     
     * @return \Illuminate\Http\Response
     */


    public function sendResetLinkEmail(Request $request)
    {
        $isApi = request()->get('isApi', false);
        try {
            $request->validate([
                'email' => 'required|email',
                'account_type' => 'required|in:user,client',
            ]);
            if (isEmailConfigured()) {
                $provider = $request->input('account_type') . 's'; // 'users' or 'clients'

                try {
                    $exists = $this->checkIfEmailExists($provider, $request->email);

                    if ($exists) {
                        config(['auth.defaults.passwords' => $provider]);

                        $response = $this->broker($provider)->sendResetLink(
                            $request->only('email'),
                            function ($user, $token) use ($provider, $request) {
                                $resetUrl = $this->generateResetUrl($token, $user->email, $request->input('account_type'));
                                $user->notify(new ForgotPassword($user, $resetUrl));
                            }
                        );

                        config(['auth.defaults.passwords' => 'users']);

                        if ($response == Password::RESET_LINK_SENT) {
                            return response()->json(['error' => false, 'message' => __('Password reset link emailed successfully.')]);
                        } else {
                            return response()->json(['error' => true, 'message' => __($response)]);
                        }
                    } else {
                        return response()->json(['error' => true, 'message' => 'Account not found.']);
                    }
                } catch (\Exception $e) {
                    return response()->json(['error' => true, 'message' => 'Password reset link couldn\'t be sent, please check email settings.']);
                }
            } else {
                return response()->json(['error' => true, 'message' => 'Password reset link couldn\'t be sent, please configure email settings.']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        }
    }


    public function showResetPasswordForm($token)
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    /**
     * Reset Password.
     * 
     * This endpoint allows a user or client to reset their password using a valid token.
     *
     * @group User Authentication
     * @bodyParam token string required The password reset token provided via the reset link. Example: abc123
     * @bodyParam email string required The email address of the user or client. Example: john.doe@example.com
     * @bodyParam password string required The new password for the account. Must be at least 6 characters and confirmed. Example: newPassword123
     * @bodyParam password_confirmation string required The confirmation of the new password. Must match 'password'. Example: newPassword123
     * @bodyParam account_type string required The type of account ('user' for normal users, 'client' for clients). Example: user
     *
     * @response 200 {
     * "error": false,
     * "message": "Password has been reset successfully."
     * }
     *
     * @response 404 {
     * "error": true,
     * "message": "Account not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Password reset failed. Please try again later."
     * }
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function ResetPassword(Request $request)
    {
        $isApi = request()->get('isApi', false);
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:6|confirmed',
                'password_confirmation' => 'required',
                'account_type' => 'required|in:user,client',
            ]);

            $provider = $request->input('account_type') . 's'; // 'users' or 'clients'       

            // Check if email exists in the chosen provider's table
            $exists = $this->checkIfEmailExists($provider, $request->email);

            if (!$exists) {
                return response()->json(['error' => true, 'message' => 'Account not found.']);
            }

            $uniqueEmailPasswordRule = new UniqueEmailPassword($request->input('account_type'), 'forgot_password');
            if (!$uniqueEmailPasswordRule->passes('password', $request->input('password'), true)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation errors occurred',
                    'errors' => [
                        'email' => [$uniqueEmailPasswordRule->message()],
                    ]
                ], 422);
            }
            if ($provider == 'users') {
                $status = Password::broker('users')->reset(
                    $request->only('email', 'password', 'password_confirmation', 'token'),
                    function (User $user, string $password) {
                        $user->forceFill([
                            'password' => Hash::make($password)
                        ])->setRememberToken(Str::random(60));

                        $user->save();
                    }
                );
            } else {
                $status = Password::broker('clients')->reset(
                    $request->only('email', 'password', 'password_confirmation', 'token'),
                    function (Client $user, string $password) {
                        $user->forceFill([
                            'password' => Hash::make($password)
                        ])->setRememberToken(Str::random(60));

                        $user->save();
                    }
                );
            }

            if ($status === Password::PASSWORD_RESET) {
                return response()->json(['error' => false, 'message' => __($status)]);
            } else {
                return response()->json(['error' => true, 'message' => __($status)]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        }
    }


    protected function checkIfEmailExists($provider, $email)
    {
        $model = $provider === 'users' ? User::class : Client::class;
        return $model::where('email', $email)->exists();
    }

    // Generate the reset password URL
    protected function generateResetUrl($token, $email, $accountType)
    {
        return url('/reset-password/' . $token) . '?' . http_build_query([
            'email' => $email,
            'account_type' => $accountType
        ]);
    }
}
