<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\TaskList;
use Illuminate\Http\Request;
use App\Services\DeletionService;

class TaskListController extends Controller
{
    public function index()
    {
        $taskLists = TaskList::all();
        return view('task_lists.index', compact('taskLists'));
    }


    /**
     * Create a new task list.
     *
     * Creates a new task list associated with a specific project.
     *
     * @group Task List Management
     *
     * @bodyParam name string required The name of the task list. Max 255 characters. Example: UI Tasks
     * @bodyParam project_id integer required The ID of the project this task list belongs to. Must exist in the `projects` table. Example: 5
     * @queryParam isApi boolean optional Whether to return a JSON API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Task list created successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "UI Tasks",
     *     "project": "Website Redesign",
     *     "created_at": "30 May, 2025",
     *     "updated_at": "30 May, 2025"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The given data was invalid.",
     *   "data": {
     *     "errors": {
     *       "name": ["The name field is required."],
     *       "project_id": ["The selected project id is invalid."]
     *     }
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Task list couldn't be created.",
     *   "data": []
     * }
     */

    public function store(Request $request)
    {

        $isApi = request()->get('isApi');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'project_id' => 'required|integer|exists:projects,id',

        ]);

        try {
            $taskList = TaskList::create([
                'name' => $validated['name'],
                'project_id' => $validated['project_id'],
            ]);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Task list created successfully.',
                    [
                        'data' => formatTaskList($taskList)
                    ]
                );
            }


            return response()->json(['error' => false, 'message' => 'Task list created successfully']);
        } catch (Exception $e) {

            if ($isApi) {
                return formatApiResponse(
                    true,
                    config('app.debug') ? $e->getMessage() : 'Task list couldn\'t created.',
                    [],
                    500
                );
            }
            return response()->json(['error' => true, 'message' => 'Task list couldn\'t created.']);
        }
    }

    /**
     * Retrieve a specific task list.
     *
     * Fetches a task list by its ID, including associated project details.
     *
     * @group Task List Management
     *
     * @urlParam id integer required The ID of the task list to retrieve. Must exist in the `task_lists` table. Example: 1
     * @queryParam isApi boolean optional Whether to return a JSON API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Task list retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "UI Tasks",
     *     "project": "Website Redesign",
     *     "created_at": "30 May, 2025",
     *     "updated_at": "30 May, 2025"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Task list not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */


    public function get($id)
    {
        $isApi = request()->get('isApi', false);

        try {

            $task_list = TaskList::with('project')->find($id);
            if (!$task_list) {

                if ($isApi) {
                    return formatApiResponse(
                        true,
                        'Task list not found.',
                        [],
                        404
                    );
                }

                return response()->json(['error' => true, 'message' => 'TaskList  not found.']);
            }

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Task list retrieved successfully.',
                    [
                        'data' => formatTaskList($task_list)
                    ]
                );
            }
            return response()->json(['error' => false, 'task_list' => $task_list]);
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }

    /**
     * Update a task list.
     *
     * Updates the name of an existing task list.
     *
     * @group Task List Management
     *
     * @bodyParam id integer required The ID of the task list to update. Must exist in the `task_lists` table. Example: 1
     * @bodyParam name string required The new name for the task list. Max 255 characters. Example: Backend Tasks
     * @queryParam isApi boolean optional Whether to return a JSON API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Task list updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "Backend Tasks",
     *     "project": "Website Redesign",
     *     "created_at": "30 May, 2025",
     *     "updated_at": "30 May, 2025"
     *   }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "The given data was invalid.",
     *   "data": {
     *     "errors": {
     *       "id": ["The selected id is invalid."],
     *       "name": ["The name field is required."]
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Task list not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Task lists couldn't be updated.",
     *   "data": []
     * }
     */


    public function update(Request $request)
    {

        $isApi = request()->get('isApi');

        $validatedData = $request->validate([
            'id' => 'required|exists:task_lists,id',
            'name' => 'required|string|max:255',
        ]);
        try {

            $taskList = TaskList::findOrFail($validatedData['id']);
            $taskList->update([
                'name' => $validatedData['name'],
            ]);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Task list updated successfully',
                    [
                        'data' => formatTaskList($taskList)
                    ]
                );
            }

            return response()->json(['error' => false, 'message' => 'Task list updated successfully']);
        } catch (Exception $e) {

            if ($isApi) {
                return formatApiResponse(
                    true,
                    config('app.debug') ? $e->getMessage() : 'An error occurred',
                    [],
                    500
                );
            }

            return response()->json(['error' => true, 'message' => 'Task lists Couldn\'t be Updated']);
        }
    }


    /**
     * Delete a task list.
     *
     * Permanently deletes a task list by its ID.
     *
     * @group Task List Management
     *
     * @urlParam id integer required The ID of the task list to delete. Must exist in the `task_lists` table. Example: 1
     * @queryParam isApi boolean optional Whether to return a JSON API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Task list deleted successfully.",
     *   "data": []
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Task list not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */


    public function destroy($id)
    {
        $isApi = request()->get('isApi', false);

        try {
            $tag = TaskList::findOrFail($id);

            $response = DeletionService::delete(TaskList::class, $id, 'TaskList');

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Task list deleted successfully.',
                    [],
                    200

                );
            }

            return $response;
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred.',
                [],
                500
            );
        }
    }
    public function list()
    {
        $search = request('search');
        $sort = request('sort', "id");
        $order = request('order', "DESC");
        $limit = request('limit', 10);

        $task_lists = TaskList::orderBy($sort, $order);


        if ($search) {
            $task_lists->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%')
                    ->orWhereHas('project', function ($query) use ($search) {
                        $query->where('title', 'like', '%' . $search . '%');
                    });
            });
        }

        $total = $task_lists->count();

        $task_lists = $task_lists
            ->paginate($limit)
            ->through(
                fn($task_list) => [
                    'id' => $task_list->id,
                    'name' => ucwords($task_list->name),
                    'project' => ucwords($task_list->project->title),
                    'created_at' => format_date($task_list->created_at),
                    'updated_at' => format_date($task_list->updated_at),
                    'actions' => $this->getActions($task_list),
                ]
            );

        return response()->json([
            "rows" => $task_lists->items(),
            "total" => $total,
        ]);
    }


    /**
     * Get list of task lists (API format).
     *
     * Returns a list of task lists for the current workspace in API format, with optional filtering and sorting.
     *
     * @group Task List Management
     *
     * @queryParam search string optional Search keyword to filter task lists by name, ID, or project title. Example: UI
     * @queryParam sort string optional Field to sort by. Defaults to id. Example: name
     * @queryParam order string optional Sort order: ASC or DESC. Defaults to DESC. Example: ASC
     * @queryParam limit integer optional Number of records to return. Defaults to 10. Example: 20
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Task lists retrieved successfully.",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "UI Tasks",
     *       "project": "Website Redesign",
     *       "created_at": "30 May, 2025",
     *       "updated_at": "30 May, 2025"
     *     }
     *   ]
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */


    public function apiList()
    {

        try {

            $search = request('search');
            $sort = request('sort', "id");
            $order = request('order', "DESC");
            $limit = request('limit', 10);

            $task_lists = TaskList::orderBy($sort, $order);


            if ($search) {
                $task_lists->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%')
                        ->orWhereHas('project', function ($query) use ($search) {
                            $query->where('title', 'like', '%' . $search . '%');
                        });
                });
            }

            $total = $task_lists->count();

            $task_lists = $task_lists
                ->take($limit)
                ->get()
                ->map(function ($task_list) {
                    return formatTaskList($task_list);
                });

            return formatApiResponse(
                false,
                'Task lists retrived successfully.',
                [
                    'total' => $total,
                    'data' => $task_lists
                ]
            );
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }


    private function getActions($task_list)
    {
        $actions = '';
        $canEdit = true;  // Replace with your actual condition
        $canDelete = true; // Replace with your actual condition


        if ($canEdit) {
            $actions .= '<a href="javascript:void(0);" class="edit-task-list" data-id="' . $task_list->id . '" title="' . get_label('update', 'Update') . '">' .
                '<i class="bx bx-edit mx-1"></i>' .
                '</a>';
        }

        if ($canDelete) {
            $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $task_list->id . '" data-type="task-lists" data-table="table">' .
                '<i class="bx bx-trash text-danger mx-1"></i>' .
                '</button>';
        }



        return $actions ?: '-';
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:task_lists,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);
        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $tag = TaskList::findOrFail($id);

            $deletedIds[] = $id;
            $deletedTitles[] = $tag->title;
            DeletionService::delete(TaskList::class, $id, 'TaskList');
        }
        return response()->json(['error' => false, 'message' => 'Task List(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }


    /**
     * Search task lists.
     *
     * Searches for task lists by name or project ID, or fetches one by ID.
     *
     * @authenticated
     *
     * @group Task List Management
     *
     * @queryParam search string optional Search keyword. Example: Sprint
     * @queryParam project_id integer optional ID of the related project. Example: 3
     * @queryParam id integer optional Specific task list ID to fetch. Example: 1
     * @queryParam isApi boolean optional Whether to return API response format. Example: true
     *
     * @response 200 {
     *   "id": 1,
     *   "name": "Sprint 1"
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred"
     * }
     */


    public function searchTaskLists(Request $request)
    {
        $isApi = request()->get('isApi', false);

        try {

            $search = $request->input('search', '');
            $projectId = $request->input('project_id');
            $taskListId = $request->input('id'); // Add ID parameter

            // If specific ID is provided, return only that task list
            if ($taskListId) {
                $taskList = TaskList::select('id', 'name')
                    ->when($projectId, function ($query) use ($projectId) {
                        $query->where('project_id', $projectId);
                    })
                    ->find($taskListId)->toArray();

                if ($isApi) {
                    return formatApiResponse(
                        false,
                        'Task list retrieved successfully.',
                        [
                            'data' => formatTaskList($taskList)
                        ]
                    );
                }

                return response()->json([$taskList]); // Wrap in an array
            }


            // Otherwise, return filtered results
            $taskListsQuery = TaskList::query()
                ->when($projectId, function ($query) use ($projectId) {
                    $query->where('project_id', $projectId);
                })
                ->when($search, function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                })
                ->select('id', 'name')
                ->get();

            if ($isApi) {

                $taskListsQuery = $taskListsQuery->map(function ($taskList) {
                    return formatApiResponse(
                        false,
                        'Task lists retrived successfully',
                        [
                            'data' => formatTaskList($taskList)
                        ],
                        200
                    );
                });
            }

            return response()->json($taskListsQuery);
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }
}
