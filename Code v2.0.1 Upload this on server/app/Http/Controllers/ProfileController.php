<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Profile;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Rules\UniqueEmailPassword;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show()
    {
        $roles = Role::all();
        return view('users.account', ['user' => getAuthenticatedUser(), 'roles' => $roles]);
    }

    /**
     * Retrieve the authenticated user's profile.
     *
     * This endpoint returns the profile information of the currently authenticated user. The user must be authenticated to access their profile details.
     *
     * @group Profile Management
     *
     * @authenticated
     *
     * @response {
     * "error": false,
     * "message": "Profile details retrieved successfully",
     * "data": {
     * "id": 7,
     * "first_name": "Madhavan",
     * "last_name": "Vaidya",
     * "role": "admin",
     * "email": "admin@gmail.com",
     * "phone": "9099882203",
     * "dob": "17-06-2024",
     * "doj": "03-10-2022",
     * "address": "Devonshire",
     * "city": "Windsor",
     * "state": "ON",
     * "country": "Canada",
     * "zip": "123654",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png",
     * "status": 1,
     * "created_at": "03-01-2023 10:37:20",
     * "updated_at": "13-08-2024 14:16:45",
     * "assigned": {
     * "projects": 11,
     * "tasks": 9
     * },
     * "is_admin_or_leave_editor": true,
     * "is_admin_or_has_all_data_access": true
     * }
     * }
     *
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $data = (getGuardName() == 'client') ? formatClient($user) : formatUser($user);

        $data['is_admin_or_leave_editor'] = is_admin_or_leave_editor();
        $data['is_admin_or_has_all_data_access'] = isAdminOrHasAllDataAccess();

        return formatApiResponse(
            false,
            'Profile details retrieved successfully',
            [
                'data' => $data
            ]
        );
    }

    /**
     * Update the profile details of a logged-in user.
     *
     * This endpoint allows the authenticated user to update their profile details such as name, email, address, and other relevant information.
     *
     * @authenticated
     *
     * @group Profile Management
     *
     * @urlParam id int required The ID of the user whose profile is being updated.
     *
     * @bodyParam first_name string required The user's first name. Example: Madhavan
     * @bodyParam last_name string required The user's last name. Example: Vaidya
     * @bodyParam email string required The user's email address. Can only be edited if `is_admin_or_has_all_data_access` is true for the logged-in user. Example: admin@gmail.com
     * @bodyParam role integer The ID of the role for the user. If the authenticated user is an admin, the provided role will be used. If the authenticated user is not an admin, the current role of the user will be used, regardless of the input. Example: 1
     * @bodyParam phone string The user's phone number. Example: 9099882203
     * @bodyParam country_code string The country code for the phone number. Example: +91
     * @bodyParam country_iso_code string nullable The ISO code for the phone number. Example: in
     * @bodyParam dob date The user's date of birth. Example: 17-06-2024
     * @bodyParam doj date The user's date of joining. Example: 03-10-2022
     * @bodyParam address string The user's address. Example: Devonshire
     * @bodyParam city string The user's city. Example: Windsor
     * @bodyParam state string The user's state. Example: ON
     * @bodyParam country string The user's country. Example: Canada
     * @bodyParam zip string The user's zip code. Example: 123654
     * @bodyParam password string The user's new password (if changing). Example: 12345678
     * @bodyParam password_confirmation string The password confirmation (if changing password). Example: 12345678
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Profile details updated successfully.",
     *   "data": {
     *     "id": 7,
     *     "first_name": "Madhavan",
     *     "last_name": "Vaidya",
     *     "role": "admin",
     *     "email": "admin@gmail.com",
     *     "phone": "9099882203",
     *     "dob": "17-06-2024",
     *     "doj": "03-10-2022",
     *     "address": "Devonshire",
     *     "city": "Windsor",
     *     "state": "ON",
     *     "country": "Canada",
     *     "zip": "123654",
     *     "photo": "https://test-taskify.infinitietech.com/storage/photos/atEj9NKCeAJhM5VqBN69mFKHntHbZkPUl2Sa22RA.webp",
     *     "status": 1,
     *     "created_at": "03-01-2023 10:37:20",
     *     "updated_at": "13-08-2024 18:58:34",
     *     "assigned": {
     *       "projects": 11,
     *       "tasks": 21
     *     }
     *   }
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Validation error: The email has already been taken."
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "User not found",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Profile details couldn\'t be updated."
     * }
     *
     */


    public function update(Request $request, $id)
    {
        $isApi = request()->get('isApi', false);
        if (getAuthenticatedUser()->getRoleNames()->first() != 'admin') {
            $role = getAuthenticatedUser()->roles->pluck('id')[0];
            $request->merge(['role' => $role]);
        }


        $request->merge([
            'phone' => str_replace(' ', '', $request->input('phone')),
            'country_code' => str_replace(' ', '', $request->input('country_code')),
        ]);
        $isUser = getGuardName() == 'web';
        $rules = [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'phone' => [
                'nullable',
                'required_with:country_code',
                Rule::unique($isUser ? 'users' : 'clients')->ignore($id)->where(function ($query) use ($request) {
                    return $query->where('country_code', $request->country_code);
                }),
            ],
            'country_code' => 'nullable|required_with:phone',
            'country_iso_code' => 'nullable',
            'role' => 'required',
            'address' => 'nullable',
            'city' => 'nullable',
            'state' => 'nullable',
            'country' => 'nullable',
            'zip' => 'nullable',
            'password' => 'nullable|min:6',
            'password_confirmation' => 'required_with:password|same:password',
        ];
        if (isAdminOrHasAllDataAccess()) {
            $rules['email'] = [
                'required',
                'email',
                Rule::unique($isUser ? 'users' : 'clients', 'email')->ignore($id),
            ];
        }
        try {
            $formFields = $request->validate($rules, [
                'phone.required_with' => 'The phone number must be provided when the country code is present.',
                'country_code.required_with' => 'The country code must be provided when the phone number is present.',
                'phone.unique' => 'The combination of this phone number and country code is already in use.',
            ]);

            if (request()->filled('password')) {
                $uniqueEmailPasswordRule = new UniqueEmailPassword($isUser ? 'user' : 'client');
                if (!$uniqueEmailPasswordRule->passes('password', $request->input('password'))) {
                    return formatApiValidationError($isApi, ['email' => [$uniqueEmailPasswordRule->message()]]);
                }
            }
            $user = $isUser ? User::find($id) : Client::find($id);
            if (!$user) {
                return formatApiResponse(
                    true,
                    'User not found',
                    []
                );
            }
            if (isset($formFields['password']) && !empty($formFields['password'])) {
                $formFields['password'] = bcrypt($formFields['password']);
            } else {
                unset($formFields['password']);
            }
            $user->update($formFields);

            // Convert role ID to role name before syncing
            $roleInput = $request->input('role');

            if (is_numeric($roleInput)) {
                // If role is numeric (ID), find the role name
                $role = \Spatie\Permission\Models\Role::find($roleInput);
                if ($role) {
                    $user->syncRoles($role->name); // Use role name instead of ID
                } else {
                    return formatApiResponse(
                        true,
                        'Invalid role specified',
                        []
                    );
                }
            } else {
                // If role is already a name, use it directly
                $user->syncRoles($roleInput);
            }

            // Session::flash('message', 'Profile details updated successfully.');
            return formatApiResponse(
                false,
                'Profile details updated successfully.',
                [
                    'data' => $isUser ? formatUser($user) : formatClient($user),
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'Profile details couldn\'t be updated.'
            ], 500);
        }
    }

    /**
     * Update the profile picture of a logged-in user or a specified user/client.
     *
     * This endpoint allows the authenticated user to update their profile picture.
     * If both `id` and `type` are provided, the profile picture for the specified user or client will be updated.
     * If not, the profile picture of the logged-in user will be updated.
     *
     * @authenticated
     *
     * @group Profile Management
     *
     * @bodyParam id int required The ID of the user or client whose profile picture is being updated. Required if `type` is provided. Example:1
     * @bodyParam type string required The type of the entity whose profile picture is being updated. Must be either 'user' or 'client'. Example:user
     * @bodyParam upload file required The file of the new profile picture to be uploaded.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Profile picture updated successfully.",
     *   "data": {
     *     "id": 7,
     *     "first_name": "Madhavan",
     *     "last_name": "Vaidya",
     *     "role": "admin",
     *     "email": "admin@gmail.com",
     *     "phone": "9099882203",
     *     "dob": "17-06-2024",
     *     "doj": "03-10-2022",
     *     "address": "Devonshire",
     *     "city": "Windsor",
     *     "state": "ON",
     *     "country": "Canada",
     *     "zip": "123654",
     *     "photo": "https://test-taskify.infinitietech.com/storage/photos/atEj9NKCeAJhM5VqBN69mFKHntHbZkPUl2Sa22RA.webp",
     *     "status": 1,
     *     "created_at": "03-01-2023 10:37:20",
     *     "updated_at": "13-08-2024 18:58:34",
     *     "assigned": {
     *       "projects": 11,
     *       "tasks": 21
     *     }
     *   }
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "No profile picture selected!"
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "User not found",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Profile picture couldn't be updated."
     * }
     */


    public function update_photo(Request $request)
    {
        $isApi = request()->get('isApi', false);
        // Define validation rules
        $rules = [
            'type' => 'required_with:id|in:user,client', // Ensure type is either user or client and required with id
            'id' => 'required_with:type|nullable', // ID is required if type is present
            'upload' => 'required|file|image', // Ensure a file is uploaded
        ];

        // Add conditional rules for 'id' based on the value of 'type'
        if ($request->input('type') === 'user') {
            $rules['id'] .= '|exists:users,id'; // Check existence in users table
        } elseif ($request->input('type') === 'client') {
            $rules['id'] .= '|exists:clients,id'; // Check existence in clients table
        }
        $messages = [
            'upload.image' => 'The file must be a valid image (jpg, jpeg, png, gif, bmp, webp).'
        ];
        try {
            $request->validate($rules, $messages);
            // Check if both id and type are provided
            $id = $request->input('id');
            $type = $request->input('type');

            // Validate ID based on type
            if ($id && $type) {
                if ($type === 'user') {
                    $isUser = true;
                    $user = User::find($id);
                } elseif ($type === 'client') {
                    $isUser = false;
                    $user = Client::find($id);
                } else {
                    return response()->json(['error' => true, 'message' => 'Invalid user type.'], 400);
                }
            } else {
                // Fallback to current authenticated user
                $isUser = getGuardName() == 'web';
                $id = getAuthenticatedUser()->id;
                $user = $isUser ? User::find($id) : Client::find($id);
            }

            if (!$user) {
                return formatApiResponse(true, 'User not found', []);
            }

            // Delete old photo if it exists
            if ($user->photo != 'photos/no-image.jpg' && $user->photo !== null) {
                Storage::disk('public')->delete($user->photo);
            }

            // Store new photo
            $formFields['photo'] = $request->file('upload')->store('photos', 'public');
            $user->update($formFields);

            return formatApiResponse(
                false,
                'Profile picture updated successfully.',
                [
                    'data' => $isUser ? formatUser($user) : formatClient($user),
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'Profile picture couldn\'t be updated.'
            ], 500);
        }
    }

    /**
     * Delete account of a logged-in user.
     *
     * This endpoint allows the authenticated user to delete their account.
     *
     * @authenticated
     *
     * @group Profile Management
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Account deleted successfully."
     *   "data": []
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "User not found",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Account couldn't be deleted."
     * }
     *
     */
    public function destroy()
    {
        try {
            $user = getAuthenticatedUser();
            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not found',
                    'data' => []
                ], 404);
            }
            $isUser = getGuardName() == 'web';
            $mainAdminId = getMainAdminId();
            if ($isUser && $user->id == $mainAdminId) {
                return response()->json([
                    'error' => true,
                    'message' => 'The main admin account cannot be deleted.'
                ]);
            }
            $modelClass = $isUser ? User::class : Client::class;
            // Call the deletion service
            $response = DeletionService::delete($modelClass, $user->id, 'Account');
            $responseData = json_decode($response->getContent(), true);
            if ($responseData['error']) {
                // Handle error response
                return response()->json($responseData);
            }
            // Delete associated todos
            $user->todos()->delete();
            return response()->json([
                'error' => false,
                'message' => 'Account deleted successfully.',
                'data' => []
            ]);
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'Account couldn\'t be deleted.'
            ], 500);
        }
    }
}
