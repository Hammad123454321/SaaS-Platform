<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StatusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('status.list');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('status.create');
    }

    /**
     * Create a new status.
     *
     * This endpoint allows authenticated users to create a new status with a unique slug and assign roles to it.
     *
     * @authenticated
     *
     * @group Status Management
     *
     * @bodyParam title string required The title of the status.
     * @bodyParam color string required The color code associated with the status.
     * @bodyParam role_ids array optional An array of role IDs to be associated with the status.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Status created successfully.",
     *   "id": 101,
     *   "status": {
     *     "id": 101,
     *     "title": "In Progress",
     *     "color": "primary",
     *     "slug": "in-progress"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred.",
     *   "errors": {
     *     "title": ["The title field is required."],
     *     "color": ["The color field is required."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Status couldn't be created."
     * }
     */


    public function store(Request $request)
    {
        try {
            $formFields = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'color' => ['required', 'string'],
                'role_ids' => ['nullable', 'array'],
                'role_ids.*' => ['integer', 'exists:roles,id']
            ]);

            $formFields['slug'] = generateUniqueSlug($request->title, Status::class);

            $status = Status::create($formFields);

            if ($status) {
                if (!empty($request->role_ids)) {
                    $status->roles()->attach($request->role_ids);
                }

                return response()->json([
                    'error' => false,
                    'message' => 'Status created successfully.',
                    'type' => 'status', // ✅ Required for refreshParentFormDropdowns
                    'data' => [         // ✅ Required structure
                        'id' => $status->id,
                        'name' => $status->title, // ✅ Using 'title' field as 'name'
                    ],
                    // Keep your existing response structure for backward compatibility
                    'id' => $status->id,
                    'status' => $status
                ]);
            }

            return response()->json([
                'error' => true,
                'message' => "Status couldn't be created."
            ], 500);
        } catch (ValidationException $e) {
            $isApi = request()->get('isApi', false);
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => "Status couldn't be created."
            ], 500);
        }
    }

    public function list()
    {
        $search = request('search');
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $status = Status::orderBy($sort, $order);

        if ($search) {
            $status = $status->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        $total = $status->count();

        $canEdit = checkPermission('edit_statuses');
        $canDelete = checkPermission('delete_statuses');

        $status = $status
            ->paginate(request("limit"))
            ->through(function ($status) use ($canEdit, $canDelete) {
                $roles = $status->roles->pluck('name')->map(function ($roleName) {
                    return ucfirst($roleName);
                })->implode(', ');

                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-status" data-bs-toggle="modal" data-bs-target="#edit_status_modal" data-id="' . $status->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $status->id . '" data-type="status">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }
                $actions = $actions ?: '-';
                return [
                    'id' => $status->id,
                    'title' => $status->title . ($status->id == 0 ? ' <span class="badge bg-success">' . get_label('default', 'Default') . '</span>' : ''),
                    'roles_has_access' => $roles ?: ' - ',
                    'color' => '<span class="badge bg-' . $status->color . '">' . $status->title . '</span>',
                    'created_at' => format_date($status->created_at, true),
                    'updated_at' => format_date($status->updated_at, true),
                    'actions' => $actions ?? '-',
                ];
            });

        return response()->json([
            "rows" => $status->items(),
            "total" => $total,
        ]);
    }

    /**
     * List or search statuses.
     *
     * This endpoint retrieves a list of statuses based on various filters. The user must be authenticated to perform this action. The request allows searching and sorting by different parameters.
     *
     * @authenticated
     *
     * @group Status Management
     *
     * @urlParam id int optional The ID of the status to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter statuses by title or id. Example: Active
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, title, color, created_at, and updated_at. Example: title
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam limit int optional The number of statuses per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Statuses retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Active",
     *       "color": "primary",
     *       "created_at": "20-07-2024 17:50:09",
     *       "updated_at": "21-07-2024 19:08:16"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Status not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Statuses not found",
     *   "total": 0,
     *   "data": []
     * }
     */
    public function apiList(Request $request, $id = '')
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $statusQuery = Status::query();

        // Apply search filter
        if ($search) {
            $statusQuery->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        if ($id) {
            $status = $statusQuery->find($id);
            if (!$status) {
                return formatApiResponse(
                    false,
                    'Status not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }

            if (!canSetStatus($status)) {
                return formatApiResponse(
                    false,
                    'Access denied for the specified status',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }

            return formatApiResponse(
                true,
                'Status retrieved successfully',
                [
                    'total' => 1,
                    'data' => [
                        [
                            'id' => $status->id,
                            'title' => $status->title,
                            'color' => $status->color,
                            'roles' => [
                                'ids' => $status->roles()->pluck('id')->toArray(),
                                'names' => $status->roles()->pluck('name')->toArray()
                            ],
                            'created_at' => format_date($status->created_at, to_format: 'Y-m-d'),
                            'updated_at' => format_date($status->updated_at, to_format: 'Y-m-d'),
                        ]
                    ]
                ]
            );
        } else {
            $statuses = $statusQuery->get()->filter(function ($status) {
                return canSetStatus($status);
            });

            $total = $statuses->count(); // Count only accessible statuses

            $statuses = $statuses->sortBy($sort, $order === 'DESC' ? SORT_DESC : SORT_ASC)
                ->slice($offset, $limit);

            if ($statuses->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Statuses not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }

            $data = $statuses->map(function ($status) {
                return [
                    'id' => $status->id,
                    'title' => $status->title,
                    'color' => $status->color,
                    'roles' => [
                        'ids' => $status->roles()->pluck('id')->toArray(),
                        'names' => $status->roles()->pluck('name')->toArray()
                    ],
                    'created_at' => format_date($status->created_at, to_format: 'Y-m-d'),
                    'updated_at' => format_date($status->updated_at, to_format: 'Y-m-d'),
                ];
            });

            return formatApiResponse(
                false,
                'Statuses retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data->values() // Ensure proper reindexing
                ]
            );
        }
    }



    /**
     * Get details of a specific status.
     *
     * This endpoint retrieves the details of a specific status, including the roles associated with it.
     *
     * @authenticated
     *
     * @group Status Management
     *
     * @urlParam id int required The ID of the status to retrieve.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Status retrieved successfully.",
     *   "status": {
     *     "id": 101,
     *     "title": "In Progress",
     *     "color": "primary",
     *     "slug": "in-progress"
     *   },
     *   "roles": [1, 2, 3]
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Status not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Could not retrieve status."
     * }
     */

    public function get($id)
    {
        try {
            $status = Status::findOrFail($id);
            $roles = $status->roles()->pluck('id')->toArray();

            return response()->json([
                'error' => false,
                'message' => 'Status retrieved successfully.',
                'status' => $status,
                'roles' => $roles
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Status not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Could not retrieve status.'
            ], 500);
        }
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update an existing status.
     *
     * This endpoint allows authenticated users to update a status, including modifying the title, color, and associated roles.
     *
     * @authenticated
     *
     * @group Status Management
     *
     * @bodyParam id int required The ID of the status to update.
     * @bodyParam title string required The updated title of the status.
     * @bodyParam color string required The updated color code associated with the status.
     * @bodyParam role_ids array optional An array of role IDs to associate with the status.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Status updated successfully.",
     *   "id": 101
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred.",
     *   "errors": {
     *     "id": ["The id field is required."],
     *     "title": ["The title field is required."],
     *     "color": ["The color field is required."]
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Status not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Status couldn't be updated."
     * }
     */

    public function update(Request $request)
    {
        try {
            $formFields = $request->validate([
                'id' => ['required', 'integer', 'exists:statuses,id'],
                'title' => ['required', 'string', 'max:255'],
                'color' => ['required', 'string'],
                'role_ids' => ['nullable', 'array'],
                'role_ids.*' => ['integer', 'exists:roles,id']
            ]);

            $status = Status::findOrFail($request->id);
            $formFields['slug'] = generateUniqueSlug($request->title, Status::class, $request->id);

            if ($status->update($formFields)) {
                if (isset($request->role_ids)) {
                    $status->roles()->sync($request->role_ids);
                }

                return response()->json([
                    'error' => false,
                    'message' => 'Status updated successfully.',
                    'id' => $status->id
                ]);
            }

            return response()->json([
                'error' => true,
                'message' => "Status couldn't be updated."
            ], 500);
        } catch (ValidationException $e) {
            $isApi = request()->get('isApi', false);
            return formatApiValidationError($isApi, $e->errors());
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Status not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => "Status couldn't be updated."
            ], 500);
        }
    }


    /**
     * Delete a status.
     *
     * This endpoint allows authenticated users to delete a specific status. Before deletion,
     * all associated projects and tasks will be updated to have a default status ID of `0`.
     *
     * @authenticated
     *
     * @group Status Management
     *
     * @urlParam id int required The ID of the status to delete.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Status deleted successfully.",
     *   "id": 101,
     *   "title": "In Progress"
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Status not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Status couldn't be deleted."
     * }
     */

    public function destroy($id)
    {
        try {
            $status = Status::findOrFail($id);

            // Update related projects and tasks to have a default status ID of 0
            $status->projects(false)->update(['status_id' => 0]);
            $status->tasks(false)->update(['status_id' => 0]);

            // Attempt to delete the status using a DeletionService
            $response = DeletionService::delete(Status::class, $id, 'Status');
            $data = $response->getData();

            if ($data->error) {
                return response()->json([
                    'error' => true,
                    'message' => $data->message
                ]);
            }

            return response()->json([
                'error' => false,
                'message' => 'Status deleted successfully.',
                'id' => $id,
                'title' => $status->title
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Status not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => "Status couldn't be deleted."
            ], 500);
        }
    }


    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:statuses,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        $defaultStatusIds = [];
        $nonDefaultIds = [];

        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $status = Status::findOrFail($id);
            if ($status) {
                if ($status->id == 0) {
                    $defaultStatusIds[] = $id;
                } else {
                    $status->projects(false)->update(['status_id' => 0]);
                    $status->tasks(false)->update(['status_id' => 0]);
                    $deletedIds[] = $id;
                    $deletedTitles[] = $status->title;
                    DeletionService::delete(Status::class, $id, 'Status');
                    $nonDefaultIds[] = $id;
                }
            }
        }

        if (count($defaultStatusIds) > 0) {
            if (count($ids) == 1) {
                return response()->json(['error' => true, 'message' => 'Default status cannot be deleted.']);
            } else {
                return response()->json(['error' => false, 'message' => 'Status(es) deleted successfully except default.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
            }
        } else {
            return response()->json(['error' => false, 'message' => 'Status(es) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
        }
    }
}
