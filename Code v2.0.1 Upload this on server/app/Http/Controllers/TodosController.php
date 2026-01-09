<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\User;
use App\Models\Client;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class TodosController extends Controller
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
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $todos = $this->user->todos()
            ->orderBy('is_completed', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('todos.list', ['todos' => $todos]);
    }

    /**
     * List or search todos.
     *
     * This endpoint retrieves a list of todos based on various filters. The user must be authenticated to perform this action. The request allows filtering by search term, status, and pagination parameters.
     *
     * @authenticated
     *
     * @group Todo Management
     *
     * @urlParam id int optional The ID of the todo to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter todos by id, title, or description. Example: Test
     * @queryParam sort string optional The field to sort by. Defaults to "is_completed". All fields are sortable. Example: created_at
     * @queryParam order string optional The sort order, either "asc" or "desc". Defaults to "desc". Example: asc
     * @queryParam status string optional The status to filter todos by. Example: completed
     * @queryParam limit int optional The number of todos per page for pagination. Defaults to 10. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Defaults to 0. Example: 0
     *
     * @response 200 {
     *     "error": false,
     *     "message": "Todos retrieved successfully.",
     *     "total": 1,
     *     "data": [
     *         {
     *              "id": 35,
     *              "title": "test",
     *              "description": "test",
     *              "priority": "low",
     *              "is_completed": 0,
     *              "created_at": "07-08-2024 15:28:22",
     *              "updated_at": "07-08-2024 15:28:22"
     *         }
     *     ]
     * }
     *
     * @response 200 {
     *     "error": true,
     *     "message": "Todo not found.",
     *     "total": 0,
     *     "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Todos not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 500 {
     *     "error": true,
     *     "message": "An error occurred while retrieving the todos."
     * }
     */


    public function apiList(Request $request, $id = null)
    {
        try {
            if ($id) {
                $todo = $this->user->todos()
                    ->where('id', $id)
                    ->first();

                if (!$todo) {
                    return formatApiResponse(
                        false,
                        'Todo not found.',
                        [
                            'total' => 0,
                            'data' => []
                        ]
                    );
                }

                return formatApiResponse(
                    false,
                    'Todo retrieved successfully.',
                    [
                        'total' => 1,
                        'data' => formatTodo($todo)
                    ]
                );
            }

            // Extract query parameters
            $search = $request->input('search', '');
            $sort = $request->input('sort', 'is_completed');
            $order = $request->input('order', 'desc');
            $status = $request->input('status', '');
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            // Build the query
            $query = $this->user->todos()
                ->when($search, function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('title', 'LIKE', "%$search%")
                            ->orWhere('description', 'LIKE', "%$search%")
                            ->orWhere('id', 'LIKE', "%$search%");
                    });
                })
                ->when($status !== '', function ($query) use ($status) {
                    return $query->where('is_completed', $status);
                });

            // Apply sorting
            if ($sort != 'is_completed') {
                $query->orderBy($sort, $order);
            } else {
                $query->orderBy('is_completed', $request->input('order', 'asc'))
                    ->orderBy('created_at', 'desc');
            }

            // Get the total count before applying limit and offset
            $total = $query->count();

            // Apply limit and offset
            $todos = $query->limit($limit)
                ->offset($offset)
                ->get();

            if ($todos->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Todos not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }

            // Format dates for each todo
            $formattedTodos = $todos->map(function ($todo) {
                return formatTodo($todo);
            });

            return formatApiResponse(
                false,
                'Todos retrieved successfully.',
                [
                    'total' => $total,
                    'data' => $formattedTodos
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while retrieving the todos.'
            ], 500);
        }
    }

    /**
     * Create a new todo.
     *
     * This endpoint creates a new todo item with the specified title, priority, and description. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Todo Management
     *
     * @bodyParam title string required The title of the todo. Example: Finish report
     * @bodyParam priority string required The priority of the todo. Must be one of "low", "medium", or "high". Example: medium
     * @bodyParam description string optional A description of the todo. Example: Complete the report by end of day
     *
     * @response 200 {
     * "error": false,
     * "message": "Todo created successfully.",
     * "id": 36,
     * "data": {
     *   "id": 36,
     *   "title": "test",
     *   "description": "test",
     *   "priority": "low",
     *   "is_completed": 0,
     *   "created_at": "07-08-2024 16:30:09",
     *   "updated_at": "07-08-2024 16:30:09"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "title": [
     *       "The title field is required."
     *     ],
     *     "priority": [
     *       "The priority must be one of the following: low, medium, high."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the todo."
     * }
     */


    public function store(Request $request)
    {
        $isApi = request()->get('isApi', false);
        $rules = [
            'title' => 'required|string',
            'priority' => ['required', 'in:low,medium,high'],
            'description' => 'nullable|string',
            //Reminder fields
            'enable_reminder' => 'nullable|in:on',
            'frequency_type' => 'nullable|in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|between:1,7',
            'day_of_month' => 'nullable|integer|between:1,31',
            'time_of_day' => 'nullable|date_format:H:i',
        ];

        $messages = [
            'priority.in' => 'The priority must be one of the following: low, medium, high.',
        ];

        try {
            $formFields = $request->validate($rules, $messages);
            $formFields['workspace_id'] = $this->workspace->id;
            $formFields['created_by'] = $this->user->id;
            $todo = new Todo($formFields);
            $todo->creator()->associate($this->user);
            $todo->save();
            $todo = Todo::find($todo->id);

            // Todo Reminder
            if (isset($formFields['enable_reminder']) && $formFields['enable_reminder'] == 'on') {
                $todo->reminders()->create([
                    'frequency_type' => $formFields['frequency_type'],
                    'day_of_week' => $formFields['day_of_week'],
                    'day_of_month' => $formFields['day_of_month'],
                    'time_of_day' => $formFields['time_of_day'],
                ]);
            }


            return formatApiResponse(
                false,
                'Todo created successfully.',
                [
                    'id' => $todo->id,
                    'data' => formatTodo($todo)
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the todo.'
            ], 500);
        }
    }

    /**
     * Update an existing todo.
     *
     * This endpoint updates an existing todo item with the specified title, priority, and description. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Todo Management
     *
     * @bodyParam id int required The ID of the todo to be updated. Example: 1
     * @bodyParam title string required The new title of the todo. Example: Finish report
     * @bodyParam priority string required The new priority of the todo. Must be one of "low", "medium", or "high". Example: medium
     * @bodyParam description string optional A new description for the todo. Example: Complete the report by end of day
     *
     * @response 200 {
     * "error": false,
     * "message": "Todo updated successfully.",
     * "id": "36",
     * "data": {
     *   "id": 36,
     *   "is_completed": 0,
     *   "title": "test",
     *   "priority": "low",
     *   "description": "test",
     *   "created_at": "07-08-2024 16:30:09",
     *   "updated_at": "07-08-2024 16:30:09"
     * }
     * }
     *
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The id field is required."
     *     ],
     *     "title": [
     *       "The title field is required."
     *     ],
     *     "priority": [
     *       "The priority must be one of the following: low, medium, high."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the todo."
     * }
     */
    public function update(Request $request)
    {
        $isApi = request()->get('isApi', false);
        $rules = [
            'id' => 'required|exists:todos,id',
            'title' => 'required|string',
            'priority' => ['required', 'in:low,medium,high'],
            'description' => 'nullable|string',
            // Reminder fields
            'enable_reminder' => 'nullable|in:on', // Validation for reminder toggle
            'frequency_type' => 'nullable|in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|between:1,7',
            'day_of_month' => 'nullable|integer|between:1,31',
            'time_of_day' => 'nullable|date_format:H:i',
        ];

        $messages = [
            'priority.in' => 'The priority must be one of the following: low, medium, high.',
            'id.exists' => 'The specified todo does not exist.'
        ];

        try {
            $formFields = $request->validate($rules, $messages);
            $todo = Todo::findOrFail($request->id);
            // Reminder Update

            // Check  Reminder is On than add the reminder
            if ($request->input('enable_reminder') === 'on') {
                // Check if reminder exists
                $reminder = $todo->reminders()->first();
                if ($reminder) {
                    // Update existing reminder
                    $reminder->update([
                        'frequency_type' => $request->input('frequency_type'),
                        'day_of_week' => $request->input('frequency_type') === 'weekly' ? $request->input('day_of_week') : null,
                        'day_of_month' => $request->input('frequency_type') === 'monthly' ? $request->input('day_of_month') : null,
                        'time_of_day' => $request->input('time_of_day'),
                        'is_active' => 1,
                        "last_sent_at" => null,
                    ]);
                } else {
                    // Create new reminder
                    $todo->reminders()->create([
                        'frequency_type' => $request->input('frequency_type'),
                        'day_of_week' => $request->input('frequency_type') === 'weekly' ? $request->input('day_of_week') : null,
                        'day_of_month' => $request->input('frequency_type') === 'monthly' ? $request->input('day_of_month') : null,
                        'time_of_day' => $request->input('time_of_day'),
                        'is_active' => 1
                    ]);
                }
            } else {
                // If reminder is turned off, either delete or deactivate the reminder
                $reminder = $todo->reminders()->first();
                if ($reminder) {
                    // Deactivate the reminder
                    $reminder->update(['is_active' => 0]);
                }
            }

            $todo->update($formFields);
            $formattedTodo = [
                'id' => $todo->id,
                'is_completed' => $todo->is_completed,
                'title' => $todo->title,
                'priority' => $todo->priority,
                'description' => $todo->description,
                'created_at' => format_date($todo->created_at, true),
                'updated_at' => format_date($todo->updated_at, true),
            ];
            return formatApiResponse(
                false,
                'Todo updated successfully.',
                [
                    'id' => $request->id,
                    'data' => $formattedTodo
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the todo.'
            ], 500);
        }
    }


    /**
     * Remove the specified todo.
     *
     * This endpoint deletes a todo item based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Todo Management
     *
     * @urlParam id int required The ID of the todo to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Todo deleted successfully.",
     *   "id": 1,
     *   "title": "Todo Title"
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Todo not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the todo."
     * }
     */
    public function destroy($id)
    {
        $response = DeletionService::delete(Todo::class, $id, 'Todo');
        return $response;
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:todos,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];

        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $todo = Todo::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $todo->title;
            DeletionService::delete(Todo::class, $id, 'Todo');
        }
        Session::flash('message', 'Todo(s) deleted successfully.');
        return response()->json([
            'error' => false,
            'message' => 'Todo(s) deleted successfully.',
            'id' => $deletedIds,
            'titles' => $deletedTitles
        ]);
    }


    /**
     * Update the completion status of a todo.
     *
     * This endpoint updates the completion status of a specified todo item. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Todo Management
     *
     * @urlParam id int required The ID of the todo whose status is to be updated. Example: 1
     * @bodyParam status boolean required The new completion status of the todo. Example: true
     *
     * @response 200 {
     * "error": false,
     * "message": "Status updated successfully.",
     * "id": "60",
     * "activity_message": "Madhavan Vaidya marked todo iouyhgyu as Completed",
     * "data": {
     * "id": 60,
     * "title": "iouyhgyu",
     * "description": "ty8uifyu",
     * "priority": "medium",
     * "is_completed": 1,
     * "created_at": "10-08-2024 10:28:59",
     * "updated_at": "12-08-2024 18:08:14"
     * }

     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The id field is required."
     *     ],
     *     "status": [
     *       "The status field is required."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Status couldn't be updated."
     * }
     */
    public function update_status(Request $request, $id = null)
    {
        $isApi = request()->get('isApi', false);
        if ($id) {
            $request->merge(['id' => $id]);
        }

        $rules = [
            'id' => 'required|exists:todos,id',
            'status' => 'required|boolean'
        ];
        try {
            $request->validate($rules);
            $id = $request->id;
            $status = $request->status;
            $todo = Todo::findOrFail($id);
            if ($todo->is_completed != $status) {
                $todo->is_completed = $status;
                $statusText = $status ? 'Completed' : 'Pending';
                $todo->save();
                Session::flash('message', 'Status updated successfully.');
                return formatApiResponse(
                    false,
                    'Status updated successfully.',
                    [
                        'id' => $id,
                        'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' marked todo ' . trim($todo->title) . ' as ' . trim($statusText),
                        'data' => formatTodo($todo)
                    ]
                );
            } else {
                return response()->json(['error' => true, 'message' => 'No status change detected.']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'Status couldn\'t be updated.'
            ], 500);
        }
    }

    /**
     * Update the priority of a todo.
     *
     * This endpoint updates the priority of a specified todo item. The user must be authenticated to perform this action. The priority must be one of 'low', 'medium', or 'high'.
     *
     * @authenticated
     *
     * @group Todo Management
     *
     * @urlParam id int required The ID of the todo whose priority is to be updated. Example: 1
     * @bodyParam priority string required The new priority of the todo. Must be one of 'low', 'medium', or 'high'. Example: medium
     *
     * @response 200 {
     * "error": false,
     * "message": "Priority updated successfully.",
     * "id": "60",
     * "activity_message": "Madhavan Vaidya updated the priority of todo iouyhgyu from High to Low",
     * "data": {
     * "id": 60,
     * "title": "iouyhgyu",
     * "description": "ty8uifyu",
     * "priority": "low",
     * "is_completed": 1,
     * "created_at": "10-08-2024 10:28:59",
     * "updated_at": "12-08-2024 18:11:13"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The id field is required."
     *     ],
     *     "priority": [
     *       "The priority field is required.",
     *       "The selected priority is invalid."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Priority couldn't be updated."
     * }
     */

    public function update_priority(Request $request, $id = null)
    {
        $isApi = request()->get('isApi', false);
        if ($id) {
            $request->merge(['id' => $id]);
        }

        $rules = [
            'id' => 'required|exists:todos,id',
            'priority' => 'required|in:low,medium,high'
        ];

        try {
            $request->validate($rules);
            $id = $request->id;
            $priority = $request->priority;
            $todo = Todo::findOrFail($id);
            if ($todo->priority != $priority) {
                $currentPriorityText = ucfirst($todo->priority);
                $todo->priority = $priority;
                $todo->save();
                $priorityText = ucfirst($priority);
                Session::flash('message', 'Priority updated successfully.');

                return formatApiResponse(
                    false,
                    'Priority updated successfully.',
                    [
                        'id' => $id,
                        'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' updated the priority of todo ' . trim($todo->title) . ' from ' . trim($currentPriorityText) . ' to ' . trim($priorityText),
                        'data' => formatTodo($todo)
                    ]
                );
            } else {
                return response()->json(['error' => true, 'message' => 'No priority change detected.']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'Priority couldn\'t be updated.'
            ], 500);
        }
    }



    public function get($id)
    {

        $todo = Todo::with('reminders')->findOrFail($id);
        return response()->json(['todo' => $todo]);
    }
}
