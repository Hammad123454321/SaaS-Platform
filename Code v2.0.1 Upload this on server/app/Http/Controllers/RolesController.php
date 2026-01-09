<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RolesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Role::all();
        return view('settings.permission_settings', ['roles' => $roles]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        $projects = Permission::where('name', 'like', '%projects%')->get()->sortBy('name');
        $tasks = Permission::where('name', 'like', '%tasks%')->get()->sortBy('name');
        $users = Permission::where('name', 'like', '%users%')->get()->sortBy('name');
        $clients = Permission::where('name', 'like', '%clients%')->get()->sortBy('name');
        return view('roles.create_role', ['projects' => $projects, 'tasks' => $tasks, 'users' => $users, 'clients' => $clients]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        try {

            $formFields = $request->validate([
                'name' => ['required']
            ]);

            $formFields['guard_name'] = 'web';



            $role = Role::create($formFields);
            $filteredPermissions = array_filter($request->input('permissions'), function ($permission) {
                return $permission != 0;
            });
            $role->permissions()->sync($filteredPermissions);
            Artisan::call('cache:clear');

            Session::flash('message', 'Role created successfully.');
            return response()->json(['error' => false]);
        } catch (RoleAlreadyExists $e) {
            // Handle the exception
            return response()->json(['error' => true, 'message' => 'A role `' . $formFields['name'] . '` already exists.']);
        } catch (\Exception $e) {
            // Handle any other exceptions
            return response()->json(['error' => true, 'message' => 'An error occurred while creating the role.']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {

        $role = Role::findOrFail($id);
        $role_permissions = $role->permissions;
        $guard = $role->guard_name == 'client' ? 'client' : 'web';
        return view('roles.edit_role', ['role' => $role, 'role_permissions' => $role_permissions, 'guard' => $guard, 'user' => getAuthenticatedUser()]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'name' => ['required', 'string', 'max:255']
        ]);

        try {
            $role = Role::findOrFail($id);
            $role->name = $formFields['name'];
            $role->save();

            $filteredPermissions = array_filter($request->input('permissions'), function ($permission) {
                return $permission != 0;
            });
            $role->permissions()->sync($filteredPermissions);

            Artisan::call('cache:clear');

            Session::flash('message', 'Role updated successfully.');
            return response()->json(['error' => false]);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                // Unique constraint violation
                return response()->json(['error' => true, 'message' => 'A role `' . $formFields['name'] . '` already exists.']);
            }
            return response()->json(['error' => true, 'message' => 'An error occurred while updating the role.']);
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'An error occurred while updating the role.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function destroy($id)
    {
        // Find the role first
        $role = Role::find($id);

        // Check if role exists
        if (!$role) {
            return response()->json([
                'error' => true,
                'message' => 'Role not found.'
            ], 404);
        }

        // Define protected/default roles that cannot be deleted
        $protectedRoles = ['admin', 'client', 'member'];

        // Check if the role is protected
        if (in_array(strtolower($role->name), $protectedRoles)) {
            return response()->json([
                'error' => true,
                'message' => 'Cannot delete default system roles (admin, client, member).'
            ], 403);
        }

        // Optional: Check if role has users assigned to it
        if ($role->users()->count() > 0) {
            return response()->json([
                'error' => true,
                'message' => 'Cannot delete role that has users assigned to it. Please reassign users first.'
            ], 400);
        }

        // Proceed with deletion if all checks pass
        $response = DeletionService::delete(Role::class, $id, 'Role');
        return $response;
    }

    public function create_permission()
    {
        // $createProjectsPermission = Permission::findOrCreate('create_tasks', 'client');
        Permission::create(['name' => 'edit_projects', 'guard_name' => 'client']);
    }

    /**
     * List or search roles.
     *
     * This endpoint retrieves a list of roles based on various filters. The request allows filtering by search term and pagination parameters.
     *
     * @group Role/Permission Management
     *
     * @urlParam id int optional The ID of the role to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter roles by id, name or guard_name. Example: Admin
     * @queryParam sort string optional The field to sort by. all fields are sortable. Defaults to "created_at". Example: name
     * @queryParam order string optional The sort order, either "asc" or "desc". Defaults to "desc". Example: asc
     * @queryParam limit int optional The number of roles per page for pagination. Defaults to 10. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Defaults to 0. Example: 0
     *
     * @response 200 {
     *     "error": false,
     *     "message": "Roles retrieved successfully.",
     *     "total": 1,
     *     "data": [
     *         {
     *             "id": 1,
     *             "name": "Admin",
     *             "guard_name": "web",
     *             "created_at": "10-10-2023 17:50:09",
     *             "updated_at": "23-07-2024 19:08:16"
     *         }
     *     ]
     * }
     *
     * @response 200 {
     *     "error": true,
     *     "message": "Role not found.",
     *     "total": 0,
     *     "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Roles not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 500 {
     *     "error": true,
     *     "message": "An error occurred while retrieving the roles."
     * }
     */
    public function apiList(Request $request, $id = null)
    {
        try {
            if ($id) {
                $role = Role::find($id, ['id', 'name', 'guard_name', 'created_at', 'updated_at']);

                if (!$role) {
                    return formatApiResponse(
                        false,
                        'Role not found.',
                        [
                            'total' => 0,
                            'data' => []
                        ]
                    );
                }

                return formatApiResponse(
                    false,
                    'Role retrieved successfully.',
                    [
                        'total' => 1,
                        'data' => formatNote($role)
                    ]
                );
            }

            // Extract query parameters
            $search = $request->input('search', '');
            $sort = $request->input('sort', 'created_at');
            $order = $request->input('order', 'desc');
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            // Build the query
            $query = Role::when($search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%$search%")
                        ->orWhere('guard_name', 'LIKE', "%$search%")
                        ->orWhere('id', 'LIKE', "%$search%");
                });
            });

            // Apply sorting
            $query->orderBy($sort, $order);

            // Get the total count before applying limit and offset
            $total = $query->count();

            // Apply limit and offset
            $roles = $query->limit($limit)
                ->offset($offset)
                ->get(['id', 'name', 'guard_name', 'created_at', 'updated_at']);

            if ($roles->isEmpty()) {
                return formatApiResponse(
                    true,
                    'Roles not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }

            $formattedRoles = $roles->map(function ($role) {
                return formatRole($role);
            });

            return formatApiResponse(
                false,
                'Roles retrieved successfully.',
                [
                    'total' => $total,
                    'data' => $formattedRoles
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while retrieving the roles.'
            ], 500);
        }
    }

    /**
     * Check user permissions.
     *
     * This endpoint checks the module-wise permissions assigned to the authenticated user.
     * If a specific permission is provided in the URL, it checks only that permission for the authenticated user.
     * Otherwise, it returns all permissions for the authenticated user.
     *
     * @authenticated
     *
     * @urlParam permission string optional The specific permission to check. Example: "edit-post"
     *
     * Here is the module-wise permissions list.
     *
     * Activity Log:
     * - manage_activity_log
     * - delete_activity_log

     * Allowances:
     * - create_allowances
     * - manage_allowances
     * - edit_allowances
     * - delete_allowances

     * Clients:
     * - create_clients
     * - manage_clients
     * - edit_clients
     * - delete_clients

     * Contract Types:
     * - create_contract_types
     * - manage_contract_types
     * - edit_contract_types
     * - delete_contract_types

     * Contracts:
     * - create_contracts
     * - manage_contracts
     * - edit_contracts
     * - delete_contracts

     * Deductions:
     * - create_deductions
     * - manage_deductions
     * - edit_deductions
     * - delete_deductions

     * Estimates/Invoices:
     * - create_estimates_invoices
     * - manage_estimates_invoices
     * - edit_estimates_invoices
     * - delete_estimates_invoices

     * Expense Types:
     * - create_expense_types
     * - manage_expense_types
     * - edit_expense_types
     * - delete_expense_types

     * Expenses:
     * - create_expenses
     * - manage_expenses
     * - edit_expenses
     * - delete_expenses

     * Items:
     * - create_items
     * - manage_items
     * - edit_items
     * - delete_items

     * Media:
     * - create_media
     * - manage_media
     * - delete_media

     * Meetings:
     * - create_meetings
     * - manage_meetings
     * - edit_meetings
     * - delete_meetings

     * Milestones:
     * - create_milestones
     * - manage_milestones
     * - edit_milestones
     * - delete_milestones

     * Payment Methods:
     * - create_payment_methods
     * - manage_payment_methods
     * - edit_payment_methods
     * - delete_payment_methods

     * Payments:
     * - create_payments
     * - manage_payments
     * - edit_payments
     * - delete_payments

     * Payslips:
     * - create_payslips
     * - manage_payslips
     * - edit_payslips
     * - delete_payslips

     * Priorities:
     * - create_priorities
     * - manage_priorities
     * - edit_priorities
     * - delete_priorities

     * Projects:
     * - create_projects
     * - manage_projects
     * - edit_projects
     * - delete_projects

     * Statuses:
     * - create_statuses
     * - manage_statuses
     * - edit_statuses
     * - delete_statuses

     * System Notifications:
     * - manage_system_notifications
     * - delete_system_notifications

     * Tags:
     * - create_tags
     * - manage_tags
     * - edit_tags
     * - delete_tags

     * Tasks:
     * - create_tasks
     * - manage_tasks
     * - edit_tasks
     * - delete_tasks

     * Taxes:
     * - create_taxes
     * - manage_taxes
     * - edit_taxes
     * - delete_taxes

     * Timesheet:
     * - create_timesheet
     * - manage_timesheet
     * - delete_timesheet

     * Units:
     * - create_units
     * - manage_units
     * - edit_units
     * - delete_units

     * Users:
     * - create_users
     * - manage_users
     * - edit_users
     * - delete_users

     * Workspaces:
     * - create_workspaces
     * - manage_workspaces
     * - edit_workspaces
     * - delete_workspaces
     *
     * @authenticated
     *
     * @group Role/Permission Management
     *
     * @response 200 {
     *     "error": false,
     *     "message": "Permissions check completed.",
     *     "data": {
     *         "permissions": {
     *             "create_projects": true,
     *             "manage_projects": false,
     *             ...
     *         }
     *     }
     * }
     *
     * @response 500 {
     *     "error": true,
     *     "message": "An error occurred while checking the permission."
     * }
     *
     */

    public function checkPermissions($specificPermission = null)
    {
        try {
            $user = getAuthenticatedUser();
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'User not authenticated'], 401);
            }



            $permissionResults = [];
            if ($specificPermission) {
                $permissionResults['has_permission'] = $user->can($specificPermission);
            } else {
                $permissions = getAllPermissions();
                foreach ($permissions as $permission) {
                    $permissionResults[$permission] = $user->can($permission);
                }
            }

            return response()->json([
                'error' => false,
                'message' => 'Permission check completed.',
                'data' => ['permissions' => $permissionResults]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while checking the permissions.'
            ], 500);
        }
    }


    /**
     * Create a new role.
     *
     * This endpoint allows authenticated users to create a new role and assign permissions.
     *
     * @authenticated
     *
     * @group Role/Permission Management
     *
     * @bodyParam name string required The updated name of the role. Example: "Supervisor"
     * @bodyParam permissions array optional A list of permission IDs to assign to the role. Example: [1, 2, 3]
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Role created successfully.",
     *   "role": {
     *     "id": 5,
     *     "name": "Supervisor",
     *     "permissions": ["edit_tasks", "assign_tasks"]
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "name": ["The name field is required."]
     *   }
     * }
     *
     * @response 409 {
     *   "error": true,
     *   "message": "A role `Manager` already exists."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the role."
     * }
     */

    public function store_api(Request $request)
    {

        try {
            // Validate input
            $formFields = $request->validate([
                'name' => ['required', 'string', 'unique:roles,name'],
                'permissions' => ['array'],
                'permissions.*' => ['integer', 'exists:permissions,id']
            ]);

            $formFields['guard_name'] = 'web';


            // Create role
            $role = Role::create($formFields);

            // Assign permissions
            if ($request->has('permissions')) {
                $filteredPermissions = array_filter($request->permissions, function ($permission) {
                    return $permission != 0;
                });
                $role->syncPermissions($filteredPermissions);
            }

            // Clear cache to apply role changes immediately
            Artisan::call('cache:clear');

            return response()->json([
                'error' => false,
                'message' => 'Role created successfully.',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')
                ]
            ]);
        } catch (RoleAlreadyExists $e) {
            return response()->json([
                'error' => true,
                'message' => 'A role `' . $request->name . '` already exists.'
            ], 409);
        } catch (ValidationException $e) {
            $isApi = request()->get('isApi', false);
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the role.'
            ], 500);
        }
    }

    /**
     * Update an existing role.
     *
     * This endpoint allows authenticated users to update a role name and modify its permissions.
     *
     * @authenticated
     *
     * @group Role/Permission Management
     *
     * @urlParam id int required The ID of the role to update. Example: 5
     * @bodyParam name string required The updated name of the role. Example: "Supervisor"
     * @bodyParam permissions array optional A list of permission IDs to assign to the role. Example: [1, 2, 3]
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Role updated successfully."
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation failed.",
     *   "errors": {
     *     "name": ["The name field is required."]
     *   }
     * }
     *
     * @response 409 {
     *   "error": true,
     *   "message": "A role `Manager` already exists."
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Role not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the role."
     * }
     */

    public function update_api(Request $request, $id)
    {
        try {
            // Validate input
            $formFields = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'permissions' => ['array'],
                'permissions.*' => ['integer', 'exists:permissions,id']
            ]);

            // Find the role
            $role = Role::findOrFail($id);

            // Check if the name already exists (except for the current role)
            if (Role::where('name', $formFields['name'])->where('id', '!=', $id)->exists()) {
                return response()->json([
                    'error' => true,
                    'message' => 'A role `' . $formFields['name'] . '` already exists.'
                ], 409);
            }

            // Update role name
            $role->name = $formFields['name'];
            $role->save();

            // Assign permissions
            if ($request->has('permissions')) {
                $filteredPermissions = array_filter($request->permissions, function ($permission) {
                    return $permission != 0;
                });
                $role->syncPermissions($filteredPermissions);
            }

            // Clear cache to apply role changes immediately
            Artisan::call('cache:clear');

            return formatApiResponse(
                false,
                'Role updated successfully.',
                [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')
                ]

            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Role not found.'
            ], 404);
        } catch (ValidationException $e) {
            $isApi = request()->get('isApi', false);
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the role.'
            ], 500);
        }
    }

    /**
     * Get a specific role with its permissions, grouped by category.
     *
     * @authenticated
     *
     * @group Role/Permission Management
     *
     * @urlParam id int required The ID of the role to retrieve.
     *
     * @response 200 {
     *   "error": false,
     *   "role": {
     *     "id": 1,
     *     "name": "Admin",
     *     "permissions": {
     *       "projects": {
     *         "create_projects": true,
     *         "delete_projects": false
     *       },
     *       "tasks": {
     *         "create_tasks": true
     *       }
     *     }
     *   }
     * }
     */

    public function get_role_api($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        // Get all permissions assigned to the role with their IDs
        $assignedPermissions = $role->permissions->pluck('id', 'name')->toArray();

        // Get all permissions for this guard
        $allPermissions = Permission::where('guard_name', $role->guard_name)->get();

        // Prepare the structured permissions array
        $structuredPermissions = [];

        foreach ($allPermissions as $permission) {
            // Parse the permission name (e.g., "create_project")
            $parts = explode('_', $permission->name, 2);

            if (count($parts) == 2) {
                $action = $parts[0]; // "create"
                $model = ucfirst($parts[1]); // "project" -> "Project"

                // Make it plural if not already
                if (!str_ends_with($model, 's')) {
                    $model .= 's'; // "Project" -> "Projects"
                }

                // Check if this permission is assigned to the role
                $isAssigned = isset($assignedPermissions[$permission->name]) ? 1 : 0;

                // Find existing category or create new one
                $categoryExists = false;
                foreach ($structuredPermissions as &$category) {
                    if ($category['category'] === $model) {
                        $categoryExists = true;

                        // Add the permission to the existing category as a list item
                        $category['permissions_assigned'][] = [
                            'action' => $action,
                            'id' => $permission->id,
                            'isAssigned' => $isAssigned
                        ];
                        break;
                    }
                }

                // If category doesn't exist, create a new one
                if (!$categoryExists) {
                    $structuredPermissions[] = [
                        'category' => $model,
                        'permissions_assigned' => [
                            [
                                'action' => $action,
                                'id' => $permission->id,
                                'isAssigned' => $isAssigned
                            ]
                        ]
                    ];
                }
            }
        }

        return formatApiResponse(
            false,
            'Role retrieved successfully.',
            [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $structuredPermissions
            ]
        );
    }


    /**
     * Delete a role.
     *
     * This endpoint allows authenticated users to delete a specific role.
     *
     * @authenticated
     *
     * @group Role/Permission Management
     *
     * @urlParam id int required The ID of the role to delete.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Role deleted successfully."
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Role not found."
     * }
     *
     * @response 409 {
     *   "error": true,
     *   "message": "Cannot delete this role because it is assigned to users."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the role."
     * }
     */

    public function destroy_api($id)
    {
        try {
            $role = Role::findOrFail($id);

            // Check if the role is assigned to any users
            if ($role->users()->count() > 0) {
                return response()->json([
                    'error' => true,
                    'message' => 'Cannot delete this role because it is assigned to users.'
                ], 409);
            }

            // Delete role using DeletionService
            $response = DeletionService::delete(Role::class, $id, 'Role');
            $data = $response->getData();

            if ($data->error) {
                return response()->json([
                    'error' => true,
                    'message' => $data->message
                ]);
            }

            return response()->json([
                'error' => false,
                'message' => 'Role deleted successfully.'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Role not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while deleting the role.'
            ], 500);
        }
    }

    /**
     * List all permissions.
     *
     * This endpoint retrieves a list of all permissions.
     *
     * @group Role/Permission Management
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Permissions retrieved successfully.",
     *   "total": 5,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "create_projects",
     *       "guard_name": "web",
     *       "created_at": "2023-10-10T17:50:09.000000Z",
     *       "updated_at": "2024-07-23T19:08:16.000000Z"
     *     },
     *     ...
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Permissions not found.",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving the permissions."
     * }
     */

    public function get_permissions_api()
    {
        try {
            // Get all permissions assigned to the role with their IDs
            $assignedPermissions = [];

            // Get all permissions for this guard
            $allPermissions = Permission::where('guard_name', 'web')->get();

            // Prepare the structured permissions array
            $structuredPermissions = [];

            foreach ($allPermissions as $permission) {
                // Parse the permission name (e.g., "create_project")
                $parts = explode('_', $permission->name, 2);

                if (count($parts) == 2) {
                    $action = $parts[0]; // "create"
                    $model = ucfirst($parts[1]); // "project" -> "Project"

                    // Make it plural if not already
                    if (!str_ends_with($model, 's')) {
                        $model .= 's'; // "Project" -> "Projects"
                    }

                    // Check if this permission is assigned to the role
                    $isAssigned = isset($assignedPermissions[$permission->name]) ? 1 : 0;

                    // Find existing category or create new one
                    $categoryExists = false;
                    foreach ($structuredPermissions as &$category) {
                        if ($category['category'] === $model) {
                            $categoryExists = true;

                            // Add the permission to the existing category as a list item
                            $category['permissions_assigned'][] = [
                                'action' => $action,
                                'id' => $permission->id,
                                'isAssigned' => $isAssigned
                            ];
                            break;
                        }
                    }

                    // If category doesn't exist, create a new one
                    if (!$categoryExists) {
                        $structuredPermissions[] = [
                            'category' => $model,
                            'permissions_assigned' => [
                                [
                                    'action' => $action,
                                    'id' => $permission->id,
                                    'isAssigned' => $isAssigned
                                ]
                            ]
                        ];
                    }
                }
            }
            return response()->json([
                'error' => false,
                'message' => 'Permissions retrieved successfully.',
                'total' => count($structuredPermissions),
                'permissions' => $structuredPermissions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while retrieving the permissions.'
            ], 500);
        }
    }
}
