<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class WorkspacesController extends Controller
{
    protected $workspace;
    protected $user;
    public function __construct()
    {

        $this->middleware(function ($request, $next) {
            // fetch session and use it in entire class with constructor
            $this->workspace = Workspace::find(getWorkspaceId());
            $this->user = getAuthenticatedUser();
            return $next($request);
        });
    }
    public function index()
    {
        $workspaces = Workspace::all();
        return view('workspaces.workspaces', compact('workspaces'));
    }

    /**
     * Create a new workspace.
     *
     * This endpoint creates a new workspace with the provided details. The user must be authenticated to perform this action. The request validates various fields, including title and participants.
     *
     * @authenticated
     *
     * @group Workspace Management
     *
     * @bodyParam title string required The title of the workspace. Example: Design Team
     * @bodyParam user_ids array|null optional Array of user IDs to be associated with the workspace. Example: [1, 2, 3]
     * @bodyParam client_ids array|null optional Array of client IDs to be associated with the workspace. Example: [5, 6]
     * @bodyParam primaryWorkspace string optional Indicates if this workspace should be set as primary. Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user, else it will be considered 0 by default. The value should be 'on' to set as primary. Example: on
     *
     * @response 200 {
     * "error": false,
     * "message": "Workspace created successfully.",
     * "id": 438,
     * "data": {
     *   "id": 438,
     *   "title": "Design Team",
     *   "is_primary": true,
     *   "users": [
     *     {
     *       "id": 7,
     *       "first_name": "Madhavan",
     *       "last_name": "Vaidya",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     *     }
     *   ],
     *   "clients": [
     *     {
     *       "id": 103,
     *       "first_name": "Test",
     *       "last_name": "Test",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     *     }
     *   ],
     *   "created_at": "07-08-2024 14:38:51",
     *   "updated_at": "07-08-2024 14:38:51"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "title": [
     *       "The title field is required."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the workspace."
     * }
     */
    public function store(Request $request)
    {
        $isApi = request()->get('isApi', false);
        // Define validation rules
        $rules = [
            'title' => 'required|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id', // Validate that each user_id exists in the users table
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'integer|exists:clients,id', // Validate that each client_id exists in the clients table
        ];

        // Validate the request
        try {
            $formFields = $request->validate($rules);

            // Add the authenticated user as a participant
            $formFields['user_id'] = $this->user->id;

            // Get user_ids and client_ids from the request
            $userIds = $request->input('user_ids') ?? [];
            $clientIds = $request->input('client_ids') ?? [];

            // Set creator as a participant automatically if !isAdminOrHasAllDataAccess
            if (!isAdminOrHasAllDataAccess()) {
                if (getGuardName() == 'client' && !in_array($this->user->id, $clientIds)) {
                    array_splice($clientIds, 0, 0, $this->user->id);
                } else if (getGuardName() == 'web' && !in_array($this->user->id, $userIds)) {
                    array_splice($userIds, 0, 0, $this->user->id);
                }
            }

            $primaryWorkspace = isAdminOrHasAllDataAccess() &&  $request->input('primaryWorkspace') == 'on' ? 1 : 0;
            $formFields['is_primary'] = $primaryWorkspace;

            // Create the new workspace
            $new_workspace = Workspace::create($formFields);
            $workspace_id = $new_workspace->id;
            if ($primaryWorkspace) {
                // Set all other workspaces to non-primary
                Workspace::where('id', '!=', $workspace_id)->update(['is_primary' => 0]);
            }
            $workspace = Workspace::find($workspace_id);
            // Attach users and clients to the workspace
            $workspace->users()->attach($userIds);
            $workspace->clients()->attach($clientIds);

            // Prepare notification data
            $notification_data = [
                'type' => 'workspace',
                'type_id' => $workspace_id,
                'type_title' => $workspace->title,
                'action' => 'assigned'
            ];

            // Combine user and client IDs for notification recipients
            $recipients = array_merge(
                array_map(function ($userId) {
                    return 'u_' . $userId;
                }, $userIds),
                array_map(function ($clientId) {
                    return 'c_' . $clientId;
                }, $clientIds)
            );

            // Process notifications
            processNotifications($notification_data, $recipients);

            // Return JSON response with workspace ID
            return formatApiResponse(
                false,
                'Workspace created successfully.',
                [
                    'id' => $workspace_id,
                    'data' => formatWorkspace($workspace)
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the workspace.'
            ], 500);
        }
    }


    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $user_ids = request('user_ids', []);
        $client_ids = request('client_ids', []);

        $workspaces = isAdminOrHasAllDataAccess() ? $this->workspace : $this->user->workspaces();

        if (!empty($user_ids)) {
            $workspaces = $workspaces->whereHas('users', function ($query) use ($user_ids) {
                $query->whereIn('users.id', $user_ids);
            });
        }

        if (!empty($client_ids)) {
            $workspaces = $workspaces->whereHas('clients', function ($query) use ($client_ids) {
                $query->whereIn('clients.id', $client_ids);
            });
        }
        $workspaces = $workspaces->when($search, function ($query) use ($search) {
            return $query->where('title', 'like', '%' . $search . '%')
                ->orWhere('id', 'like', '%' . $search . '%');
        });
        $totalworkspaces = $workspaces->count();

        $canCreate = checkPermission('create_workspaces');
        $canEdit = checkPermission('edit_workspaces');
        $canDelete = checkPermission('delete_workspaces');

        $workspaces = $workspaces->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($workspace) use ($canEdit, $canDelete, $canCreate) {

                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-workspace" data-id="' . $workspace->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $workspace->id . '" data-type="workspaces" data-reload="true">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                if ($canCreate) {
                    $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $workspace->id . '" data-title="' . $workspace->title . '" data-type="workspaces" data-reload="true" title="' . get_label('duplicate', 'Duplicate') . '">' .
                        '<i class="bx bx-copy text-warning mx-2"></i>' .
                        '</a>';
                }

                $actions = $actions ?: '-';

                $userHtml = '';
                if (!empty($workspace->users) && count($workspace->users) > 0) {
                    $userHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($workspace->users as $user) {
                        $userHtml .= "<li class='avatar avatar-sm pull-up'><a href='" . url("/users/profile/{$user->id}") . "' title='{$user->first_name} {$user->last_name}'><img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    if ($canEdit) {
                        $userHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-workspace update-users-clients" data-id="' . $workspace->id . '"><span class="bx bx-edit"></span></a></li>';
                    }
                    $userHtml .= '</ul>';
                } else {
                    $userHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                    if ($canEdit) {
                        $userHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-workspace update-users-clients" data-id="' . $workspace->id . '">' .
                            '<span class="bx bx-edit"></span>' .
                            '</a>';
                    }
                }

                $clientHtml = '';
                if (!empty($workspace->clients) && count($workspace->clients) > 0) {
                    $clientHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($workspace->clients as $client) {
                        $clientHtml .= "<li class='avatar avatar-sm pull-up'><a href='" . url("/clients/profile/{$client->id}") . "' title='{$client->first_name} {$client->last_name}'><img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    if ($canEdit) {
                        $clientHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-workspace update-users-clients" data-id="' . $workspace->id . '"><span class="bx bx-edit"></span></a></li>';
                    }
                    $clientHtml .= '</ul>';
                } else {
                    $clientHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                    if ($canEdit) {
                        $clientHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-workspace update-users-clients" data-id="' . $workspace->id . '">' .
                            '<span class="bx bx-edit"></span>' .
                            '</a>';
                    }
                }
                return [
                    'id' => $workspace->id,
                    'title' => '<a href="workspaces/switch/' . $workspace->id . '">' . $workspace->title . '</a>' .
                        ($workspace->is_primary ? ' <span class="badge bg-success">' . get_label('primary', 'Primary') . '</span>' : ''),
                    'is_default' => '
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   id="defaultWorkspaceSwitch_' . $workspace->id . '"
                                   ' . ($this->user->default_workspace_id == $workspace->id ? 'checked' : '') . '
                                   onchange="setDefaultWorkspace(' . $workspace->id . ', this.checked)">
                            <label class="form-check-label" for="defaultWorkspaceSwitch_' . $workspace->id . '">
                            </label>
                        </div>',
                    'users' => $userHtml,
                    'clients' => $clientHtml,
                    'created_at' => format_date($workspace->created_at, true),
                    'updated_at' => format_date($workspace->updated_at, true),
                    'actions' => $actions
                ];
            });

        return response()->json([
            "rows" => $workspaces->items(),
            "total" => $totalworkspaces,
        ]);
    }

    /**
     * List or search workspaces.
     *
     * This endpoint retrieves a list of workspaces based on various filters. The user must be authenticated to perform this action. The request allows filtering by user, client, and other parameters.
     *
     * @authenticated
     *
     * @group Workspace Management
     *
     * @urlParam id int optional The ID of the workspace to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter workspaces by title or id. Example: Workspace
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, title, created_at, and updated_at. Example: title
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam user_id int optional The user ID to filter workspaces by. Example: 1
     * @queryParam client_id int optional The client ID to filter workspaces by. Example: 5
     * @queryParam limit int optional The number of workspaces per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Workspaces retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 351,
     *       "title": "Workspace Title",
     *       "is_primary": 0,
     *       "users": [
     *         {
     *           "id": 7,
     *           "first_name": "Madhavan",
     *           "last_name": "Vaidya",
     *           "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     *         }
     *       ],
     *       "clients": [
     *         {
     *           "id": 12,
     *           "first_name": "Client",
     *           "last_name": "Name",
     *           "photo": "https://test-taskify.infinitietech.com/storage/photos/client-photo.png"
     *         }
     *       ],
     *       "created_at": "20-07-2024 17:50:09",
     *       "updated_at": "21-07-2024 19:08:16"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Workspace not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Workspaces not found",
     *   "total": 0,
     *   "data": []
     * }
     */


    public function apiList(Request $request, $id = '')
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $status = $request->input('status', '');
        $user_id = $request->input('user_id', '');
        $client_id = $request->input('client_id', '');
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $where = [];
        if ($status != '') {
            $where['status_id'] = $status;
        }

        $workspacesQuery = isAdminOrHasAllDataAccess() ? $this->workspace->newQuery() : $this->user->workspaces()->newQuery();

        // Handle user_id filtering
        if ($user_id) {
            $user = User::find($user_id);
            if (!$user) {
                return formatApiResponse(
                    false,
                    'User not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $workspacesQuery = $user->workspaces()->newQuery();
        }

        // Handle client_id filtering
        if ($client_id) {
            $client = Client::find($client_id);
            if (!$client) {
                return formatApiResponse(
                    false,
                    'Client not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $workspacesQuery = $client->workspaces()->newQuery();
        }

        // Apply search and other filters
        $workspacesQuery->when($search, function ($query) use ($search) {
            $query->where('title', 'like', '%' . $search . '%')
                ->orWhere('workspaces.id', 'like', '%' . $search . '%');
        });

        $workspacesQuery->where($where);

        if ($id) {
            $workspace = $workspacesQuery->find($id);
            if (!$workspace) {
                return formatApiResponse(
                    false,
                    'Workspace not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            return formatApiResponse(
                false,
                'Workspace retrieved successfully',
                [
                    'total' => 1,
                    'data' => [formatWorkspace($workspace)]
                ]
            );
        } else {
            $total = $workspacesQuery->count(); // Get total count before applying offset and limit

            $workspaces = $workspacesQuery->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get();

            if ($workspaces->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Workspaces not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }

            $data = $workspaces->map(function ($workspace) {
                return formatWorkspace($workspace);
            });

            return formatApiResponse(
                false,
                'Workspaces retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }

    public function get($id)
    {
        $workspace = Workspace::with('users', 'clients')->findOrFail($id);
        return response()->json(['error' => false, 'workspace' => $workspace]);
    }

    /**
     * Update an existing workspace.
     *
     * This endpoint updates the details of an existing workspace. The user must be authenticated to perform this action. The request validates various fields, including title and participants.
     *
     * @authenticated
     *
     * @group Workspace Management
     *
     * @bodyParam id integer required The ID of the workspace to update. Example: 438
     * @bodyParam title string required The new title of the workspace. Example: Design Team
     * @bodyParam user_ids array|null optional Array of user IDs to be associated with the workspace. Example: [1, 2, 3]
     * @bodyParam client_ids array|null optional Array of client IDs to be associated with the workspace. Example: [5, 6]
     * @bodyParam primaryWorkspace string optional Indicates if this workspace should be set as primary. Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user, else current value will be considered by default. The value should be 'on' to set as primary. Example: on
     *
     * @response 200 {
     * "error": false,
     * "message": "Workspace updated successfully.",
     * "id": 438,
     * "data": {
     *   "id": 438,
     *   "title": "Design Team",
     *   "is_primary": true,
     *   "users": [
     *     {
     *       "id": 7,
     *       "first_name": "Madhavan",
     *       "last_name": "Vaidya",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     *     }
     *   ],
     *   "clients": [
     *     {
     *       "id": 103,
     *       "first_name": "Test",
     *       "last_name": "Test",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     *     }
     *   ],
     *   "created_at": "07-08-2024 14:38:51",
     *   "updated_at": "07-08-2024 14:38:51"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "title": [
     *       "The title field is required."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the workspace."
     * }
     */
    public function update(Request $request)
    {
        $isApi = request()->get('isApi', false);
        // Define validation rules
        $rules = [
            'id' => 'required|exists:workspaces,id',
            'title' => 'required|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'integer|exists:clients,id',
        ];

        try {
            // Validate the request
            $formFields = $request->validate($rules);
            $id = $request->input('id');
            $workspace = Workspace::findOrFail($id);

            $userIds = $request->input('user_ids') ?? [];
            $clientIds = $request->input('client_ids') ?? [];

            // Get current list of users and clients associated with the workspace
            $existingUserIds = $workspace->users->pluck('id')->toArray();
            $existingClientIds = $workspace->clients->pluck('id')->toArray();

            if (isAdminOrHasAllDataAccess()) {
                if ($request->has('primaryWorkspace')) {
                    $primaryWorkspace = $request->boolean('primaryWorkspace', false) ? 1 : 0;
                    $formFields['is_primary'] = $primaryWorkspace;
                } else {
                    $primaryWorkspace = 0;
                }
            } else {
                $primaryWorkspace = $workspace->is_primary;
            }

            // Update workspace
            $workspace->update($formFields);

            if ($primaryWorkspace) {
                // Set all other workspaces to non-primary
                Workspace::where('id', '!=', $workspace->id)->update(['is_primary' => 0]);
            }

            // Sync users and clients
            $workspace->users()->sync($userIds);
            $workspace->clients()->sync($clientIds);

            // Exclude old users and clients from receiving notifications
            $userIds = array_diff($userIds, $existingUserIds);
            $clientIds = array_diff($clientIds, $existingClientIds);

            // Prepare notification data
            $notification_data = [
                'type' => 'workspace',
                'type_id' => $id,
                'type_title' => $workspace->title,
                'action' => 'assigned'
            ];

            // Combine user and client IDs for notification recipients
            $recipients = array_merge(
                array_map(function ($userId) {
                    return 'u_' . $userId;
                }, $userIds),
                array_map(function ($clientId) {
                    return 'c_' . $clientId;
                }, $clientIds)
            );

            // Process notifications
            processNotifications($notification_data, $recipients);

            // Fetch the latest workspace data
            $workspace->load('users', 'clients'); // Ensure users and clients relationships are loaded

            // Return JSON response with updated workspace data
            return formatApiResponse(
                false,
                'Workspace updated successfully.',
                [
                    'id' => $id,
                    'data' => formatWorkspace($workspace)
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the workspace.'
            ], 500);
        }
    }

    /**
     * Set or remove a default workspace for the authenticated user.
     *
     * This endpoint updates whether a workspace is set as the default workspace for the user. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Workspace Management
     *
     * @urlParam id int required The ID of the workspace to update.
     * @bodyParam is_default boolean required Indicates whether the workspace should be set as default. Use 1 for setting as default and 0 for removing it as default.
     *
     * @response 200 {
     * "error": false,
     * "message": "Default status updated successfully"
     * "data":[Workspace data here]
     * }
     *
     * @response 404 {
     * "error": true,
     * "message": "Workspace not found",
     * "data": []
     * }
     *
     * @response 500 {
     * "error": true,
     * "message": "Failed to update default workspace"
     * }
     */


    public function setDefaultWorkspace(Request $request, $id)
    {
        $isApi = request()->get('isApi', false);
        try {
            $request->validate([
                'is_default' => 'required|boolean',
            ]);
            $workspace = Workspace::find($id);
            if (!$workspace) {
                return formatApiResponse(
                    true,
                    'Workspace not found',
                    []
                );
            }
            $isDefault = $request->input('is_default') == 1;
            $this->user->default_workspace_id = $isDefault ? $id : null;
            $this->user->save();
            return response()->json([
                'error' => false,
                'message' => 'Default status updated successfully',
                'data' => formatWorkspace($workspace)
            ]);
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the workspace default status.'
            ], 500);
        }
    }

    /**
     * Remove the specified workspace.
     *
     * This endpoint deletes a workspace based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Workspace Management
     *
     * @urlParam id int required The ID of the workspace to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Workspace deleted successfully.",
     *   "id": "60",
     *   "title": "Workspace Title",
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Workspace not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the workspace."
     * }
     */

    public function destroy($id)
    {
        $workspace = Workspace::find($id);
        if ($workspace) {
            if ($this->workspace->id != $id) {
                if ($workspace->is_primary == 0) {
                    $response = DeletionService::delete(Workspace::class, $id, 'Workspace');
                    $responseData = json_decode($response->getContent(), true);
                    if ($responseData['error']) {
                        // Handle error response
                        return response()->json($responseData);
                    }
                    // Check if this workspace is the default workspace for the user
                    if ($this->user->default_workspace_id == $id) {
                        $this->user->default_workspace_id = null;
                        $this->user->save();
                    }
                    $workspace->notificationsForWorkspace()->delete();
                    return $response;
                } else {
                    return response()->json(['error' => true, 'message' => 'Primary workspace cannot be deleted.']);
                }
            } else {
                return response()->json(['error' => true, 'message' => 'Current workspace cannot be deleted. Please switch to a different workspace first.']);
            }
        } else {
            return formatApiResponse(
                true,
                'Workspace not found.',
                []
            );
        }
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:workspaces,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedWorkspaces = [];
        $deletedWorkspaceTitles = [];
        $primaryWorkspaceIds = [];
        $currentWorkspaceIds = [];

        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $workspace = Workspace::find($id);
            if ($workspace) {
                if ($workspace->id == $this->workspace->id) {
                    $currentWorkspaceIds[] = $id;
                } elseif ($workspace->is_primary == 1) {
                    $primaryWorkspaceIds[] = $id;
                } else {
                    $deletedWorkspaces[] = $id;
                    $deletedWorkspaceTitles[] = $workspace->title;
                    $workspace->notificationsForWorkspace()->delete();
                    DeletionService::delete(Workspace::class, $id, 'Workspace');
                }
            }
        }

        if (count($ids) == 1) {
            if (!empty($primaryWorkspaceIds)) {
                return response()->json(['error' => true, 'message' => 'Primary workspace cannot be deleted.']);
            } elseif (!empty($currentWorkspaceIds)) {
                return response()->json(['error' => true, 'message' => 'Current workspace cannot be deleted.']);
            }
        }

        if (!empty($primaryWorkspaceIds) && !empty($currentWorkspaceIds) && count($ids) == 2) {
            return response()->json(['error' => true, 'message' => 'Current and primary workspaces cannot be deleted.']);
        }

        if (in_array($this->user->default_workspace_id, $deletedWorkspaces)) {
            $this->user->default_workspace_id = null;
            $this->user->save();
        }

        $message = 'Workspace(s) deleted successfully.';
        if (!empty($primaryWorkspaceIds) && !empty($currentWorkspaceIds)) {
            $message = 'Workspace(s) deleted successfully except primary and current.';
        } elseif (!empty($primaryWorkspaceIds)) {
            $message = 'Workspace(s) deleted successfully except primary one.';
        } elseif (!empty($currentWorkspaceIds)) {
            $message = 'Workspace(s) deleted successfully except current one.';
        }

        return response()->json(['error' => false, 'message' => $message, 'id' => $deletedWorkspaces, 'titles' => $deletedWorkspaceTitles]);
    }

    /**
     * Switch the current workspace.
     *
     * This endpoint allows the user to switch to a different workspace based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Workspace Management
     *
     * @urlParam id int required The ID of the workspace to switch to. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Workspace changed successfully.",
     *   "data": {
     *     "workspace_id": 1
     *   }
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Workspace not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while switching the workspace."
     * }
     */

    public function switch($id)
    {
        try {
            $workspace = Workspace::find($id);

            if ($workspace) {
                if (isSanctumAuth()) {
                    // Update the authenticated user's current workspace ID
                    // $user = getAuthenticatedUser();
                    // $user->current_workspace_id = $id;
                    // $user->save();
                    return formatApiResponse(
                        false,
                        'Workspace changed successfully.',
                        ['data' => ['workspace_id' => $id]]
                    );
                } else {
                    // Fallback to session-based approach
                    session()->put('workspace_id', $id);
                    return back()->with('message', 'Workspace changed successfully.');
                }
            } else {
                return formatApiResponse(
                    true,
                    'Workspace not found.',
                    ['data' => []]
                );
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while switching the workspace.'
            ], 500);
        }
    }



    /**
     * Remove the authenticated user from the current workspace.
     *
     * This endpoint removes the authenticated user from the workspace they are currently in. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Workspace Management
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Removed from workspace successfully.",
     *   "data": {
     *     "workspace_id": 1
     *   }
     * }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while removing the participant from the workspace."
     * }
     */

    public function remove_participant()
    {
        try {
            $workspace = Workspace::find(getWorkspaceId());
            if (getGuardName() == 'client') {
                $workspace->clients()->detach($this->user->id);
            } else {
                $workspace->users()->detach($this->user->id);
            }

            // Update the workspace ID after removal
            $workspace_id = isset($this->user->workspaces[0]['id']) && !empty($this->user->workspaces[0]['id']) ? $this->user->workspaces[0]['id'] : 0;
            $data = ['workspace_id' => $workspace_id];

            if (isSanctumAuth()) {
                return formatApiResponse(
                    false,
                    'Removed from workspace successfully.',
                    ['data' => ['workspace_id' => $workspace->id]]
                );
            } else {
                session()->put($data);
                Session::flash('message', 'Removed from workspace successfully.');
                return response()->json(['error' => false]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while removing the participant from the workspace.'
            ], 500);
        }
    }


    public function duplicate(Request $request, $id)
    {

        $options = $request->input('options') ?? [];
         // Normalize options to always be an array
        if (!is_array($options)) {
            $options = explode(',', $options); // Split string into an array by commas if necessary
        }
        // Ensure default duplication of users and clients
        $defaultOptions = ['users', 'clients'];
        $options = array_merge($defaultOptions, $options);

        // Validation: Tasks can only be selected if Projects is selected
        if (in_array('tasks', $options) && !in_array('projects', $options)) {
            return response()->json(['error' => true, 'message' => 'Tasks can only be duplicated if Projects is selected.']);
        }
        $allowedOptions = ['projects', 'project_tasks', 'meetings', 'todos', 'notes', 'users', 'clients'];
        $relatedTables = array_intersect($options, $allowedOptions);

        // Use the general duplicateRecord function
        $title = (request()->has('title') && !empty(trim(request()->title))) ? request()->title : '';
        $duplicate = duplicateRecord(Workspace::class, $id, $relatedTables, $title);
        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Workspace duplication failed.']);
        }
        $workspace = Workspace::find($duplicate->id);
        $workspace->update(['is_primary' => 0]);
        return response()->json(['error' => false, 'message' => 'Workspace duplicated successfully.', 'id' => $id]);
    }
}
