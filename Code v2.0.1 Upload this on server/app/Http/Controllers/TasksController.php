<?php

namespace App\Http\Controllers;

use PDO;
use Exception;
use Carbon\Carbon;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Priority;
use App\Models\TaskList;
use App\Models\Workspace;
use App\Models\CustomField;
use Illuminate\Support\Arr;
use App\Imports\TasksImport;
use Illuminate\Http\Request;
use App\Models\RecurringTask;
use App\Models\CommentAttachment;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\UserClientPreference;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Helpers\FileValidationHelper;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Request as FacadesRequest;

class TasksController extends Controller
{
    protected $workspace;
    protected $user;
    protected $guard;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // fetch session and use it in entire class with constructor
            $this->workspace = Workspace::find(getWorkspaceId());
            $this->user = getAuthenticatedUser();
            $this->guard = getGuardName();
            return $next($request);
        });
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $id = '')
    {
        $isFavorites = request()->get('favorite', false);
        $project = (object)[];
        if ($id) {
            $project = Project::findOrFail($id);
            $tasks = isAdminOrHasAllDataAccess() ? $project->tasks : $this->user->project_tasks($id);
        } else {
            $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks : $this->user->tasks();
        }
        $tasks = $tasks->count();
        $customFields = CustomField::where('module', 'task')->get();
        $is_favorites = 0;
        if ($isFavorites) {
            $is_favorites = 1;
        }
        return view('tasks.tasks', ['project' => $project, 'tasks' => $tasks, 'is_favorites' => $is_favorites, 'customFields' => $customFields]);
    }
    /**
     * Create a new task.
     *
     * This endpoint creates a new task with the provided details. The user must be authenticated to perform this action. The request validates various fields, including title, status, priority, start and due dates, project association, and optional notes.
     *
     * @authenticated
     *
     * @group Task Management
     *
     * @bodyParam title string required The title of the task. Example: New Task
     * @bodyParam status_id integer required The status of the task. Must exist in the `statuses` table. Example: 1
     * @bodyParam priority_id integer nullable The priority of the task. Must exist in the `priorities` table. Example: 2
     * @bodyParam start_date string|null optional The start date of the task in the format specified in the general settings. Example: 2024-07-20
     * @bodyParam due_date string|null optional The due date of the task in the format specified in the general settings. Example: 2024-08-20
     * @bodyParam description string nullable A description of the task. Example: This is a detailed description of the task.
     * @bodyParam project integer required The ID of the project associated with the task. Must exist in the `projects` table. Example: 10
     * @bodyParam note string nullable Additional notes about the task. Example: Urgent
     * @bodyParam user_id array nullable An array of user IDs to be assigned to the task. Example: [1, 2, 3]
     * @bodyParam clientCanDiscuss string optional Indicates if the client can participate in task discussions. Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user; otherwise, it will be considered 0 by default. The value should be 'on' to allow client participation. Example: on
     *
     * @response 200 {
     * "error": false,
     * "message": "Task created successfully.",
     * "id": 280,
     * "parent_id": "420",
     * "parent_type": "project",
     * "data": {
     *   "id": 280,
     *   "workspace_id": 6,
     *   "title": "Res Test",
     *   "status": "Default",
     *   "status_id": "0",
     *   "priority": "Default",
     *   "priority_id": "0",
     *   "users": [
     *     {
     *       "id": 7,
     *       "first_name": "Madhavan",
     *       "last_name": "Vaidya",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     *     }
     *   ],
     *   "user_id": [1,2],
     *   "clients": [
     *     {
     *       "id": 173,
     *       "first_name": "666",
     *       "last_name": "666",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     *     }
     *   ],
     *   "start_date": "07-08-2024",
     *   "due_date": "07-08-2024",
     *   "project": {
     *     "id": 420,
     *     "title": "Updated Project Title"
     *   },
     *   "description": "Test Desc",
     *   "note": "Test Note",
     *   "created_at": "07-08-2024 13:02:52",
     *   "updated_at": "07-08-2024 13:02:52"
     * }
     *
     * }
     * @response 422 {
     *  "error": true,
     *  "message": "Validation errors occurred",
     *  "errors": {
     *    "title": ["The title field is required."],
     *    "status_id": ["The selected status_id is invalid."],
     *    ...
     *  }
     * }
     * @response 500 {
     *  "error": true,
     *  "message": "An error occurred while creating the task."
     * }
     */
    public function store(Request $request)
    {
        $isApi = request()->get('isApi', false);
        if ($request->input('priority_id') == 0) {
            $request->merge(['priority_id' => null]);
        }
        $rules = [
            'title' => 'required',
            'status_id' => 'required|exists:statuses,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'start_date' => [
                'nullable',
                function ($attribute, $value, $fail) use ($isApi) {
                    $endDate = request()->input('due_date');
                    $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);
                    if (!empty($errors['start_date'])) {
                        foreach ($errors['start_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'due_date' => [
                'nullable',
                function ($attribute, $value, $fail) use ($isApi) {
                    $startDate = request()->input('start_date');
                    $errors = validate_date_format_and_order($startDate, $value, $isApi ? 'Y-m-d' : null, endDateKey: 'due_date');
                    if (!empty($errors['due_date'])) {
                        foreach ($errors['due_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'description' => 'nullable|string',
            'project' => 'required|exists:projects,id',
            'note' => 'nullable|string',
            'user_id' => 'nullable|array',
            'user_id.*' => 'exists:users,id', // Validate that each user_id exists in the users table
            // Validation for reminder toggle
            'enable_reminder' => 'nullable|in:on',
            'frequency_type' => 'nullable|in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|between:1,7',
            'day_of_month' => 'nullable|integer|between:1,31',
            'time_of_day' => 'nullable|date_format:H:i',
            // Validation for recurring task
            'enable_recurring_task' => 'nullable|in:on',
            'recurrence_frequency' => 'nullable|in:daily,weekly,monthly,yearly',
            'recurrence_day_of_week' => 'nullable|integer|min:1|max:7',
            'recurrence_day_of_month' => 'nullable|integer|min:1|max:31',
            'recurrence_month_of_year' => 'nullable|integer|min:1|max:12',
            'recurrence_starts_from' => 'nullable|date|after_or_equal:today',
            'recurrence_occurrences' => 'nullable|integer|min:1',
            'parent_id' => 'nullable',
            'billing_type' => 'nullable|in:none,billable,non-billable',
            'completion_percentage' => ['nullable', 'integer', 'min:0', 'max:100', 'in:0,10,20,30,40,50,60,70,80,90,100'],
            'task_list_id' => 'nullable|exists:task_lists,id',
        ];
        $messages = [
            'status_id.required' => 'The status field is required.'
        ];
        try {
            $formFields = $request->validate($rules, $messages);
            $status = Status::findOrFail($request->input('status_id'));
            if (canSetStatus($status)) {
                $project_id = $request->input('project');
                $start_date = $request->input('start_date');
                $due_date = $request->input('due_date');
                if ($start_date) {
                    $formFields['start_date'] = format_date($start_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
                }
                if ($due_date) {
                    $formFields['due_date'] = format_date($due_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
                }
                $formFields['workspace_id'] = getWorkspaceId();
                $formFields['created_by'] = $this->user->id;
                $formFields['project_id'] = $project_id;
                $userIds = $request->input('user_id', []);
                unset($formFields['user_id']);
                $clientCanDiscuss = isAdminOrHasAllDataAccess() && $request->filled('clientCanDiscuss') && $request->input('clientCanDiscuss') == 'on' ? 1 : 0;
                $formFields['client_can_discuss'] = $clientCanDiscuss;
                $new_task = Task::create($formFields);
                $task_id = $new_task->id;
                $task = Task::find($task_id);
                $task->statusTimelines()->create([
                    'status' => $status->title,
                    'new_color' => $status->color,
                    'previous_status' => '-',
                    'changed_at' => now(),
                ]);
                // Set creator as a participant automatically if !isAdminOrHasAllDataAccess
                if (!isAdminOrHasAllDataAccess()) {
                    if ($this->guard == 'web' && !in_array($this->user->id, $userIds)) {
                        array_splice($userIds, 0, 0, $this->user->id);
                    }
                }
                $task->users()->attach($userIds);
                if ($request->has('is_favorite') && $request->input('is_favorite') == 1) {
                    $this->user->favorites()->create([
                        'favoritable_type' => Task::class,
                        'favoritable_id' => $task_id,
                    ]);
                }
                // Check Task Reminder is On than add the reminder
                if (isset($formFields['enable_reminder']) && $formFields['enable_reminder'] == 'on') {
                    $task->reminders()->create([
                        'frequency_type' => $formFields['frequency_type'],
                        'day_of_week' => $formFields['day_of_week'],
                        'day_of_month' => $formFields['day_of_month'],
                        'time_of_day' => $formFields['time_of_day'],
                    ]);
                }
                // Check Task Recurring Task is On than add the recurring task
                if (isset($formFields['enable_recurring_task']) && $formFields['enable_recurring_task'] == 'on') {
                    $task->recurringTask()->create([
                        'frequency' => $formFields['recurrence_frequency'],
                        'day_of_week' => $formFields['recurrence_day_of_week'],
                        'day_of_month' => $formFields['recurrence_day_of_month'],
                        'month_of_year' => $formFields['recurrence_month_of_year'],
                        'starts_from' => $formFields['recurrence_starts_from'],
                        'number_of_occurrences' => $formFields['recurrence_occurrences'],
                    ]);
                }

                if ($request->has('custom_fields')) {
                    foreach ($request->input('custom_fields') as $fieldId => $value) {
                        // Handle checkbox arrays
                        if (is_array($value)) {
                            $value = json_encode($value);
                        }

                        $task->customFields()->create([
                            'custom_field_id' => $fieldId,
                            'value' => $value
                        ]);
                    }
                }


                $notification_data = [
                    'type' => 'task',
                    'type_id' => $task_id,
                    'type_title' => $task->title,
                    'access_url' => 'tasks/information/' . $task->id,
                    'action' => 'assigned'
                ];
                // $clientIds = $project->clients()->pluck('clients.id')->toArray();
                // $recipients = array_merge(
                //     array_map(function ($userId) {
                //         return 'u_' . $userId;
                //     }, $userIds),
                //     array_map(function ($clientId) {
                //         return 'c_' . $clientId;
                //     }, $clientIds)
                // );
                $recipients = array_map(function ($userId) {
                    return 'u_' . $userId;
                }, $userIds);
                processNotifications($notification_data, $recipients);
                return formatApiResponse(
                    false,
                    'Task created successfully.',
                    [
                        'id' => $new_task->id,
                        'parent_id' => $project_id,
                        'parent_type' => 'project',
                        'data' => formatTask($task)
                    ]
                );
            } else {
                return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the task.' . $e->getMessage()
            ], 500);
        }
    }
    public function showBulkUploadForm(Request $request)
    {
        $sampleFileUrl = asset('storage/files/Tasks bulk upload sample.xlsx');
        $helpUrl = asset('storage/files/Tasks bulk upload instructions.pdf');
        return view('bulk-upload', [
            'entity' => 'tasks',
            'form_action' => url('tasks/process-bulk-upload'),
            'sample_file_url' => $sampleFileUrl,
            'help_url' => $helpUrl
        ]);
    }
    public function importBulkTasks(Request $request)
    {
        // Validate file type (ensure it's Excel or CSV)
        $request->validate([
            'bulk_file' => 'required|mimes:xlsx,xls,csv'
        ]);
        try {
            // Initialize the import class
            $import = new TasksImport;
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
                'message' => 'Tasks imported successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while importing tasks: ' . $e->getMessage()
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
        $task = Task::with('reminders', 'recurringTask', 'subtasks', 'project')->findOrFail($id);
        $project = $task->project;
        $customFields = CustomField::where('module', 'task')->get();
        return view('tasks.task_information', ['task' => $task, 'auth_user' => $this->user, 'project' => $project, 'customFields' => $customFields]);
    }
    public function get($id)
    {
        $task = Task::with('users', 'reminders', 'recurringTask', 'customFields.customField')->findOrFail($id);
        $project = $task->project()->with(relations: 'users')->firstOrFail();
        // $recursionSettings = RecurringTask::with('users')->findOrFail($id);

        // Format custom fields for easier frontend usage
        $formattedCustomFields = [];
        foreach ($task->customFields as $fieldable) {
            $formattedCustomFields[$fieldable->custom_field_id] = [
                'field_id' => $fieldable->custom_field_id,
                'field_label' => $fieldable->customField->field_label,
                'field_type' => $fieldable->customField->field_type,
                'value' => $fieldable->value
            ];
        }
        // dd($formattedCustomFields);
        $task->formatted_custom_fields = $formattedCustomFields;
        return response()->json(['error' => false, 'task' => $task, 'project' => $project,]);
    }
    /**
     * Update an existing task.
     *
     * This endpoint updates the details of an existing task. The user must be authenticated to perform this action. The request validates various fields including title, status, priority, start and due dates, and optional notes. It also handles user assignments and notifies relevant parties of any status changes.
     *
     * @authenticated
     *
     * @group Task Management
     *
     * @bodyParam id integer required The ID of the task to be updated. Must exist in the `tasks` table. Example: 267
     * @bodyParam title string required The title of the task. Example: Updated Task
     * @bodyParam status_id integer required The status of the task. Must exist in the `statuses` table. Example: 2
     * @bodyParam priority_id integer nullable The priority of the task. Must exist in the `priorities` table. Example: 1
     * @bodyParam start_date string|null optional The start date of the task in the format specified in the general settings. Example: 2024-07-20
     * @bodyParam due_date string|null optional The due date of the task in the format specified in the general settings. Example: 2024-08-20
     * @bodyParam description string nullable A description of the task. Example: Updated task description.
     * @bodyParam note string nullable Additional notes about the task. Example: Needs immediate attention.
     * @bodyParam user_id array nullable An array of user IDs to be assigned to the task. Example: [2, 3]
     * @bodyParam clientCanDiscuss string optional Indicates if the client can participate in task discussions. Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user; otherwise, it will be considered current value by default. The value should be 'on' to allow client participation. Example: on
     *
     * @response 200 {
     * "error": false,
     * "message": "Task updated successfully.",
     * "id": 280,
     * "parent_id": "420",
     * "parent_type": "project",
     * "data": {
     *   "id": 280,
     *   "workspace_id": 6,
     *   "title": "Res Test",
     *   "status": "Default",
     *   "priority": "Default",
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
     *       "id": 173,
     *       "first_name": "666",
     *       "last_name": "666",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     *     }
     *   ],
     *   "start_date": "07-08-2024",
     *   "due_date": "07-08-2024",
     *   "project": {
     *     "id": 420,
     *     "title": "Updated Project Title"
     *   },
     *   "description": "Test Desc",
     *   "note": "Test Note",
     *   "created_at": "07-08-2024 13:02:52",
     *   "updated_at": "07-08-2024 13:02:52"
     * }
     *
     * }
     * @response 422 {
     *  "error": true,
     *  "message": "Validation errors occurred",
     *  "errors": {
     *    "id": ["The selected id is invalid."],
     *    "title": ["The title field is required."],
     *    "status_id": ["The selected status_id is invalid."],
     *    ...
     *  }
     * }
     * @response 500 {
     *  "error": true,
     *  "message": "An error occurred while updating the task."
     * }
     */
    public function update(Request $request)
    {
        $isApi = request()->get('isApi', false);
        if ($request->input('priority_id') == 0) {
            $request->merge(['priority_id' => null]);
        }
        $rules = [
            'id' => 'required|exists:tasks,id',
            'title' => 'required',
            'status_id' => 'required|exists:statuses,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'start_date' => [
                'nullable',
                function ($attribute, $value, $fail) use ($isApi) {
                    $endDate = request()->input('due_date');
                    $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);
                    if (!empty($errors['start_date'])) {
                        foreach ($errors['start_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'due_date' => [
                'nullable',
                function ($attribute, $value, $fail) use ($isApi) {
                    $startDate = request()->input('start_date');
                    $errors = validate_date_format_and_order($startDate, $value, $isApi ? 'Y-m-d' : null, endDateKey: 'due_date');
                    if (!empty($errors['due_date'])) {
                        foreach ($errors['due_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'description' => 'nullable|string',
            'note' => 'nullable|string',
            'user_id' => 'nullable|array',
            'user_id.*' => 'exists:users,id',
            // Remider Tasks
            'enable_reminder' => 'nullable|in:on', // Validation for reminder toggle
            'frequency_type' => 'nullable|in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|between:1,7',
            'day_of_month' => 'nullable|integer|between:1,31',
            'time_of_day' => 'nullable|date_format:H:i',
            // Recurring task validation rules
            'enable_recurring_task' => 'nullable|in:on,off',
            'recurrence_frequency' => 'nullable|in:daily,weekly,monthly,yearly',
            'recurrence_day_of_week' => 'nullable|integer|min:1|max:7',
            'recurrence_day_of_month' => 'nullable|integer|min:1|max:31',
            'recurrence_month_of_year' => 'nullable|integer|min:1|max:12',
            'recurrence_starts_from' => 'nullable|date|after_or_equal:today',
            'recurrence_occurrences' => 'nullable|integer|min:1',
            'billing_type' => 'nullable|in:none,billable,non-billable',
            'completion_percentage' => ['nullable', 'integer', 'min:0', 'max:100', 'in:0,10,20,30,40,50,60,70,80,90,100'],
            'task_list_id' => 'nullable|exists:task_lists,id',
        ];
        $messages = [
            'status_id.required' => 'The status field is required.'
        ];
        try {
            $request->validate($rules, $messages);
            $status = Status::findOrFail($request->input('status_id'));
            $id = $request->input('id');
            $task = Task::findOrFail($id);
            $currentStatusId = $task->status_id;
            // Check if the status has changed
            if ($currentStatusId != $request->input('status_id')) {
                $status = Status::findOrFail($request->input('status_id'));
                if (!canSetStatus($status)) {
                    return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
                }
                $oldStatus = Status::findOrFail($currentStatusId);
                $task->statusTimelines()->create([
                    'status' => $status->title,
                    'new_color' => $status->color,
                    'previous_status' => $oldStatus->title,
                    'old_color' => $oldStatus->color,
                    'changed_at' => now()
                ]);
            }
            $formFieldsToUpdate = [
                'title' => $request->input('title'),
                'status_id' => $request->input('status_id'),
                'priority_id' => $request->input('priority_id'),
                'description' => $request->input('description'),
                'note' => $request->input('note'),
                'billing_type' => $request->input('billing_type', 'non-billable'),
                'completion_percentage' => $request->input('completion_percentage', 0),
                'task_list_id' => $request->input('task_list_id'),
            ];
            // Handle start_date
            if ($request->filled('start_date')) {
                $formFieldsToUpdate['start_date'] = format_date($request->input('start_date'), false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            } else {
                $formFieldsToUpdate['start_date'] = null;
            }
            // Handle due_date
            if ($request->filled('due_date')) {
                $formFieldsToUpdate['due_date'] = format_date($request->input('due_date'), false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            } else {
                $formFieldsToUpdate['due_date'] = null;
            }
            $clientCanDiscuss = isAdminOrHasAllDataAccess()
                ? ($request->input('clientCanDiscuss') == 'on' ? 1 : 0)
                : $task->client_can_discuss;
            $formFieldsToUpdate['client_can_discuss'] = $clientCanDiscuss;
            $userIds = $request->input('user_id', []);
            $task->update($formFieldsToUpdate);
            // Check Task Reminder is On than add the reminder
            if ($request->input('enable_reminder') === 'on') {
                // Check if reminder exists
                $reminder = $task->reminders()->first();
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
                    $task->reminders()->create([
                        'frequency_type' => $request->input('frequency_type'),
                        'day_of_week' => $request->input('frequency_type') === 'weekly' ? $request->input('day_of_week') : null,
                        'day_of_month' => $request->input('frequency_type') === 'monthly' ? $request->input('day_of_month') : null,
                        'time_of_day' => $request->input('time_of_day'),
                        'is_active' => 1
                    ]);
                }
            } else {
                // If reminder is turned off, either delete or deactivate the reminder
                $reminder = $task->reminders()->first();
                if ($reminder) {
                    // Deactivate the reminder
                    $reminder->update(['is_active' => 0]);
                }
            }
            // Handle Recurring Task
            $enableRecurringTask = $request->input('enable_recurring_task') === 'on';
            $recurringTaskData = [
                'frequency' => $request->input('recurrence_frequency'),
                'day_of_week' => $request->input('recurrence_day_of_week'),
                'day_of_month' => $request->input('recurrence_day_of_month'),
                'month_of_year' => $request->input('recurrence_month_of_year'),
                'starts_from' => $request->input('recurrence_starts_from'),
                'number_of_occurrences' => $request->input('recurrence_occurrences'),
            ];
            // Update or create recurring task
            if ($enableRecurringTask) {
                if ($task->recurringTask) {
                    $task->recurringTask->update($recurringTaskData);
                } else {
                    $task->recurringTask()->create($recurringTaskData);
                }
            } elseif ($task->recurringTask) {
                // Delete existing recurring task if disabled
                $task->recurringTask->delete();
            }
            // Get the current users associated with the task
            $currentUsers = $task->users->pluck('id')->toArray();
            $currentClients = $task->project->clients->pluck('id')->toArray();
            // Sync the users for the task
            $task->users()->sync($userIds);
            // Get the new users associated with the task
            $newUsers = array_diff($userIds, $currentUsers);
            // Prepare notification data for new users


            if ($request->has('custom_fields')) {
                foreach ($request->custom_fields as $field_id => $value) {
                    // Handle checkboxes (arrays)
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }

                    // Find existing custom field value or create new
                    $fieldValue = $task->customFields()
                        ->where('custom_field_id', $field_id)
                        ->first();

                    if ($fieldValue) {
                        $fieldValue->update(['value' => $value]);
                    } else {
                        $task->customFields()->create([
                            'custom_field_id' => $field_id,
                            'value' => $value
                        ]);
                    }
                }
            }


            $notification_data = [
                'type' => 'task',
                'type_id' => $id,
                'type_title' => $task->title,
                'access_url' => 'tasks/information/' . $task->id,
                'action' => 'assigned'
            ];
            // Notify only the new users
            $recipients = array_map(function ($userId) {
                return 'u_' . $userId;
            }, $newUsers);
            // Process notifications for new users
            processNotifications($notification_data, $recipients);
            if ($currentStatusId != $request->input('status_id')) {
                $currentStatus = Status::findOrFail($currentStatusId);
                $newStatus = Status::findOrFail($request->input('status_id'));
                $notification_data = [
                    'type' => 'task_status_updation',
                    'type_id' => $id,
                    'type_title' => $task->title,
                    'updater_first_name' => $this->user->first_name,
                    'updater_last_name' => $this->user->last_name,
                    'old_status' => $currentStatus->title,
                    'new_status' => $newStatus->title,
                    'access_url' => 'tasks/information/' . $id,
                    'action' => 'status_updated'
                ];
                $currentRecipients = array_merge(
                    array_map(function ($userId) {
                        return 'u_' . $userId;
                    }, $currentUsers),
                    array_map(function ($clientId) {
                        return 'c_' . $clientId;
                    }, $currentClients)
                );
                processNotifications($notification_data, $currentRecipients);
            }
            $task = $task->fresh();
            return formatApiResponse(
                false,
                'Task updated successfully.',
                [
                    'id' => $task->id,
                    'parent_id' => $task->project->id,
                    'parent_type' => 'project',
                    'data' => formatTask($task)
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the task.' . $e->getMessage()
            ], 500);
        }
    }
    public function updateTaskDates(Request $request)
    {
        $isApi = $request->get('isApi', false);
        // Validation rules for start and end dates
        $rules = [
            'id' => 'required|exists:tasks,id',
            'start_date' => [
                'required',
                function ($attribute, $value, $fail) {
                    $endDate = request()->input('due_date');
                    $errors = validate_date_format_and_order($value, $endDate);
                    if (!empty($errors['start_date'])) {
                        foreach ($errors['start_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'due_date' => [
                'required',
                function ($attribute, $value, $fail) {
                    $startDate = request()->input('start_date');
                    $errors = validate_date_format_and_order($startDate, $value, endDateKey: 'due_date');
                    if (!empty($errors['due_date'])) {
                        foreach ($errors['due_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
        ];
        try {
            // Validate the request data
            $request->validate($rules);
            // Find the task to be updated
            $task = Task::findOrFail($request->input('id'));
            // Update start and due dates
            $task->start_date = format_date($request->input('start_date'), false, app('php_date_format'), 'Y-m-d');
            $task->due_date = format_date($request->input('due_date'), false, app('php_date_format'), 'Y-m-d');
            // Save the updated task
            $task->save();
            return formatApiResponse(
                false,
                'Updated successfully.',
                [
                    'id' => $task->id,
                    'parent_id' => $task->project->id,
                    'parent_type' => 'project',
                    'data' => formatTask($task)
                ]
            );
        } catch (ValidationException $e) {
            // Handle validation errors
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating task dates.',
            ], 500);
        }
    }
    /**
     * Remove the specified task.
     *
     * This endpoint deletes a task based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Task Management
     *
     * @urlParam id int required The ID of the task to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Task deleted successfully.",
     *   "id": "262",
     *   "title": "From API",
     *   "parent_id": 377,
     *   "parent_type": "project",
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Task not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the task."
     * }
     */
    public function destroy($id)
    {
        $task = Task::with('comments.attachments')->find($id);
        if ($task) {
            $response = DeletionService::delete(Task::class, $id, 'Task');
            $responseData = json_decode($response->getContent(), true);
            if ($responseData['error']) {
                // Handle error response
                return response()->json($responseData);
            }
            // Get all comments before deletion
            $comments = $task->comments;
            // Delete all files using public disk
            $comments->each(function ($comment) {
                $comment->attachments->each(function ($attachment) {
                    Storage::disk('public')->delete($attachment->file_path);
                    $attachment->delete();
                });
            });
            $task->favorites()->delete();
            // Delete all pinned records associated with this task
            $task->pinned()->delete();
            // Delete comments
            $task->comments()->forceDelete();
            $task->notificationsForTask()->delete();
            return formatApiResponse(
                false,
                'Task deleted successfully.',
                [
                    'id' => $id,
                    'title' => $task->title,
                    'parent_id' => $task->project_id,
                    'parent_type' => 'project',
                    'data' => []
                ]
            );
        } else {
            return formatApiResponse(
                true,
                'Task not found.',
                []
            );
        }
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:tasks,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);
        $ids = $validatedData['ids'];
        $deletedTasks = [];
        $deletedTaskTitles = [];
        $parentIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $task = Task::find($id);
            if ($task) {
                $deletedTaskTitles[] = $task->title;
                $comments = $task->comments()->with('attachments')->get();
                $comments->each(function ($comment) {
                    $comment->attachments->each(function ($attachment) {
                        Storage::disk('public')->delete($attachment->file_path);
                        $attachment->delete();
                    });
                });
                $task->favorites()->delete();
                // Delete all pinned records associated with this task
                $task->pinned()->delete();
                $task->comments()->forceDelete();
                $task->notificationsForTask()->delete();
                DeletionService::delete(Task::class, $id, 'Task');
                $deletedTasks[] = $id;
                $parentIds[] = $task->project_id;
            }
        }
        return response()->json(['error' => false, 'message' => 'Task(s) deleted successfully.', 'id' => $deletedTasks, 'titles' => $deletedTaskTitles, 'parent_id' => $parentIds, 'parent_type' => 'project']);
    }
    public function list(Request $request, $id = '')
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status_ids = request('status_ids', []);
        $priority_ids = request('priority_ids', []);
        $user_ids = request('user_ids', []);
        $client_ids = request('client_ids', []);
        $project_ids = request('project_ids', []);
        $date_between_from = request('task_date_between_from') ?: "";
        $date_between_to = request('task_date_between_to') ?: "";
        $start_date_from = (request('task_start_date_from')) ? trim(request('task_start_date_from')) : "";
        $start_date_to = (request('task_start_date_to')) ? trim(request('task_start_date_to')) : "";
        $end_date_from = (request('task_end_date_from')) ? trim(request('task_end_date_from')) : "";
        $end_date_to = (request('task_end_date_to')) ? trim(request('task_end_date_to')) : "";
        $is_favorites = (request('is_favorites')) ? request('is_favorites') : "";
        $task_parent_id = (request('task_parent_id')) ? request('task_parent_id') : "";
        $where = [];
        if ($id) {
            $id = explode('_', $id);
            $belongs_to = $id[0];
            $belongs_to_id = $id[1];
            if ($belongs_to == 'project') {
                $project = Project::find($belongs_to_id);
                $tasks = $project->tasks();
            } else {
                $userOrClient = $belongs_to == 'user' ? User::find($belongs_to_id) : Client::find($belongs_to_id);
                $tasks = isAdminOrHasAllDataAccess($belongs_to, $belongs_to_id) ? $this->workspace->tasks() : $userOrClient->tasks();
            }
        } else {
            $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks() : $this->user->tasks();
        }
        if (!empty($user_ids)) {
            $tasks = $tasks->whereHas('users', function ($query) use ($user_ids) {
                $query->whereIn('users.id', $user_ids);
            });
        }
        if (!empty($client_ids)) {
            $tasks = $tasks->whereHas('project', function ($query) use ($client_ids) {
                $query->whereHas('clients', function ($query) use ($client_ids) {
                    $query->whereIn('clients.id', $client_ids);
                });
            });
        }
        if (!empty($project_ids)) {
            $tasks->whereIn('project_id', $project_ids);
        }
        if (!empty($status_ids)) {
            $tasks->whereIn('status_id', $status_ids);
        }
        if (!empty($priority_ids)) {
            $tasks->whereIn('priority_id', $priority_ids);
        }
        if ($date_between_from && $date_between_to) {
            $tasks->where('start_date', '>=', $date_between_from)
                ->where('due_date', '<=', $date_between_to);
        }
        if ($start_date_from && $start_date_to) {
            $tasks->whereBetween('start_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $tasks->whereBetween('due_date', [$end_date_from, $end_date_to]);
        }
        if ($is_favorites) {
            $favoriteTaskIds = $this->user->favoriteTasks() // Use the favoriteTasks method in the User model
                ->pluck('favoritable_id') // Get the list of favorite task IDs
                ->toArray();
            $tasks->whereIn('tasks.id', $favoriteTaskIds); // Filter tasks to include only the favorite ones
        }
        if ($search) {
            $tasks = $tasks->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('tasks.id', 'like', '%' . $search . '%');
            });
        }
        // Apply where clause to $tasks
        $tasks = $tasks->where($where);
        if ($task_parent_id === "") {
            // Add whereNull condition for parent_id to get only parent tasks
            $tasks->whereNull('parent_id');
        } else {
            $tasks->where('parent_id', $task_parent_id);
        }
        // Count total tasks before pagination
        $totaltasks = $tasks->count();
        $canCreate = checkPermission('create_tasks');
        $canEdit = checkPermission('edit_tasks');
        $canDelete = checkPermission('delete_tasks');
        $statuses = Status::all();
        $priorities = Priority::all();
        $isHome = $request->query('from_home');
        $webGuard = Auth::guard('web')->check();
        $canManageProjects = checkPermission('manage_projects');
        // Paginate tasks and format them
        $tasks = $tasks->leftJoin('pinned', function ($join) {
            $join->on('pinned.pinnable_id', '=', 'tasks.id')
                ->where('pinned.pinnable_type', '=', Task::class);
        })
            ->select('tasks.*', 'pinned.id as pinned_id')  // Select tasks and alias pinned.id as pinned_id
            ->orderByDesc('pinned.id')  // Tasks that are pinned will appear first
            ->orderBy($sort, $order)  // Then order by other parameters (e.g., id, title)
            ->paginate(request('limit'))
            ->through(function ($task) use ($statuses, $priorities, $canEdit, $canDelete, $canCreate, $isHome, $webGuard, $canManageProjects) {
                $statusOptions = '';
                foreach ($statuses as $status) {
                    $disabled = canSetStatus($status)  ? '' : 'disabled';
                    $selected = $task->status_id == $status->id ? 'selected' : '';
                    $statusOptions .= "<option value='{$status->id}' class='badge bg-label-{$status->color}' {$selected} {$disabled}>{$status->title}</option>";
                }
                $priorityOptions = "<option value='' class='badge bg-label-secondary'>-</option>";
                foreach ($priorities as $priority) {
                    $selectedPriority = $task->priority_id == $priority->id ? 'selected' : '';
                    $priorityOptions .= "<option value='{$priority->id}' class='badge bg-label-{$priority->color}' {$selectedPriority}>{$priority->title}</option>";
                }
                $actions = '';
                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-task" data-id="' . $task->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }
                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $task->id . '" data-type="tasks" data-table="task_table" data-reload="' . ($isHome ? 'true' : '') . '">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }
                if ($canCreate) {
                    $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $task->id . '" data-title="' . $task->title . '" data-type="tasks" data-table="task_table" data-reload="' . ($isHome ? 'true' : '') . '" title="' . get_label('duplicate', 'Duplicate') . '">' .
                        '<i class="bx bx-copy text-warning mx-2"></i>' .
                        '</a>';
                }
                $actions .= '<a href="javascript:void(0);" class="quick-view" data-id="' . $task->id . '" title="' . get_label('quick_view', 'Quick View') . '">' .
                    '<i class="bx bx-info-circle mx-3"></i>' .
                    '</a>';
                $actions = $actions ?: '-';
                $userHtml = '';
                if (!empty($task->users) && count($task->users) > 0) {
                    $userHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($task->users as $user) {
                        $userHtml .= "<li class='avatar avatar-sm pull-up'><a href='" . url("/users/profile/{$user->id}") . "' title='{$user->first_name} {$user->last_name}'><img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    if ($canEdit) {
                        $userHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="' . $task->id . '"><span class="bx bx-edit"></span></a></li>';
                    }
                    $userHtml .= '</ul>';
                } else {
                    $userHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                    if ($canEdit) {
                        $userHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="' . $task->id . '">' .
                            '<span class="bx bx-edit"></span>' .
                            '</a>';
                    }
                }
                $clientHtml = '';
                if (!empty($task->project->clients) && count($task->project->clients) > 0) {
                    $clientHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($task->project->clients as $client) {
                        $clientHtml .= "<li class='avatar avatar-sm pull-up'><a href='" . url("/clients/profile/{$client->id}") . "' title='{$client->first_name} {$client->last_name}'><img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    $clientHtml .= '</ul>';
                } else {
                    $clientHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                }
                $isFavorite = getFavoriteStatus($task->id, \App\Models\Task::class);
                $isFavoriteProject = getFavoriteStatus($task->project->id);
                $isPinned = getPinnedStatus($task->id, \App\Models\Task::class);
                return [
                    'id' => $task->id,
                    'title' => "<a href='" . url("/tasks/information/{$task->id}") . "'><strong>{$task->title}</strong></a> <a href='javascript:void(0);' class='ms-2'>
                            <i class='bx " . ($isFavorite ? 'bxs' : 'bx') . "-star favorite-icon text-warning' data-favorite='{$isFavorite}' data-id='{$task->id}' data-type='tasks' title='" . ($isFavorite ? get_label('remove_favorite', 'Click to remove from favorite') : get_label('add_favorite', 'Click to mark as favorite')) . "'></i>
                        </a><a href='javascript:void(0);' class='ms-2'>
                <i class='bx " . ($isPinned ? 'bxs' : 'bx') . "-pin pinned-icon text-success' data-pinned='{$isPinned}' data-id='{$task->id}' data-require_reload='0' data-table='task_table' data-type='tasks' title='" . ($isPinned ? get_label('click_unpin', 'Click to Unpin') : get_label('click_pin', 'Click to Pin')) . "'></i>
            </a>" . ($webGuard || $task->client_can_discuss ?
                        "<a href='" . route('tasks.info', ['id' => $task->id]) . "#navs-top-discussions' class='ms-2'>
                                <i class='bx bx-message-rounded-dots text-danger' data-bs-toggle='tooltip' data-bs-placement='right' title='" . get_label('discussions', 'Discussions') . "'></i>
                            </a>"
                        : ""),
                    'project_id' => ($canManageProjects
                        ? "<a href='" . url("/projects/information/{$task->project->id}") . "'>
        <strong>" . $task->project->title . "</strong>
       </a>"
                        : "<strong>" . $task->project->title . "</strong>"
                    ) . "
    <a href='javascript:void(0);' class='mx-2'>
        <i class='bx " . ($isFavoriteProject ? 'bxs' : 'bx') . "-star favorite-icon text-warning'
           data-favorite='{$isFavoriteProject}'
           data-id='{$task->project->id}'
           title='" . ($isFavoriteProject
                        ? get_label('remove_favorite', 'Click to remove from favorite')
                        : get_label('add_favorite', 'Click to mark as favorite')) . "'>
        </i>
    </a>",
                    'users' => $userHtml,
                    'clients' => $clientHtml,
                    'start_date' => format_date($task->start_date),
                    'due_date' => format_date($task->due_date),
                    'status_id' => "<div class='d-flex align-items-center'><select class='form-select form-select-sm select-bg-label-{$task->status->color} fixed-width-select' id='statusSelect' data-id='{$task->id}' data-original-status-id='{$task->status->id}' data-original-color-class='select-bg-label-{$task->status->color}' data-type='task'" . ($isHome ? " data-reload='true'" : "") . ">{$statusOptions}</select>" . ($task->note ?
                        "<i class='bx bx-notepad ms-2 text-primary' title='{$task->note}'></i>"
                        : "") . "</div>",
                    'priority_id' => "<select class='form-select form-select-sm select-bg-label-" . ($task->priority ? $task->priority->color : 'secondary') . "' id='prioritySelect' data-id='{$task->id}' data-original-priority-id='" . ($task->priority ? $task->priority->id : '') . "' data-original-color-class='select-bg-label-" . ($task->priority ? $task->priority->color : 'secondary') . "' data-type='task'>{$priorityOptions}</select>",
                    'created_at' => format_date($task->created_at, true),
                    'updated_at' => format_date($task->updated_at, true),
                    'actions' => $actions
                ];
            });
        // Return JSON response with formatted tasks and total count
        return response()->json([
            "rows" => $tasks->items(),
            "total" => $totaltasks,
        ]);
    }
    /**
     * List or search tasks.
     *
     * This endpoint retrieves a list of tasks based on various filters. The user must be authenticated to perform this action. The request allows filtering by multiple statuses, users, clients, projects, date ranges, and other parameters.
     *
     * @authenticated
     *
     * @group Task Management
     *
     * @urlParam id int optional The ID of the task to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter tasks by title or id. Example: Task
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, title, project, status, priority, start_date, due_date, created_at, and updated_at. Example: title
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam status_ids array optional An array of status IDs to filter tasks by. Example: [2, 3]
     * @queryParam user_ids array optional An array of user IDs to filter tasks by. Example: [1, 2, 3]
     * @queryParam client_ids array optional An array of client IDs to filter tasks by. Example: [5, 6]
     * @queryParam priority_ids array optional An array of priority IDs to filter tasks by. Example: [1, 2]
     * @queryParam project_ids array optional An array of project IDs to filter tasks by. Example: [1, 2]
     * @queryParam task_start_date_from string optional The start date range's start in YYYY-MM-DD format. Example: 2024-01-01
     * @queryParam task_start_date_to string optional The start date range's end in YYYY-MM-DD format. Example: 2024-12-31
     * @queryParam task_end_date_from string optional The end date range's start in YYYY-MM-DD format. Example: 2024-01-01
     * @queryParam task_end_date_to string optional The end date range's end in YYYY-MM-DD format. Example: 2024-12-31
     * @queryParam is_favorites boolean optional Filter projects marked as favorites. Example: true
     * @queryParam limit int optional The number of tasks per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Tasks retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 268,
     *       "workspace_id": 6,
     *       "title": "sdff",
     *       "status": "Default",
     *       "priority": "Default",
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
     *           "id": 102,
     *           "first_name": "Test",
     *           "last_name": "Client",
     *           "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     *         }
     *       ],
     *       "start_date": "23-07-2024",
     *       "due_date": "24-07-2024",
     *       "project": {
     *         "id": 379,
     *         "title": "From API"
     *       },
     *       "description": "<p>Test Desc</p>",
     *       "note": "Test note",
     *       "created_at": "23-07-2024 17:50:09",
     *       "updated_at": "23-07-2024 19:08:16"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Task not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Tasks not found",
     *   "total": 0,
     *   "data": []
     * }
     */
    public function apiList(Request $request, $id = '')
    {
        // Validation rules for multi-select filters
        $validator = Validator::make($request->all(), [
            'user_ids' => 'array',
            'user_ids.*' => 'integer|exists:users,id',
            'client_ids' => 'array',
            'client_ids.*' => 'integer|exists:clients,id',
            'priority_ids' => 'array',
            'priority_ids.*' => 'integer|exists:priorities,id',
            'project_ids' => 'array',
            'project_ids.*' => 'integer|exists:projects,id',
            'status_ids' => 'array',
            'status_ids.*' => 'integer|exists:statuses,id',
        ]);
        // If validation fails, return a response
        if ($validator->fails()) {
            return formatApiValidationError($request->is('api/*'), $validator->errors());
        }
        $search = $request->input('search');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $status_ids = $request->input('status_ids', []);
        $priority_ids = $request->input('priority_ids', []);
        $user_ids = $request->input('user_ids', []);
        $client_ids = $request->input('client_ids', []);
        $project_ids = $request->input('project_ids', []);
        $start_date_from = $request->input('task_start_date_from', '');
        $start_date_to = $request->input('task_start_date_to', '');
        $end_date_from = $request->input('task_end_date_from', '');
        $end_date_to = $request->input('task_end_date_to', '');
        $is_favorites = $request->input('is_favorites', '');
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $task_parent_id = (request('task_parent_id')) ? request('task_parent_id') : "";
        if ($id) {
            $task = Task::with('reminders', 'recurringTask')->find($id);

            if (!$task) {
                return formatApiResponse(
                    false,
                    'Task not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            } else {
                return formatApiResponse(
                    false,
                    'Task retrieved successfully',
                    [
                        'total' => 1,
                        'data' => [formatTask($task)]
                    ]
                );
            }
        } else {
            $tasksQuery = isAdminOrHasAllDataAccess() ? $this->workspace->tasks() : $this->user->tasks();

            // Multi-select filters
            if (!empty($user_ids)) {
                $taskIds = DB::table('task_user')->whereIn('user_id', $user_ids)->pluck('task_id')->toArray();
                $tasksQuery = $tasksQuery->whereIn('tasks.id', $taskIds);
            }
            if (!empty($client_ids)) {
                $projectIds = DB::table('client_project')->whereIn('client_id', $client_ids)->pluck('project_id')->toArray();
                $tasksQuery = $tasksQuery->whereIn('project_id', $projectIds);
            }
            if (!empty($project_ids)) {
                $tasksQuery->whereIn('project_id', $project_ids);
            }
            if (!empty($status_ids)) {
                $tasksQuery->whereIn('status_id', $status_ids);
            }
            if (!empty($priority_ids)) {
                $tasksQuery->whereIn('priority_id', $priority_ids);
            }
            if ($start_date_from && $start_date_to) {
                $tasksQuery->whereBetween('start_date', [$start_date_from, $start_date_to]);
            }
            if ($end_date_from && $end_date_to) {
                $tasksQuery->whereBetween('due_date', [$end_date_from, $end_date_to]);
            }
            if ($start_date_from) {
                $tasksQuery->where('start_date', '>=', $start_date_from);
            }
            if ($end_date_to) {
                $tasksQuery->where('due_date', '<=', $end_date_to);
            }
            if ($is_favorites) {
                $favoriteTaskIds = $this->user->favorites()
                    ->where('favoritable_type', \App\Models\Task::class)
                    ->pluck('favoritable_id')
                    ->toArray();
                $tasksQuery->whereIn('tasks.id', $favoriteTaskIds);
            }
            if ($search) {
                $tasksQuery->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('tasks.id', 'like', '%' . $search . '%');
                });
            }
            if ($task_parent_id === "") {
                // Add whereNull condition for parent_id to get only parent tasks
                $tasksQuery->whereNull('parent_id');
            } else {
                $tasksQuery->where('parent_id', $task_parent_id);
            }
            $total = $tasksQuery->count(); // Get total count before applying offset and limit
            $tasks = $tasksQuery->leftJoin('pinned', function ($join) {
                $join->on('pinned.pinnable_id', '=', 'tasks.id')
                    ->where('pinned.pinnable_type', '=', Task::class);
            })
                ->select('tasks.*', 'pinned.id as pinned_id')  // Select tasks and alias pinned.id as pinned_id
                ->orderByDesc('pinned.id')  // Tasks that are pinned will appear first
                ->orderBy($sort, $order)  // Then order by other parameters (e.g., id or title)
                ->skip($offset)  // Apply the offset
                ->take($limit)  // Apply the limit
                ->get();
            if ($tasks->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Tasks not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $tasks->map(function ($task) {
                return formatTask($task);
            });
            return formatApiResponse(
                false,
                'Tasks retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data,
                ]
            );
        }
    }
    public function dragula(Request $request, $id = '')
    {
        $project = (object)[];
        $isFavorites = request()->get('favorite', false);

        if ($id) {
            $project = Project::findOrFail($id);
            if (isAdminOrHasAllDataAccess()) {
                $tasksQuery = $project->tasks();
            } else {
                $taskIds = $this->user->project_tasks($id)->pluck('id')->toArray();
                $tasksQuery = \App\Models\Task::whereIn('tasks.id', $taskIds);
            }
        } else {
            if (isAdminOrHasAllDataAccess()) {
                $tasksQuery = $this->workspace->tasks();
            } else {
                $taskIds = $this->user->tasks()->pluck('tasks.id')->toArray();
                $tasksQuery = \App\Models\Task::whereIn('tasks.id', $taskIds);
            }
        }

        // Apply status filter if present
        if (request()->has('status')) {
            $tasksQuery->where('tasks.status_id', request()->status);
        }

        // Apply project filter if present
        if (request()->has('project')) {
            $project = Project::findOrFail(request()->project);
            $tasksQuery->where('tasks.project_id', request()->project);
        }

        // Filter favorite tasks if required
        if ($isFavorites) {
            $favoriteTaskIds = $this->user->favoriteTasks()
                ->pluck('favoritable_id')
                ->toArray();
            $tasksQuery->whereIn('tasks.id', $favoriteTaskIds);
        }

        // Join with the pinned table and order by pinned status
        $tasksQuery->leftJoin('pinned', function ($join) {
            $join->on('pinned.pinnable_id', '=', 'tasks.id')
                ->where('pinned.pinnable_type', '=', Task::class);
        })
            ->selectRaw('tasks.*, tasks.id as task_id, pinned.id as pinned_id') //
            ->orderByDesc('pinned.id');

        // Get the total count and the tasks
        $total_tasks = $tasksQuery->count();
        $customFields = CustomField::where('module', 'task')->get();
        $tasks = $tasksQuery->get();

        return view('tasks.board_view', [
            'project' => $project,
            'tasks' => $tasks,
            'total_tasks' => $total_tasks,
            'is_favorites' => $isFavorites,
            'customFields' => $customFields
        ]);
    }

    public function updateStatus($id, $newStatus)
    {
        $status = Status::findOrFail($newStatus);
        if (canSetStatus($status)) {
            $task = Task::findOrFail($id);
            $current_status = $task->status->title;
            $task->status_id = $newStatus;
            if ($task->save()) {
                $task->refresh();
                $new_status = $task->status->title;
                $notification_data = [
                    'type' => 'task_status_updation',
                    'type_id' => $id,
                    'type_title' => $task->title,
                    'updater_first_name' => $this->user->first_name,
                    'updater_last_name' => $this->user->last_name,
                    'old_status' => $current_status,
                    'new_status' => $new_status,
                    'access_url' => 'tasks/information/' . $id,
                    'action' => 'status_updated'
                ];
                $userIds = $task->users->pluck('id')->toArray();
                $clientIds = $task->project->clients->pluck('id')->toArray();
                $recipients = array_merge(
                    array_map(function ($userId) {
                        return 'u_' . $userId;
                    }, $userIds),
                    array_map(function ($clientId) {
                        return 'c_' . $clientId;
                    }, $clientIds)
                );
                processNotifications($notification_data, $recipients);
                return response()->json(['error' => false, 'message' => 'Task status updated successfully.', 'id' => $id, 'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' updated task status from ' . trim($current_status) . ' to ' . trim($new_status)]);
            } else {
                return response()->json(['error' => true, 'message' => 'Task status couldn\'t updated.']);
            }
        } else {
            return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
        }
    }
    /**
     * Update the status of a task.
     *
     * This endpoint updates the status of a specified task. The user must be authenticated and have permission to set the new status. A notification will be sent to all users and clients associated with the task.
     *
     * @authenticated
     *
     * @group Task Management
     *
     * @urlParam id int required The ID of the task whose status is to be updated. Example: 1
     * @bodyParam statusId int required The ID of the new status to set for the task. Must exist in the `statuses` table. Example: 2
     * @bodyParam note string optional An optional note to attach to the task update. Example: Updated due to client request.
     *
     * @response 200 {
     * "error": false,
     * "message": "Status updated successfully.",
     * "id": "278",
     * "type": "task",
     * "activity_message": "Madhavan Vaidya updated task status from Ongoing to Completed",
     * "data": {
     * "id": 278,
     * "workspace_id": 6,
     * "title": "New Task",
     * "status": "Completed",
     * "priority": "dsfdsf",
     * "users": [
     * {
     * "id": 7,
     * "first_name": "Madhavan",
     * "last_name": "Vaidya",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     * }
     * ],
     * "clients": [
     * {
     * "id": 173,
     * "first_name": "666",
     * "last_name": "666",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     * }
     * ],
     * "start_date": "20-08-2024",
     * "due_date": null,
     * "project": {
     * "id": 419,
     * "title": "Updated Project Title"
     * },
     * "description": "This is a detailed description of the task.",
     * "note": null,
     * "created_at": "06-08-2024 11:42:13",
     * "updated_at": "12-08-2024 15:18:09"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The selected id is invalid."
     *     ],
     *     "statusId": [
     *       "The selected status id is invalid."
     *     ]
     *   }
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "You are not authorized to set this status."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Status couldn't be updated."
     * }
     */
    //For status change from dropdown
    public function update_status(Request $request, $id = null)
    {
        $isApi = request()->get('isApi', false);
        if ($id) {
            $request->merge(['id' => $id]);
        }
        $rules = [
            'id' => 'required|exists:tasks,id',
            'statusId' => 'required|exists:statuses,id'
        ];
        try {
            $request->validate($rules);
            $id = $request->id;
            $statusId = $request->statusId;
            $status = Status::findOrFail($statusId);
            if (canSetStatus($status)) {
                $task = Task::findOrFail($id);
                if ($task->status->id != $statusId) {
                    $currentStatus = $task->status->title;
                    $oldStatus = $task->status_id;
                    $task->status_id = $statusId;
                    $task->note = $request->note;
                    $oldStatus = Status::findOrFail($oldStatus);
                    $newStatus = Status::findOrFail($statusId);
                    $task->statusTimelines()->create([
                        'status' => $newStatus->title,
                        'new_color' => $newStatus->color,
                        'previous_status' => $oldStatus->title,
                        'old_color' => $oldStatus->color,
                        'changed_at' => now(),
                    ]);
                    if ($task->save()) {
                        $task = $task->fresh();
                        $newStatus = $task->status->title;
                        $notification_data = [
                            'type' => 'task_status_updation',
                            'type_id' => $id,
                            'type_title' => $task->title,
                            'updater_first_name' => $this->user->first_name,
                            'updater_last_name' => $this->user->last_name,
                            'old_status' => $currentStatus,
                            'new_status' => $newStatus,
                            'access_url' => 'tasks/information/' . $id,
                            'action' => 'status_updated'
                        ];
                        $userIds = $task->users->pluck('id')->toArray();
                        $clientIds = $task->project->clients->pluck('id')->toArray();
                        $recipients = array_merge(
                            array_map(function ($userId) {
                                return 'u_' . $userId;
                            }, $userIds),
                            array_map(function ($clientId) {
                                return 'c_' . $clientId;
                            }, $clientIds)
                        );
                        processNotifications($notification_data, $recipients);
                        return formatApiResponse(
                            false,
                            'Status updated successfully.',
                            [
                                'id' => $id,
                                'type' => 'task',
                                'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' updated task status from ' . trim($currentStatus) . ' to ' . trim($newStatus),
                                'data' => formatTask($task)
                            ]
                        );
                    } else {
                        return response()->json(['error' => true, 'message' => 'Status couldn\'t updated.']);
                    }
                } else {
                    return response()->json(['error' => true, 'message' => 'No status change detected.']);
                }
            } else {
                return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
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
    public function duplicate($id)
    {
        // Define the related tables for this meeting
        $relatedTables = ['users']; // Include related tables as needed
        // Use the general duplicateRecord function
        $title = (request()->has('title') && !empty(trim(request()->title))) ? request()->title : '';
        $duplicate = duplicateRecord(Task::class, $id, $relatedTables, $title);
        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Task duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Task duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Task duplicated successfully.', 'id' => $id, 'parent_id' => $duplicate->project->id, 'parent_type' => 'project']);
    }
    /**
     * Upload media files for a task.
     *
     * This endpoint allows authenticated users to upload media files and associate them with a specific task.
     *
     * @authenticated
     *
     * @group Task Media
     *
     * @bodyParam id int required The ID of the task where the media will be uploaded.
     * @bodyParam media_files[] file required An array of media files to upload. Max size is defined by system settings.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "File(s) uploaded successfully.",
     *   "id": [201, 202],
     *   "type": "media",
     *   "parent_type": "task",
     *   "parent_id": 15
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred.",
     *   "errors": {
     *     "id": ["The selected id is invalid."],
     *     "media_files": ["The media file must be a valid file."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred during file upload: [error details]"
     * }
     */

    public function upload_media(Request $request)
    {
        $maxFileSizeBytes = config('media-library.max_file_size');
        $maxFileSizeKb = (int) ($maxFileSizeBytes / 1024); // Convert bytes to KB

        try {
            $validatedData = $request->validate([
                'id' => 'required|integer|exists:tasks,id',
                'media_files.*' => 'required|file|max:' . $maxFileSizeKb
            ]);

            $mediaIds = [];

            if ($request->hasFile('media_files')) {
                $task = Task::findOrFail($validatedData['id']);
                $mediaFiles = $request->file('media_files');

                foreach ($mediaFiles as $mediaFile) {
                    $mediaItem = $task->addMedia($mediaFile)
                        ->sanitizingFileName(function ($fileName) {
                            $sanitizedFileName = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));
                            $uniqueId = time() . '_' . mt_rand(1000, 9999);
                            $extension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
                            $baseName = pathinfo($sanitizedFileName, PATHINFO_FILENAME);
                            return "{$baseName}-{$uniqueId}.{$extension}";
                        })
                        ->toMediaCollection('task-media');

                    $mediaIds[] = $mediaItem->id;
                }

                return response()->json([
                    'error' => false,
                    'message' => 'File(s) uploaded successfully.',
                    'id' => $mediaIds,
                    'type' => 'media',
                    'parent_type' => 'task',
                    'parent_id' => $task->id
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'No file(s) chosen.'
                ]);
            }
        } catch (ValidationException $e) {
            $isApi = request()->get('isApi', false);
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred during file upload: ' . $e->getMessage()
            ], 500);
        }
    }

    public function get_media($id)
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $task = Task::findOrFail($id);
        $media = $task->getMedia('task-media');
        if ($search) {
            $media = $media->filter(function ($mediaItem) use ($search) {
                return (
                    // Check if ID contains the search query
                    stripos($mediaItem->id, $search) !== false ||
                    // Check if file name contains the search query
                    stripos($mediaItem->file_name, $search) !== false ||
                    // Check if date created contains the search query
                    stripos($mediaItem->created_at->format('Y-m-d'), $search) !== false
                );
            });
        }
        $canDelete = checkPermission('delete_media');
        $formattedMedia = $media->map(function ($mediaItem) use ($canDelete) {
            // Check if the disk is public
            $isPublicDisk = $mediaItem->disk == 'public' ? 1 : 0;
            // Generate file URL based on disk visibility
            $fileUrl = $isPublicDisk
                ? asset('storage/task-media/' . $mediaItem->file_name)
                : $mediaItem->getFullUrl();
            $fileExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);
            // Check if file extension corresponds to an image type
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            $isImage = in_array(strtolower($fileExtension), $imageExtensions);
            if ($isImage) {
                $html = '<a href="' . $fileUrl . '" data-lightbox="task-media">';
                $html .= '<img src="' . $fileUrl . '" alt="' . $mediaItem->file_name . '" width="50">';
                $html .= '</a>';
            } else {
                $html = '<a href="' . $fileUrl . '" title=' . get_label('download', 'Download') . '>' . $mediaItem->file_name . '</a>';
            }
            $actions = '';
            $actions .= '<a href="' . $fileUrl . '" title="' . get_label('download', 'Download') . '" download>' .
                '<i class="bx bx-download bx-sm"></i>' .
                '</a>';
            if ($canDelete) {
                $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $mediaItem->id . '" data-type="task-media" data-table="task_media_table">' .
                    '<i class="bx bx-trash text-danger"></i>' .
                    '</button>';
            }
            $actions = $actions ?: '-';
            return [
                'id' => $mediaItem->id,
                'file' => $html,
                'file_name' => $mediaItem->file_name,
                'file_size' => formatSize($mediaItem->size),
                'created_at' => format_date($mediaItem->created_at, true),
                'updated_at' => format_date($mediaItem->updated_at, true),
                'actions' => $actions,
            ];
        });
        if ($order == 'asc') {
            $formattedMedia = $formattedMedia->sortBy($sort);
        } else {
            $formattedMedia = $formattedMedia->sortByDesc($sort);
        }
        return response()->json([
            'rows' => $formattedMedia->values()->toArray(),
            'total' => $formattedMedia->count(),
        ]);
    }

    /**
     * Get Task media files.
     *
     * This endpoint retrieves all media files associated with a specific project, including sorting and search capabilities.
     *
     * @authenticated
     *
     * @group Task Media
     *
     * @urlParam id int required The ID of the project whose media files are to be retrieved.
     * @queryParam search string optional A search query to filter media files by name, ID, or upload date.
     * @queryParam sort string optional The column to sort by (default: "id").
     * @queryParam order string optional The sorting order: "ASC" or "DESC" (default: "DESC").
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Media files retrieved successfully.",
     *   "rows": [
     *     {
     *       "id": 101,
     *       "file": "<a href='https://example.com/storage/task-media/image.jpg' data-lightbox='task-media'><img src='https://example.com/storage/project-media/image.jpg' alt='image.jpg' width='50'></a>",
     *       "file_name": "image.jpg",
     *       "file_size": "2 MB",
     *       "created_at": "2025-03-03",
     *       "updated_at": "2025-03-03",
     *
     *     }
     *   ],
     *   "total": 1
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Task not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Could not retrieve media files."
     * }
     */
    public function get_media_api($id)
    {
        try {
            $search = request('search');
            $sort = request('sort', 'id');
            $order = request('order', 'DESC');
            $task = Task::findOrFail($id);
            $media = $task->getMedia('task-media');
            if ($search) {
                $media = $media->filter(function ($mediaItem) use ($search) {
                    return (
                        stripos($mediaItem->id, $search) !== false ||
                        stripos($mediaItem->file_name, $search) !== false ||
                        stripos($mediaItem->created_at->format('Y-m-d'), $search) !== false
                    );
                });
            }
            $canDelete = checkPermission('delete_media');
            $formattedMedia = $media->map(function ($mediaItem) use ($canDelete) {
                $isPublicDisk = $mediaItem->disk == 'public' ? 1 : 0;
                $fileUrl = $isPublicDisk
                    ? asset('storage/task-media/' . $mediaItem->file_name)
                    : $mediaItem->getFullUrl();
                $fileExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                $isImage = in_array(strtolower($fileExtension), $imageExtensions);
                $previewUrl = $isImage ? $fileUrl : asset('storage/file-icon.png');

                return [
                    'id' => $mediaItem->id,
                    'file' => $fileUrl,
                    'preview' => $previewUrl,
                    'file_name' => $mediaItem->file_name,
                    'file_size' => formatSize($mediaItem->size),
                    'created_at' => format_date($mediaItem->created_at, to_format: 'Y-m-d'),
                    'updated_at' => format_date($mediaItem->updated_at, to_format: 'Y-m-d'),
                    'can_delete' => $canDelete,

                ];
            });
            $formattedMedia = $order === 'asc'
                ? $formattedMedia->sortBy($sort)
                : $formattedMedia->sortByDesc($sort);
            return response()->json([
                'error' => false,
                'message' => 'Media files retrieved successfully.',
                'data' => $formattedMedia->values()->toArray(),
                'total' => $formattedMedia->count(),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Task not found."
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "Could not retrieve media files."
            ], 500);
        }
    }
    /**
     * Delete a media file.
     *
     * This endpoint allows authenticated users to delete a specific media file associated with a task.
     *
     * @authenticated
     *
     * @group Task Media
     *
     * @urlParam mediaId int required The ID of the media file to delete.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "File deleted successfully.",
     *   "id": 301,
     *   "title": "document.pdf",
     *   "parent_id": 15,
     *   "type": "media",
     *   "parent_type": "task"
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "File not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the file."
     * }
     */

    public function delete_media($mediaId)
    {
        try {
            $mediaItem = Media::find($mediaId);

            if (!$mediaItem) {
                return response()->json([
                    'error' => true,
                    'message' => 'File not found.'
                ], 404);
            }

            // Delete the media file from storage
            $mediaItem->delete();

            return response()->json([
                'error' => false,
                'message' => 'File deleted successfully.',
                'id' => $mediaId,
                'title' => $mediaItem->file_name,
                'parent_id' => $mediaItem->model_id,
                'type' => 'media',
                'parent_type' => 'task'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while deleting the file.'
            ], 500);
        }
    }

    public function delete_multiple_media(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:media,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);
        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        $parentIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $media = Media::find($id);
            if ($media) {
                $deletedIds[] = $id;
                $deletedTitles[] = $media->file_name;
                $parentIds[] = $media->model_id;
                $media->delete();
            }
        }
        return response()->json(['error' => false, 'message' => 'Files(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'parent_id' => $parentIds, 'type' => 'media', 'parent_type' => 'task']);
    }
    /**
     * Update the priority of a task.
     *
     * This endpoint updates the priority of a specified task. The user must be authenticated and have permission to set the new priority.
     *
     * @authenticated
     *
     * @group Task Management
     *
     * @urlParam id int required The ID of the task whose priority is to be updated. Example: 1
     * @bodyParam priorityId int required The ID of the new priority to set for the task. Must exist in the `priorities` table. Example: 3
     *
     * @response 200 {
     * "error": false,
     * "message": "Priority updated successfully.",
     * "id": "278",
     * "type": "task",
     * "activity_message": "Madhavan Vaidya updated task priority from Medium to High",
     * "data": {
     * "id": 278,
     * "workspace_id": 6,
     * "title": "New Task",
     * "status": "Completed",
     * "priority": "High",
     * "users": [
     * {
     * "id": 7,
     * "first_name": "Madhavan",
     * "last_name": "Vaidya",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     * }
     * ],
     * "clients": [
     * {
     * "id": 173,
     * "first_name": "666",
     * "last_name": "666",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     * }
     * ],
     * "start_date": "20-08-2024",
     * "due_date": null,
     * "project": {
     * "id": 419,
     * "title": "Updated Project Title"
     * },
     * "description": "This is a detailed description of the task.",
     * "note": null,
     * "created_at": "06-08-2024 11:42:13",
     * "updated_at": "12-08-2024 15:40:41"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The selected id is invalid."
     *     ],
     *     "priorityId": [
     *       "The selected priorityId is invalid."
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
        if ($request->input('priorityId') == 0) {
            $request->merge(['priorityId' => null]);
        }
        $rules = [
            'id' => 'required|exists:tasks,id',
            'priorityId' => 'nullable|exists:priorities,id'
        ];
        try {
            $request->validate($rules);
            $id = $request->id;
            $priorityId = $request->priorityId;
            $task = Task::findOrFail($id);
            if ($task->priority_id != $priorityId) {
                $currentPriority = $task->priority ? $task->priority->title : '-';
                $task->priority_id = $priorityId;
                if ($task->save()) {
                    // Reload the task to get updated priority information
                    $task = $task->fresh();
                    $newPriority = $task->priority ? $task->priority->title : '-';
                    $message = trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' updated task priority from ' . trim($currentPriority) . ' to ' . trim($newPriority);
                    return formatApiResponse(
                        false,
                        'Priority updated successfully.',
                        [
                            'id' => $id,
                            'type' => 'task',
                            'activity_message' => $message,
                            'data' => formatTask($task)
                        ]
                    );
                } else {
                    return response()->json(['error' => true, 'message' => 'Priority couldn\'t updated.']);
                }
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
    public function saveViewPreference(Request $request)
    {
        $view = $request->input('view');
        $prefix = isClient() ? 'c_' : 'u_';
        UserClientPreference::updateOrCreate(
            ['user_id' => $prefix . $this->user->id, 'table_name' => 'tasks'],
            ['default_view' => $view]
        );
        return response()->json(['error' => false, 'message' => 'Default View Set Successfully.']);
    }
    public function calendar(Request $request, $id = null)
    {
        $project = (object)[];
        $isFavorites = request()->get('favorite', false);
        $customFields = CustomField::where('module', 'task')->get();
        if ($id) {
            $project = Project::findOrFail($id);
        }
        return view('tasks.calendar_view', ['project' => $project, 'is_favorites' => $isFavorites, 'customFields' => $customFields]);
    }
    public function get_calendar_data(Request $request)
    {
        $start = $request->query('start');
        $end = $request->query('end');
        $projectId = $request->query('projectId');
        $is_favorites = (request('is_favorites')) ? request('is_favorites') : "";
        if ($projectId) {
            $project = Project::find($projectId);
            if ($project) {
                // Fetch project-specific tasks
                $tasksQuery = isAdminOrHasAllDataAccess() ? $project->tasks() : $this->user->project_tasks($projectId);
            } else {
                // Fallback to workspace or user tasks if the project is not found
                $tasksQuery = isAdminOrHasAllDataAccess() ? $this->workspace->tasks() : $this->user->tasks();
            }
        } else {
            // If no projectId, fetch workspace or user tasks
            $tasksQuery = isAdminOrHasAllDataAccess() ? $this->workspace->tasks() : $this->user->tasks();
        }
        // Apply date range filter with grouping
        $tasksQuery->where(function ($query) use ($start, $end) {
            $query->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('due_date', [$start, $end]);
        });
        // Retrieve the tasks
        $tasks = $tasksQuery->get();
        if ($is_favorites) {
            $favoriteTaskIds = $this->user->favoriteTasks() // Use the favoriteTasks method in the User model
                ->pluck('favoritable_id') // Get the list of favorite task IDs
                ->toArray();
            $tasks->whereIn('tasks.id', $favoriteTaskIds); // Filter tasks to include only the favorite ones
        }
        // Format the tasks for FullCalendar
        $events = $tasks->map(function ($task) {
            $backgroundColor = '#007bff';
            // Set the background color based on the task status
            switch ($task->status->color) {
                case 'primary':
                    $backgroundColor = '#9bafff'; // Lighter primary blue
                    break;
                case 'success':
                    $backgroundColor = '#a0e4a3'; // Lighter green
                    break;
                case 'danger':
                    $backgroundColor = '#ff6b5c'; // Lighter red
                    break;
                case 'warning':
                    $backgroundColor = '#ffca66'; // Lighter yellow
                    break;
                case 'info':
                    $backgroundColor = '#6ed4f0'; // Lighter blue
                    break;
                case 'secondary':
                    $backgroundColor = '#aab0b8'; // Lighter grey
                    break;
                case 'dark':
                    $backgroundColor = '#4f5b67'; // Lighter dark grey
                    break;
                case 'light':
                    $backgroundColor = '#ffffff'; // Already light
                    break;
                default:
                    $backgroundColor = '#5ab0ff'; // Lighter default blue
            }
            $title = $task->title . ' : ' . format_date($task->start_date);
            if ($task->due_date != $task->start_date) {
                $title .= ' ' . get_label('to', 'to') . ' ' . format_date($task->due_date);
            }
            return [
                'id' => $task->id,
                'tasks_info_url' => route('tasks.info', ['id' => $task->id]),
                'title' => $title,
                'start' => $task->start_date,
                'end' => $task->due_date,
                'backgroundColor' => $backgroundColor,
                'borderColor' => '#ffffff',
                'textColor' => '#000000',
            ];
        });
        return response()->json($events);
    }
    /**
     * Add a comment with attachments.
     *
     * This endpoint allows authenticated users to add comments to a specific model (e.g., tasks, projects).
     * Users can also attach files and mention other users.
     *
     * @authenticated
     *
     * @group Task Comments
     *
     * @bodyParam model_type string required The type of model being commented on (e.g., "Task", "Project").
     * @bodyParam model_id int required The ID of the model being commented on.
     * @bodyParam content string required The comment text.
     * @bodyParam parent_id int optional The ID of the parent comment (for replies).
     * @bodyParam attachments[] file optional An array of files to attach to the comment. Supported formats: jpg, jpeg, png, pdf, xlsx, txt, docx (max size: 2MB).
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Comment Added Successfully",
     *   "comment": {
     *     "id": 45,
     *     "commentable_type": "App\\Models\\Task",
     *     "commentable_id": 438,
     *     "content": "This is a sample comment with a mention @JohnDoe",
     *     "user_id": 7,
     *     "parent_id": null,
     *     "created_at": "2 minutes ago",
     *     "attachments": [
     *       {
     *         "id": 1,
     *         "file_name": "document.pdf",
     *         "file_path": "comment_attachments/document.pdf",
     *         "file_type": "application/pdf"
     *       }
     *     ]
     *   },
     *   "user": {
     *     "id": 7,
     *     "name": "John Doe"
     *   }
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "content": ["Please enter a comment."]
     *   }
     * }
     *
     * @response 500 {
     *   "success": false,
     *   "message": "Comment could not be added."
     * }
     */
    public function comments(Request $request)
    {
        try {
            $maxFileSizeBytes = config('media-library.max_file_size');
            $maxFileSizeKb = (int) ($maxFileSizeBytes / 1024);
            $request->validate([
                'model_type' => 'required|string',
                'model_id' => 'required|integer',
                'content' => 'required|string',
                'parent_id' => 'nullable|integer|exists:comments,id',
                'attachments.*' => "file|max:$maxFileSizeKb"
            ], [
                'content.required' => 'Please enter a comment'
            ]);
            $fileValidationResponse = FileValidationHelper::validateFileUpload($request, 'attachments');
            if ($fileValidationResponse !== true) {
                return $fileValidationResponse;
            }
            list($processedContent, $mentionedUserIds, $mentionedClientIds) = replaceUserMentionsWithLinks($request->content);
            $comment = Comment::create([
                'commentable_type' => $request->model_type,
                'commentable_id' => $request->model_id,
                'content' => $processedContent,
                'commenter_id' => $this->user->id,
                'commenter_type' => get_class($this->user),
                'parent_id' => $request->parent_id,
            ]);
            // Create directory if it does not exist
            $directoryPath = storage_path('app/public/comment_attachments');
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }
            // Save attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = str_replace('public/', '', $file->store('public/comment_attachments'));
                    CommentAttachment::create([
                        'comment_id' => $comment->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                    ]);
                }
            }
            sendMentionNotification($comment, $mentionedUserIds, $this->workspace->id, $this->user->id, $mentionedClientIds);
            return response()->json([
                'success' => true,
                'message' => 'Comment Added Successfully',
                'comment' => $comment->load('attachments'),
                'user' => $comment->commenter,
                'created_at' => $comment->created_at->diffForHumans()
            ]);
        } catch (ValidationException $e) {
            $isApi = request()->get('isApi', false);
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Comment could not be added.'
            ], 500);
        }
    }
    /**
     * Get details of a specific comment.
     *
     * This endpoint retrieves the details of a specific comment, including any attachments.
     *
     * @authenticated
     *
     * @group Task Comments
     *
     * @urlParam id int required The ID of the comment to retrieve.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Comment retrieved successfully.",
     *   "comment": {
     *     "id": 45,
     *     "commentable_type": "App\\Models\\Task",
     *     "commentable_id": 438,
     *     "content": "This is a sample comment with a mention @JohnDoe",
     *     "user_id": 7,
     *     "parent_id": null,
     *     "created_at": "2025-03-03 14:00:00",
     *     "updated_at": "2025-03-03 16:00:00",
     *     "attachments": [
     *       {
     *         "id": 1,
     *         "file_name": "document.pdf",
     *         "file_path": "comment_attachments/document.pdf",
     *         "file_type": "application/pdf"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Comment not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Could not retrieve comment."
     * }
     */
    public function get_comment(Request $request, $id)
    {
        try {
            $comment = Comment::with('attachments')->findOrFail($id);
            return response()->json([
                'error' => false,
                'message' => 'Comment retrieved successfully.',
                'comment' => $comment,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Comment not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Could not retrieve comment.'
            ], 500);
        }
    }
    /**
     * Update a comment.
     *
     * This endpoint updates a specified comment. The user must be authenticated and have permission to modify the comment.
     *
     * @authenticated
     *
     * @group Task Comments
     *
     * @bodyParam comment_id int required The ID of the comment to be updated.
     * @bodyParam content string required The updated content of the comment.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Comment updated successfully.",
     *   "id": 45,
     *   "type": "task"
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "content": ["Please enter a comment."]
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Comment not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Comment couldn't be updated."
     * }
     */
    public function update_comment(Request $request)
    {
        try {
            $request->validate([
                'comment_id' => ['required', 'integer', 'exists:comments,id'],
                'content' => ['required', 'string'],
            ], [
                'content.required' => 'Please enter a comment'
            ]);
            list($processedContent, $mentionedUserIds, $mentionedClientIds) = replaceUserMentionsWithLinks($request->content);
            $comment = Comment::findOrFail($request->comment_id);
            $comment->content = $processedContent;
            if ($comment->save()) {
                sendMentionNotification($comment, $mentionedUserIds, $this->workspace->id, $this->user->id, $mentionedClientIds);
                return response()->json([
                    'error' => false,
                    'message' => 'Comment updated successfully.',
                    'id' => $comment->id,
                    'type' => 'task'
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Comment couldn\'t be updated.'
                ]);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Comment not found."
            ], 404);
        } catch (ValidationException $e) {
            $isApi = request()->get('isApi', false);
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "Comment couldn't be updated."
            ], 500);
        }
    }
    /**
     * Delete a comment.
     *
     * This endpoint deletes a specified comment and removes its attachments from storage.
     * The user must be authenticated and have permission to delete comments.
     *
     * @authenticated
     *
     * @group Task Comments
     *
     * @queryParam comment_id int required The ID of the comment to delete.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Comment deleted successfully.",
     *   "id": 45,
     *   "type": "task"
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "comment_id": ["The comment_id field is required."]
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Comment not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Comment couldn't be deleted."
     * }
     */
    public function destroy_comment(Request $request)
    {
        try {
            $request->validate([
                'comment_id' => ['required', 'integer', 'exists:comments,id'],
            ]);
            $comment = Comment::findOrFail($request->comment_id);
            $attachments = $comment->attachments;
            // Delete attachments from storage
            foreach ($attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
                $attachment->delete();
            }
            // Permanently delete the comment
            if ($comment->forceDelete()) {
                return response()->json([
                    'error' => false,
                    'message' => 'Comment deleted successfully.',
                    'id' => $comment->id,
                    'type' => 'task'
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Comment couldn\'t be deleted.'
                ]);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Comment not found."
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                "error" => true,
                "message" => "Validation errors occurred",
                "errors" => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "Comment couldn't be deleted."
            ], 500);
        }
    }

    /**
     * Delete a comment attachment.
     *
     * This endpoint deletes a specific attachment belonging to a comment and removes its file from storage.
     * The user must be authenticated and have permission to delete comment attachments.
     *
     * @authenticated
     *
     * @group Task Comments
     *
     * @urlParam id int required The ID of the comment attachment to delete.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Attachment deleted successfully."
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Attachment not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Attachment couldn't be deleted."
     * }
     */
    public function destroy_comment_attachment($id)
    {

        try {
            $attachment = CommentAttachment::findOrFail($id);

            Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();

            return response()->json([
                'error' => false,
                'message' => 'Attachment deleted successfully.',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Attachment not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Something went wrong.',
            ], 500);
        }
    }

    /**
     * Get all comments for a task with attachments and children.
     *
     * @authenticated
     * @group Task Comments
     * @urlParam id int required The ID of the project.
     * @response 200 {
     *   "error": false,
     *   "comments": [
     *     {
     *       "id": 1,
     *       "content": "Parent comment",
     *       "attachments": [...],
     *       "children": [
     *         {
     *           "id": 2,
     *           "content": "Reply",
     *           "attachments": [...],
     *           "children": [...]
     *         }
     *       ]
     *     }
     *   ]
     * }
     * @response 404 {
     *   "error": true,
     *   "message": "Project not found."
     * }
     */
    public function get_project_comments_api($id)
    {
        $limit = request('limit', 10);
        $offset = request('offset', 0);
        $search = request('search');

        try {
            $project = Task::findOrFail($id);

            $commentsQuery = $project->comments()
                ->whereNull('parent_id') // Only get parent comments, not child comments
                ->when($search, function ($query, $search) {
                    $query->where('content', 'LIKE', '%' . $search . '%');
                })
                ->orderBy('created_at', 'desc');
            $total = $commentsQuery->count();


            $comments = $commentsQuery
                ->skip($offset)
                ->take($limit)
                ->get();

            $result = $comments->map(function ($comment) {
                return formatComment($comment);
            });

            return response()->json([
                'error' => false,
                'data' => $result,
                'total' => $total,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Project not found.'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'error' => true,
                'message' => 'Could not retrieve comments.'
            ], 500);
        }
    }

    /**
     * Update the favorite status of a task.
     *
     * This endpoint updates whether a task is marked as a favorite or not. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Task Management
     *
     * @urlParam id int required The ID of the task to update.
     * @bodyParam is_favorite int required Indicates whether the task is a favorite. Use 1 for true and 0 for false.
     *
     * @response 200 {
     * "error": false,
     * "message": "Task favorite status updated successfully",
     * "data": {
     *   "id": 438,
     *   "title": "Task Example"
     *   // Other task details will be included in the actual response
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "is_favorite": [
     *       "The is favorite field must be either 0 or 1."
     *     ]
     *   }
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Task not found",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the favorite status."
     * }
     */
    public function update_favorite(Request $request, $id)
    {
        $isApi = request()->get('isApi', false);
        try {
            // Validate the request data
            $request->validate([
                'is_favorite' => 'required|integer|in:0,1',
            ]);
            // Get the authenticated user (could be either User or Client)
            $authUser = getAuthenticatedUser();
            // Find the task by ID
            $task = Task::find($id);
            // If the task is not found, return an error response
            if (!$task) {
                return formatApiResponse(
                    true,
                    'Task not found',
                    []
                );
            }
            $isFavorite = $request->input('is_favorite');
            // Check if the task is already favorited by the authenticated user/client
            $favorite = $authUser->favorites()->where('favoritable_type', Task::class)
                ->where('favoritable_id', $id)
                ->first();
            if ($isFavorite) {
                // If no existing favorite, create a new one
                if (!$favorite) {
                    $authUser->favorites()->create([
                        'favoritable_type' => Task::class,
                        'favoritable_id' => $id,
                    ]);
                }
            } else {
                // If unfavoriting, delete the record
                if ($favorite) {
                    $favorite->delete();
                }
            }
            // Return a successful response with the updated task
            return formatApiResponse(
                false,
                'Task favorite status updated successfully',
                ['data' => formatTask($task)]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the task favorite status.'
            ], 500);
        }
    }
    /**
     * Update the pinned status of a task.
     *
     * This endpoint updates whether a task is marked as pinned or not. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Task Management
     *
     * @urlParam id int required The ID of the task to update.
     * @bodyParam is_pinned int required Indicates whether the task is pinned. Use 1 for true and 0 for false.
     *
     * @response 200 {
     * "error": false,
     * "message": "Task pinned status updated successfully",
     * "data": {
     *   "id": 438,
     *   "title": "Task Example"
     *   // Other task details will be included in the actual response
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "is_pinned": [
     *       "The is pinned field must be either 0 or 1."
     *     ]
     *   }
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Task not found",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the pinned status."
     * }
     */
    public function update_pinned(Request $request, $id)
    {
        $isApi = request()->get('isApi', false);
        try {
            // Validate the request data
            $request->validate([
                'is_pinned' => 'required|integer|in:0,1',
            ]);
            // Get the authenticated user
            $authUser = getAuthenticatedUser();
            // Find the task by ID
            $task = Task::find($id);
            // If the task is not found, return an error response
            if (!$task) {
                return formatApiResponse(
                    true,
                    'Task not found',
                    []
                );
            }
            $isPinned = $request->input('is_pinned');
            // Check if the task is already pinned by the authenticated user
            $pinned = $authUser->pinnedTasks()
                ->where('pinnable_id', $id)
                ->first();
            if ($isPinned) {
                // If no existing pinned item, create a new one
                if (!$pinned) {
                    $authUser->pinnedTasks()->create([
                        'pinnable_type' => Task::class,
                        'pinnable_id' => $id,
                    ]);
                    $message = 'Pinned Successfully.'; // Success message for pinning
                } else {
                    $message = 'Already pinned.'; // In case it's already pinned
                }
            } else {
                // If unpinning, delete the record
                if ($pinned) {
                    $pinned->delete();
                    $message = 'Unpinned Successfully.'; // Success message for unpinning
                } else {
                    $message = 'Already unpinned.'; // In case it's not pinned to begin with
                }
            }
            // Return a successful response with the updated task
            return formatApiResponse(
                false,
                $message,
                ['data' => formatTask($task)]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the task pinned status.'
            ], 500);
        }
    }
    public function group_by_task_list(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = 10;  // Number of task lists per page
            $isFavorites = request()->get('favorite', false);
            $is_favorites = 0;
            if ($isFavorites) {
                $is_favorites = 1;
            }
            $taskLists = TaskList::with([
                'tasks' => function ($query) {
                    $query->with('users');
                }
            ])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
            if ($request->ajax()) {
                return response()->json([
                    'html' => view('components.group-task-list', compact('taskLists'))->render(),
                    'hasMorePages' => $taskLists->hasMorePages()
                ]);
            }
            $toSelectTaskUsers = $this->workspace->users;
            $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks : $this->user->tasks();
            $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects : $this->user->projects;
            $customFields = CustomField::where('module', 'task')->get();
            return view('tasks.group_by_task_lists', compact('taskLists', 'projects', 'tasks', 'toSelectTaskUsers', 'is_favorites', 'customFields'));
        } catch (\Exception $e) {
            // dd($e);
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }
    }
    /**
     * Get task status timeline.
     *
     * This endpoint retrieves the status change history of a task, sorted in descending order.
     *
     * @authenticated
     *
     * @group Task Management
     *
     * @urlParam id int required The ID of the task whose status timeline is to be retrieved.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Status timeline retrieved successfully.",
     *   "status_timeline": [
     *     {
     *       "id": 1,
     *       "status": "In Progress",
     *       "previous_status": "Pending",
     *       "new_color": "#ffcc00",
     *       "old_color": "#cccccc",
     *       "changed_at": "2025-03-03"
     *     },
     *     {
     *       "id": 2,
     *       "status": "Completed",
     *       "previous_status": "In Progress",
     *       "new_color": "#00cc66",
     *       "old_color": "#ffcc00",
     *       "changed_at": "2025-03-05 16:00:00"
     *     }
     *   ]
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Task not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Could not retrieve status timeline."
     * }
     */
    public function get_status_timelines_api($id)
    {
        try {
            $task = Task::findOrFail($id);
            $statusTimelines = $task->statusTimelines
                ->sortByDesc('changed_at')
                ->map(function ($timeline) {
                    return [
                        'id' => $timeline->id,
                        'entity_id' => $timeline->entity_id,
                        'entity_type' => $timeline->entity_type,
                        'status' => $timeline->status,
                        'previous_status' => $timeline->previous_status,
                        'new_color' => $timeline->new_color,
                        'old_color' => $timeline->old_color,
                        'time_diff' => Carbon::parse($timeline->changed_at ?? null)->diffForHumans(),
                        'changed_at' => format_date(Carbon::parse($timeline->changed_at ?? null), true, to_format: 'Y-m-d'),
                        'changed_time' => format_date(Carbon::parse($timeline->changed_at ?? null), false, to_format: 'H:i:s'),
                        'created_at' => format_date(Carbon::parse($timeline->created_at ?? null), to_format: 'Y-m-d'),
                        'updated_at' => format_date(Carbon::parse($timeline->updated_at ?? null), to_format: 'Y-m-d'),

                    ];
                })
                ->values();

            return formatApiResponse(
                false,
                'Status timelines retrieved successfully.',
                [
                    'data' => $statusTimelines,
                    'total' => $statusTimelines->count()
                ]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Task not found."
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                "error" => true,
                "message" => "Could not retrieve status timelines."
            ], 500);
        }
    }
}
