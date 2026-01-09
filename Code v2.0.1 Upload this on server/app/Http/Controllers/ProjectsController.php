<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Priority;
use App\Models\Milestone;
use App\Models\Workspace;
use App\Models\CustomField;
use App\Models\ProjectUser;
use Illuminate\Http\Request;
use App\Models\ProjectClient;
use App\Imports\ProjectsImport;
use App\Models\CommentAttachment;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserClientPreference;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Helpers\FileValidationHelper;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Chatify\Facades\ChatifyMessenger as Chatify;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Request as FacadesRequest;

class ProjectsController extends Controller
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
    public function index(Request $request, $type = null)
    {
        // Get multiple statuses from the request
        $statuses = $request->input('statuses', []);
        $selectedTags = $request->input('tags', []);
        $is_favorite = 0;
        if ($type === 'favorite') {
            $is_favorite = 1;
        }
        $sort = $request->input('sort', 'id');
        $order = 'desc';
        switch ($sort) {
            case 'newest':
                $sort = 'created_at';
                $order = 'desc';
                break;
            case 'oldest':
                $sort = 'created_at';
                $order = 'asc';
                break;
            case 'recently-updated':
                $sort = 'updated_at';
                $order = 'desc';
                break;
            case 'earliest-updated':
                $sort = 'updated_at';
                $order = 'asc';
                break;
            default:
                $sort = 'id';
                $order = 'desc';
                break;
        }
        $projectsQuery = isAdminOrHasAllDataAccess() ? $this->workspace->projects() : $this->user->projects();
        if (!empty($statuses)) {
            $projectsQuery->whereIn('status_id', $statuses); // Apply multiple status filter
        }
        if (!empty($selectedTags)) {
            $projectsQuery->whereHas('tags', function ($q) use ($selectedTags) {
                $q->whereIn('tags.id', $selectedTags);
            });
        }
        if ($is_favorite) {
            // Get the IDs of the projects marked as favorites by the user
            $favoriteProjectIds = $this->user->favoriteProjects()
                ->pluck('favoritable_id')  // Get the project IDs
                ->toArray();
            // Filter projects based on the favorite project IDs
            $projectsQuery->whereIn('projects.id', $favoriteProjectIds);
        }
        $projects = $projectsQuery->leftJoin('pinned', function ($join) {
            $join->on('pinned.pinnable_id', '=', 'projects.id')
                ->where('pinned.pinnable_type', '=', Project::class);
        })
            ->select('projects.*', 'pinned.id as pinned_id') // Select the projects and alias pinned.id as pinned_id
            ->orderByDesc('pinned.id') // Projects that are pinned will appear first
            ->orderBy($sort, $order) // Then order by other parameters (e.g., id or title)
            ->paginate(6);

        // custome field
        $customFields = CustomField::where('module', 'project')->get();

        return view('projects.grid_view', [
            'projects' => $projects,
            'auth_user' => $this->user,
            'selectedTags' => $selectedTags,
            'is_favorite' => $is_favorite,
            'customFields' => $customFields
        ]);
    }
    public function kanban_view(Request $request, $type = null)
    {
        $statuses = $request->input('statuses', []);
        $selectedTags = $request->input('tags', []);
        $is_favorite = 0;
        if ($type === 'favorite') {
            $is_favorite = 1;
        }
        $sort = (request('sort')) ? request('sort') : "id";
        $order = 'desc';
        if ($sort == 'newest') {
            $sort = 'created_at';
            $order = 'desc';
        } elseif ($sort == 'oldest') {
            $sort = 'created_at';
            $order = 'asc';
        } elseif ($sort == 'recently-updated') {
            $sort = 'updated_at';
            $order = 'desc';
        } elseif ($sort == 'earliest-updated') {
            $sort = 'updated_at';
            $order = 'asc';
        }
        $projectsQuery = isAdminOrHasAllDataAccess() ? $this->workspace->projects() : $this->user->projects();
        if (!empty($statuses)) {
            $projectsQuery->whereIn('status_id', $statuses); // Apply multiple status filter
        }
        if (!empty($selectedTags)) {
            $projectsQuery->whereHas('tags', function ($q) use ($selectedTags) {
                $q->whereIn('tags.id', $selectedTags);
            });
        }
        if ($is_favorite) {
            // Get the IDs of the projects marked as favorites by the user
            $favoriteProjectIds = $this->user->favoriteProjects()
                ->pluck('favoritable_id')  // Get the project IDs
                ->toArray();
            // Filter projects based on the favorite project IDs
            $projectsQuery->whereIn('projects.id', $favoriteProjectIds);
        }
        $projects = $projectsQuery->leftJoin('pinned', function ($join) {
            $join->on('pinned.pinnable_id', '=', 'projects.id')
                ->where('pinned.pinnable_type', '=', Project::class);
        })
            ->select('projects.*', 'pinned.id as pinned_id') // Select the projects and alias pinned.id as pinned_id
            ->orderByDesc('pinned.id') // Projects that are pinned will appear first
            ->orderBy($sort, $order)->get();

        $customFields = CustomField::where('module', 'project')->get();
        return view('projects.kanban', ['projects' => $projects, 'auth_user' => $this->user, 'selectedTags' => $selectedTags, 'is_favorite' => $is_favorite, 'customFields' => $customFields]);
    }
    public function list_view(Request $request, $type = null)
    {
        $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects : $this->user->projects;
        $customFields = CustomField::where('module', 'project')->get();
        $is_favorites = 0;
        if ($type === 'favorite') {
            $is_favorites = 1;
        }
        return view('projects.projects', ['projects' => $projects, 'is_favorites' => $is_favorites, 'customFields' => $customFields]);
    }
    public function ganttChartView(Request $request, $type = null)
    {
        $customFields = CustomField::where('module', 'project')->get();
        $is_favorite = 0;
        if ($type === 'favorite') {
            $is_favorite = 1;
        }
        return view('projects.gantt_chart', ['is_favorite' => $is_favorite, 'customFields' => $customFields]);
    }
    /**
     * Create a new project.
     *
     * This endpoint creates a new project with the provided details. The user must be authenticated to perform this action. The request validates various fields, including title, status, priority, dates, and task accessibility.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @bodyParam title string required The title of the project. Example: New Website Launch
     * @bodyParam status_id int required The ID of the project's status. Example: 1
     * @bodyParam priority_id int optional The ID of the project's priority. Example: 2
     * @bodyParam start_date string|null optional The start date of the project in the format specified in the general settings. Example: 2024-08-01
     * @bodyParam end_date string|null optional The end date of the project in the format specified in the general settings. Example: 2024-08-31
     * @bodyParam budget string|null optional Only digits, commas as thousand separators, and a single decimal point are allowed. digits can optionally be grouped in thousands with commas, where each group of digits must be exactly three digits long (e.g., 1,000 is correct; 10,0000 is not). Example: 5000.00
     * @bodyParam task_accessibility string required Indicates who can access the task. Must be either 'project_users' or 'assigned_users'. Example: project_users
     * @bodyParam description string|null optional A description of the project. Example: A project to launch a new company website.
     * @bodyParam note string|null optional Additional notes for the project. Example: Ensure all team members are informed.
     * @bodyParam user_id array|null optional Array of user IDs to be associated with the project. Example: [1, 2, 3]
     * @bodyParam client_id array|null optional Array of client IDs to be associated with the project. Example: [5, 6]
     * @bodyParam tag_ids array|null optional Array of tag IDs to be associated with the project. Example: [10, 11]
     * @bodyParam clientCanDiscuss string optional Indicates if the client can participate in project discussions. Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user; otherwise, it will be considered 0 by default. The value should be 'on' to allow client participation. Example: on
     *
     * @response 200 {
     * "error": false,
     * "message": "Project created successfully.",
     * "id": 438,
     * "data": {
     *   "id": 438,
     *   "title": "Res Test",
     *   "status": "Default",
     *   "priority": "dsfdsf",
     *   "users": [
     *     {
     *       "id": 7,
     *       "first_name": "Madhavan",
     *       "last_name": "Vaidya",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     *     },
     *     {
     *       "id": 185,
     *       "first_name": "Admin",
     *       "last_name": "Test",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
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
     *   "tags": [
     *     {
     *       "id": 45,
     *       "title": "Tag from update project"
     *     }
     *   ],
     *   "start_date": null,
     *   "end_date": null,
     *   "budget": "1000",
     *   "task_accessibility": "assigned_users",
     *   "description": null,
     *   "note": null,
     *   "favorite": 0,
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
     *     ],
     *     "status_id": [
     *       "The status_id field is required."
     *     ],
     *     "start_date": [
     *       "The start date must be before or equal to the end date."
     *     ],
     *     "budget": [
     *       "The budget format is invalid."
     *     ],
     *     "task_accessibility": [
     *       "The task accessibility must be either project_users or assigned_users."
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
     *   "message": "An error occurred while creating the project."
     * }
     */
    public function store(Request $request)
    {
        $isApi = request()->get('isApi', false);
        if ($request->input('priority_id') == 0) {
            $request->merge(['priority_id' => null]);
        }
        // Define validation rules
        $rules = [
            'title' => 'required',
            'status_id' => 'required|exists:statuses,id',
            'priority_id' => 'nullable|exists:priorities,id',
            'start_date' => [
                'nullable',
                'after_or_equal:today',
                function ($attribute, $value, $fail) use ($isApi) {
                    $endDate = request()->input('end_date');
                    $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);
                    if (!empty($errors['start_date'])) {
                        foreach ($errors['start_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'end_date' => [
                'nullable',
                'after_or_equal:today',
                function ($attribute, $value, $fail) use ($isApi) {
                    $startDate = request()->input('start_date');
                    $errors = validate_date_format_and_order($startDate, $value, $isApi ? 'Y-m-d' : null);
                    if (!empty($errors['end_date'])) {
                        foreach ($errors['end_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'budget' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $error = validate_currency_format($value, 'budget');
                    if ($error) {
                        $fail($error);
                    }
                }
            ],
            'task_accessibility' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value !== 'project_users' && $value !== 'assigned_users') {
                        $fail('The task accessibility must be either project_users or assigned_users.');
                    }
                }
            ],
            'description' => 'nullable|string',
            'note' => 'nullable|string',
            'user_id' => 'nullable|array',
            'user_id.*' => 'integer|exists:users,id', // Validate that each user_id exists in the users table
            'client_id' => 'nullable|array',
            'client_id.*' => 'integer|exists:clients,id', // Validate that each client_id exists in the clients table
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id', // Validate that each tag_id exists in the tags table
            'enable_tasks_time_entries' => 'boolean',
        ];
        // Custom validation messages
        $messages = [
            'status_id.required' => 'The status field is required.',
            'start_date.after_or_equal' => 'The start date must be today or a future date.',
            'end_date.after_or_equal'   => 'The end date must be today or a future date.',
        ];
        // Validate the request
        try {
            $formFields = $request->validate($rules, $messages);
            $status = Status::findOrFail($request->input('status_id'));
            if (canSetStatus($status)) {
                $start_date = $request->input('start_date');
                $end_date = $request->input('end_date');
                if ($start_date) {
                    $formFields['start_date'] = format_date($start_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
                }
                if ($end_date) {
                    $formFields['end_date'] = format_date($end_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
                }
                $formFields['budget'] = str_replace(',', '', $request->input('budget'));
                $formFields['workspace_id'] = getWorkspaceId();
                $formFields['created_by'] = $this->user->id;
                unset($formFields['user_id']);
                unset($formFields['client_id']);
                unset($formFields['tag_ids']);
                $clientCanDiscuss = isAdminOrHasAllDataAccess() && $request->filled('clientCanDiscuss') && $request->input('clientCanDiscuss') == 'on' ? 1 : 0;
                $formFields['client_can_discuss'] = $clientCanDiscuss;
                $new_project = Project::create($formFields);
                $userIds = $request->input('user_id') ?? [];
                $clientIds = $request->input('client_id') ?? [];
                $tagIds = $request->input('tag_ids') ?? [];
                // Set creator as a participant automatically if !isAdminOrHasAllDataAccess
                if (!isAdminOrHasAllDataAccess()) {
                    if (getGuardName() == 'client' && !in_array($this->user->id, $clientIds)) {
                        array_splice($clientIds, 0, 0, $this->user->id);
                    } else if (getGuardName() == 'web' && !in_array($this->user->id, $userIds)) {
                        array_splice($userIds, 0, 0, $this->user->id);
                    }
                }
                $project_id = $new_project->id;
                $project = Project::find($project_id);
                $project->users()->attach($userIds);
                $project->clients()->attach($clientIds);
                $project->tags()->attach($tagIds);
                if ($request->has('is_favorite') && $request->input('is_favorite') == 1) {
                    $this->user->favorites()->create([
                        'favoritable_type' => Project::class,
                        'favoritable_id' => $project_id,
                    ]);
                }
                //Status Timeline
                $project->statusTimelines()->create([
                    'status' => $status->title,
                    'new_color' => $status->color,
                    'previous_status' => '-',
                    'changed_at' => now(),
                ]);

                // Store custom field values
                if ($request->has('custom_fields')) {
                    foreach ($request->custom_fields as $field_id => $value) {
                        // Handle checkboxes (arrays)
                        if (is_array($value)) {
                            $value = json_encode($value);
                        }

                        $project->customFieldValues()->create([
                            'custom_field_id' => $field_id,
                            'value' => $value
                        ]);
                    }
                }

                $notification_data = [
                    'type' => 'project',
                    'type_id' => $project_id,
                    'type_title' => $project->title,
                    'access_url' => 'projects/information/' . $project_id,
                    'action' => 'assigned'
                ];
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
                    'Project created successfully.',
                    [
                        'id' => $new_project->id,
                        'data' => formatProject($project)
                    ]
                );
            } else {
                return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // dd($e);
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the project.'
            ], 500);
        }
    }
    public function showBulkUploadForm(Request $request)
    {
        $sampleFileUrl = asset('storage/files/Projects bulk upload sample.xlsx');
        $helpUrl = asset('storage/files/Projects bulk upload instructions.pdf');
        return view('bulk-upload', [
            'entity' => 'projects',
            'form_action' => url('projects/process-bulk-upload'),
            'sample_file_url' => $sampleFileUrl,
            'help_url' => $helpUrl
        ]);
    }
    public function importBulkProjects(Request $request)
    {
        // Validate file type (ensure it's Excel or CSV)
        $request->validate([
            'bulk_file' => 'required|mimes:xlsx,xls,csv'
        ]);
        try {
            // Initialize the import class
            $import = new ProjectsImport;
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
                'message' => 'Projects imported successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while importing projects: ' . $e->getMessage()
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
        $project = Project::findOrFail($id);
        $projectTags = $project->tags;
        $types = getControllerNames();
        $comments = $project->comments;
        $customFields = CustomField::where('module', 'project')->get();
        return view('projects.project_information', ['project' => $project, 'projectTags' => $projectTags, 'types' => $types, 'auth_user' => $this->user, 'comments' => $comments, 'customFields' => $customFields]);
    }
    public function get($projectId)
    {
        $project = Project::findOrFail($projectId);
        $project->budget = format_currency($project->budget, false, false);
        $users = $project->users()->get();
        $clients = $project->clients()->get();
        $tags = $project->tags()->get();
        $workspace_users = $this->workspace->users;
        $workspace_clients = $this->workspace->clients;
        $project->load("customFieldValues");
        // Prepare custom field values for API response
        $customFields = CustomField::where('module', 'project')->get();
        // dd($project->customFieldValues);
        // Prepare custom field values for the view
        $customFieldValues = [];
        foreach ($project->customFieldValues as $fieldValue) {
            $customFieldValues[$fieldValue->custom_field_id] = $fieldValue->value;
        }

        return response()->json([
            'error' => false,
            'project' => $project,
            'users' => $users,
            'clients' => $clients,
            'workspace_users' => $workspace_users,
            'workspace_clients' => $workspace_clients,
            'tags' => $tags,
            'customFields' => $customFields,
            'customFieldValues' => $customFieldValues
        ]);
    }
    /**
     * Update an existing project.
     *
     * This endpoint updates an existing project with the provided details. The user must be authenticated to perform this action. The request validates various fields, including title, status, priority, dates, and task accessibility.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @bodyParam id int required The ID of the project to update. Example: 1
     * @bodyParam title string required The title of the project. Example: Updated Project Title
     * @bodyParam status_id int required The ID of the project's status. Example: 2
     * @bodyParam priority_id int optional The ID of the project's priority. Example: 3
     * @bodyParam budget string|null optional Only digits, commas as thousand separators, and a single decimal point are allowed. digits can optionally be grouped in thousands with commas, where each group of digits must be exactly three digits long (e.g., 1,000 is correct; 10,0000 is not). Example: 5000.00
     * @bodyParam task_accessibility string required Indicates who can access the task. Must be either 'project_users' or 'assigned_users'. Example: assigned_users
     * @bodyParam start_date string|null optional The start date of the project in the format specified in the general settings. Example: 2024-08-01
     * @bodyParam end_date string|null optional The end date of the project in the format specified in the general settings. Example: 2024-08-31
     * @bodyParam description string|null optional A description of the project. Example: Updated project description.
     * @bodyParam note string|null optional Additional notes for the project. Example: Updated note for the project.
     * @bodyParam user_id array|null optional Array of user IDs to be associated with the project. Example: [2, 3]
     * @bodyParam client_id array|null optional Array of client IDs to be associated with the project. Example: [5, 6]
     * @bodyParam tag_ids array|null optional Array of tag IDs to be associated with the project. Example: [10, 11]
     * @bodyParam clientCanDiscuss string optional Indicates if the client can participate in project discussions. Can only specify if `is_admin_or_has_all_data_access` is true for the logged-in user; otherwise, it will be considered current value by default. The value should be 'on' to allow client participation. Example: on
     *
     * @response 200 {
     * "error": false,
     * "message": "Project updated successfully.",
     * "id": 438,
     * "data": {
     *   "id": 438,
     *   "title": "Res Test",
     *   "status": "Default",
     *   "priority": "dsfdsf",
     *   "users": [
     *     {
     *       "id": 7,
     *       "first_name": "Madhavan",
     *       "last_name": "Vaidya",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     *     },
     *     {
     *       "id": 185,
     *       "first_name": "Admin",
     *       "last_name": "Test",
     *       "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
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
     *   "tags": [
     *     {
     *       "id": 45,
     *       "title": "Tag from update project"
     *     }
     *   ],
     *   "start_date": null,
     *   "end_date": null,
     *   "budget": "1000",
     *   "task_accessibility": "assigned_users",
     *   "description": null,
     *   "note": null,
     *   "favorite": 0,
     *   "created_at": "07-08-2024 14:38:51",
     *   "updated_at": "07-08-2024 14:38:51"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The project ID is required.",
     *       "The project ID does not exist in our records."
     *     ],
     *     "status_id": [
     *       "The status field is required."
     *     ],
     *     "budget": [
     *       "The budget format is invalid."
     *     ],
     *     "task_accessibility": [
     *       "The task accessibility must be either project_users or assigned_users."
     *     ],
     *     "start_date": [
     *       "The start date must be before or equal to the end date."
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
     *   "message": "An error occurred while updating the project."
     * }
     */
    public function update(Request $request)
    {

        $isApi = request()->get('isApi', false);
        if ($request->input('priority_id') == 0) {
            $request->merge(['priority_id' => null]);
        }
        $rules = [
            'id' => 'required|exists:projects,id',
            'title' => 'required',
            'status_id' => 'required',
            'priority_id' => 'nullable|exists:priorities,id',
            'budget' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $error = validate_currency_format($value, 'budget');
                    if ($error) {
                        $fail($error);
                    }
                }
            ],
            'task_accessibility' => [
                'required',
                function ($attribute, $value, $fail) {
                    if ($value !== 'project_users' && $value !== 'assigned_users') {
                        $fail('The task accessibility must be either project_users or assigned_users.');
                    }
                }
            ],
            'start_date' => [
                'nullable',
                'after_or_equal:today',
                function ($attribute, $value, $fail) use ($isApi) {
                    $endDate = request()->input('end_date');
                    $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);
                    if (!empty($errors['start_date'])) {
                        foreach ($errors['start_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'end_date' => [
                'nullable',
                'after_or_equal:today',
                function ($attribute, $value, $fail) use ($isApi) {
                    $startDate = request()->input('start_date');
                    $errors = validate_date_format_and_order($startDate, $value, $isApi ? 'Y-m-d' : null);
                    if (!empty($errors['end_date'])) {
                        foreach ($errors['end_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'user_id' => 'nullable|array',
            'user_id.*' => 'exists:users,id', // Validate that each user_id exists in the users table
            'client_id' => 'nullable|array',
            'client_id.*' => 'exists:clients,id', // Validate that each client_id exists in the clients table
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id', // Validate that each tag_id exists in the tags table
            'enable_tasks_time_entries' => 'boolean',
        ];
        $messages = [
            'status_id.required' => 'The status field is required.',
            'start_date.after_or_equal' => 'The start date must be today or a future date.',
            'end_date.after_or_equal'   => 'The end date must be today or a future date.',
        ];
        // Validate the request
        try {
            $request->validate($rules, $messages);
            $id = $request->input('id');
            $project = Project::findOrFail($id);
            $currentStatusId = $project->status_id;
            $formFieldsToUpdate = [
                'title' => $request->input('title'),
                'status_id' => $request->input('status_id'),
                'priority_id' => $request->input('priority_id'),
                'budget' => str_replace(',', '', $request->input('budget')),
                'task_accessibility' => $request->input('task_accessibility'),
                'description' => $request->input('description'),
                'note' => $request->input('note'),
                'enable_tasks_time_entries' => $request->input('enable_tasks_time_entries', false),
            ];
            // Check if the status has changed
            if ($currentStatusId != $request->input('status_id')) {
                $status = Status::findOrFail($request->input('status_id'));
                if (!canSetStatus($status)) {
                    return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
                }
                // Status Time Storing
                $oldStatus = Status::findOrFail($currentStatusId);
                $newStatus = Status::findOrFail($formFieldsToUpdate['status_id']);
                $project->statusTimelines()->create([
                    'status' => $newStatus->title,
                    'new_color' => $newStatus->color,
                    'previous_status' => $oldStatus->title,
                    'old_color' => $oldStatus->color,
                    'changed_at' => now(),
                ]);
            }
            // Handle start_date
            if ($request->filled('start_date')) {
                $formFieldsToUpdate['start_date'] = format_date($request->input('start_date'), false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            } else {
                $formFieldsToUpdate['start_date'] = null;
            }
            // Handle end_date
            if ($request->filled('end_date')) {
                $formFieldsToUpdate['end_date'] = format_date($request->input('end_date'), false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            } else {
                $formFieldsToUpdate['end_date'] = null;
            }
            $clientCanDiscuss = isAdminOrHasAllDataAccess()
                ? ($request->input('clientCanDiscuss') == 'on' ? 1 : 0)
                : $project->client_can_discuss;
            $formFieldsToUpdate['client_can_discuss'] = $clientCanDiscuss;
            $userIds = $request->input('user_id') ?? [];
            $clientIds = $request->input('client_id') ?? [];
            $tagIds = $request->input('tag_ids') ?? [];
            // Get current list of users and clients associated with the project
            $existingUserIds = $project->users->pluck('id')->toArray();
            $existingClientIds = $project->clients->pluck('id')->toArray();
            // Update project and its relationships
            $project->update($formFieldsToUpdate);
            $project->users()->sync($userIds);
            $project->clients()->sync($clientIds);
            $project->tags()->sync($tagIds);
            // Exclude old users and clients from receiving notification
            $userIds = array_diff($userIds, $existingUserIds);
            $clientIds = array_diff($clientIds, $existingClientIds);

            // Update custom field values
            if ($request->has('custom_fields')) {

                foreach ($request->custom_fields as $field_id => $value) {
                    // Handle checkboxes (arrays)
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }

                    // Find existing custom field value or create new
                    $fieldValue = $project->customFieldValues()
                        ->where('custom_field_id', $field_id)
                        ->first();

                    if ($fieldValue) {
                        $fieldValue->update(['value' => $value]);
                    } else {
                        $project->customFieldValues()->create([
                            'custom_field_id' => $field_id,
                            'value' => $value
                        ]);
                    }
                }
            }

            // Prepare notification data
            $notificationData = [
                'type' => 'project',
                'type_id' => $project->id,
                'type_title' => $project->title,
                'access_url' => 'projects/information/' . $project->id,
                'action' => 'assigned'
            ];
            // Determine recipients
            $recipients = array_merge(
                array_map(function ($userId) {
                    return 'u_' . $userId;
                }, $userIds),
                array_map(function ($clientId) {
                    return 'c_' . $clientId;
                }, $clientIds)
            );
            // Process notifications
            processNotifications($notificationData, $recipients);
            if ($currentStatusId != $request->input('status_id')) {
                $currentStatus = Status::findOrFail($currentStatusId);
                $newStatus = Status::findOrFail($request->input('status_id'));
                $notification_data = [
                    'type' => 'project_status_updation',
                    'type_id' => $project->id,
                    'type_title' => $project->title,
                    'updater_first_name' => $this->user->first_name,
                    'updater_last_name' => $this->user->last_name,
                    'old_status' => $currentStatus->title,
                    'new_status' => $newStatus->title,
                    'access_url' => 'projects/information/' . $project->id,
                    'action' => 'status_updated'
                ];
                $currentRecipients = array_merge(
                    array_map(function ($userId) {
                        return 'u_' . $userId;
                    }, $existingUserIds),
                    array_map(function ($clientId) {
                        return 'c_' . $clientId;
                    }, $existingClientIds)
                );
                processNotifications($notification_data, $currentRecipients);
            }
            $project = $project->fresh();
            return formatApiResponse(
                false,
                'Project updated successfully.',
                [
                    'id' => $project->id,
                    'data' => formatProject($project)
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the project.'
            ], 500);
        }
    }
    /**
     * Remove the specified project.
     *
     * This endpoint deletes a project based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @urlParam id int required The ID of the project to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Project deleted successfully.",
     *   "id": 1,
     *   "title": "Project Title",
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Project not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the project."
     * }
     */
    public function destroy($id)
    {
        $project = Project::find($id);
        if ($project) {
            $response = DeletionService::delete(Project::class, $id, 'Project');
            $data = $response->getData();
            if ($data->error) {
                return response()->json(['error' => true, 'message' => $data->message]);
            }
            // Get all attachments before deletion
            $comments = $project->comments()->with('attachments')->get();
            // Delete all files using public disk
            $comments->each(function ($comment) {
                $comment->attachments->each(function ($attachment) {
                    Storage::disk('public')->delete($attachment->file_path);
                    $attachment->delete();
                });
            });
            // Delete associated favorites for this project
            $project->favorites()->delete();
            // Delete all pinned records associated with this project
            $project->pinned()->delete();
            $project->comments()->forceDelete();
            $project->notificationsForProject()->delete();
            return $response;
        } else {
            return formatApiResponse(
                true,
                'Project not found.',
                []
            );
        }
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:projects,id' // Ensure each ID in 'ids' is an integer and exists in the 'projects' table
        ]);
        $ids = $validatedData['ids'];
        $deletedProjects = [];
        $deletedProjectTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $project = Project::find($id);
            if ($project) {
                $deletedProjectTitles[] = $project->title;
                $comments = $project->comments()->with('attachments')->get();
                // Delete all files using public disk
                $comments->each(function ($comment) {
                    $comment->attachments->each(function ($attachment) {
                        Storage::disk('public')->delete($attachment->file_path);
                        $attachment->delete();
                    });
                });
                // Delete associated favorites for this project
                $project->favorites()->delete();
                // Delete all pinned records associated with this project
                $project->pinned()->delete();
                $project->comments()->forceDelete();
                $project->notificationsForProject()->delete();
                DeletionService::delete(Project::class, $id, 'Project');
                $deletedProjects[] = $id;
            }
        }
        return response()->json(['error' => false, 'message' => 'Project(s) deleted successfully.', 'id' => $deletedProjects, 'titles' => $deletedProjectTitles]);
    }
    public function list(Request $request, $id = '', $type = '')
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status_ids = request('status_ids', []);
        $priority_ids = request('priority_ids', []);
        $user_ids = request('user_ids', []);
        $client_ids = request('client_ids', []);
        $tag_ids = $request->input('tag_ids', []);
        $date_between_from = request('project_date_between_from') ?: "";
        $date_between_to = request('project_date_between_to') ?: "";
        $start_date_from = (request('project_start_date_from')) ? request('project_start_date_from') : "";
        $start_date_to = (request('project_start_date_to')) ? request('project_start_date_to') : "";
        $end_date_from = (request('project_end_date_from')) ? request('project_end_date_from') : "";
        $end_date_to = (request('project_end_date_to')) ? request('project_end_date_to') : "";
        $is_favorites = (request('is_favorites')) ? request('is_favorites') : "";
        if ($id) {
            $id = explode('_', $id);
            $belongs_to = $id[0];
            $belongs_to_id = $id[1];
            $userOrClient = $belongs_to == 'user' ? User::find($belongs_to_id) : Client::find($belongs_to_id);
            $projects = isAdminOrHasAllDataAccess($belongs_to, $belongs_to_id) ? $this->workspace->projects() : $userOrClient->projects();
        } else {
            $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects() : $this->user->projects();
        }
        if (!empty($user_ids)) {
            $projects = $projects->whereHas('users', function ($query) use ($user_ids) {
                $query->whereIn('users.id', $user_ids);
            });
        }
        if (!empty($client_ids)) {
            $projects = $projects->whereHas('clients', function ($query) use ($client_ids) {
                $query->whereIn('clients.id', $client_ids);
            });
        }
        if (!empty($status_ids)) {
            $projects->whereIn('status_id', $status_ids);
        }
        if (!empty($priority_ids)) {
            $projects->whereIn('priority_id', $priority_ids);
        }
        if (!empty($tag_ids)) {
            $projects->whereHas('tags', function ($query) use ($tag_ids) {
                $query->whereIn('tags.id', $tag_ids);
            });
        }
        if ($date_between_from && $date_between_to) {
            $projects->where('start_date', '>=', $date_between_from)
                ->where('end_date', '<=', $date_between_to);
        }
        if ($start_date_from && $start_date_to) {
            $projects->whereBetween('start_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $projects->whereBetween('end_date', [$end_date_from, $end_date_to]);
        }
        if ($is_favorites) {
            // Get the IDs of the projects marked as favorites by the user
            $favoriteProjectIds = $this->user->favoriteProjects()
                ->pluck('favoritable_id')  // Get the project IDs
                ->toArray();
            // Filter projects based on the favorite project IDs
            $projects->whereIn('projects.id', $favoriteProjectIds);
        }
        $projects->when($search, function ($query) use ($search) {
            $query->where('title', 'like', '%' . $search . '%')
                ->orWhere('projects.id', 'like', '%' . $search . '%');
        });
        $totalprojects = $projects->count();
        $canCreate = checkPermission('create_projects');
        $canEdit = checkPermission('edit_projects');
        $canDelete = checkPermission('delete_projects');
        $statuses = Status::all();
        $priorities = Priority::all();
        $isHome = $request->query('from_home') == '1';
        $webGuard = Auth::guard('web')->check();
        $projects = $projects->leftJoin('pinned', function ($join) {
            $join->on('pinned.pinnable_id', '=', 'projects.id')
                ->where('pinned.pinnable_type', '=', Project::class);
        })
            ->select('projects.*', 'pinned.id as pinned_id')  // Select the projects and alias pinned.id as pinned_id
            ->orderByDesc('pinned.id') // Projects that are pinned will appear first
            ->orderBy('projects.' . $sort, $order)  // Then order by other parameters (e.g., id or title)
            ->paginate(request("limit"))
            ->through(
                function ($project) use ($statuses, $priorities, $canEdit, $canDelete, $canCreate, $isHome, $webGuard) {
                    $statusOptions = '';
                    foreach ($statuses as $status) {
                        // Determine if the option should be disabled
                        $disabled = canSetStatus($status) ? '' : 'disabled';
                        // Render the option with appropriate attributes
                        $selected = $project->status_id == $status->id ? 'selected' : '';
                        $statusOptions .= "<option value='{$status->id}' class='badge bg-label-$status->color' $selected $disabled>$status->title</option>";
                    }
                    $priorityOptions = "<option value='' class='badge bg-label-secondary'>-</option>";
                    foreach ($priorities as $priority) {
                        $selected = $project->priority_id == $priority->id ? 'selected' : '';
                        $priorityOptions .= "<option value='{$priority->id}' class='badge bg-label-$priority->color' $selected>$priority->title</option>";
                    }
                    $actions = '';
                    if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-project" data-offcanvas="true" data-id="' . $project->id . '" title="' . get_label('update', 'Update') . '">' .
                            '<i class="bx bx-edit mx-1"></i>' .
                            '</a>';
                    }
                    if ($canDelete) {
                        $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $project->id . '" data-type="projects" data-table="projects_table" data-reload="' . ($isHome ? 'true' : '') . '">' .
                            '<i class="bx bx-trash text-danger mx-1"></i>' .
                            '</button>';
                    }
                    if ($canCreate) {
                        $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $project->id . '" data-title="' . $project->title . '" data-type="projects" data-table="projects_table" data-reload="' . ($isHome ? 'true' : '') . '" title="' . get_label('duplicate', 'Duplicate') . '">' .
                            '<i class="bx bx-copy text-warning mx-2"></i>' .
                            '</a>';
                    }
                    $actions .= '<a href="javascript:void(0);" class="quick-view" data-id="' . $project->id . '" data-type="project" title="' . get_label('quick_view', 'Quick View') . '">' .
                        '<i class="bx bx-info-circle text-info mx-3"></i>' .
                        '</a>';
                    $actions .= '<a href="' . url('projects/mind-map/' . $project->id) . '" title="' . get_label('mind_map', 'Mind Map') . '">' .
                        '<i class="bx bx-sitemap ms-2"></i>' .
                        '</a>';
                    $actions = $actions ?: '-';
                    $userHtml = '';
                    if (!empty($project->users) && count($project->users) > 0) {
                        $userHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                        foreach ($project->users as $user) {
                            $userHtml .= "<li class='avatar avatar-sm pull-up'><a href='" . url("/users/profile/{$user->id}") . "' title='{$user->first_name} {$user->last_name}'><img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                        }
                        if ($canEdit) {
                        $userHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="' . $project->id . '"><span class="bx bx-edit"></span></a></li>';
                        }
                        $userHtml .= '</ul>';
                    } else {
                        $userHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                        if ($canEdit) {
                        $userHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="' . $project->id . '">' .
                                '<span class="bx bx-edit"></span>' .
                                '</a>';
                        }
                    }
                    $clientHtml = '';
                    if (!empty($project->clients) && count($project->clients) > 0) {
                        $clientHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                        foreach ($project->clients as $client) {
                            $clientHtml .= "<li class='avatar avatar-sm pull-up'><a href='" . url("/clients/profile/{$client->id}") . "' title='{$client->first_name} {$client->last_name}'><img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                        }
                        if ($canEdit) {
                        $clientHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="' . $project->id . '"><span class="bx bx-edit"></span></a></li>';
                        }
                        $clientHtml .= '</ul>';
                    } else {
                        $clientHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                        if ($canEdit) {
                        $clientHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="' . $project->id . '">' .
                                '<span class="bx bx-edit"></span>' .
                                '</a>';
                        }
                    }
                    $tagHtml = '';
                    foreach ($project->tags as $tag) {
                        $tagHtml .= "<span class='badge bg-label-{$tag->color}'>{$tag->title}</span> ";
                    }
                    $isFavorite = getFavoriteStatus($project->id);
                    $isPinned = getPinnedStatus($project->id);
                    return [
                        'id' => $project->id,
                        'title' => "<a href='" . url("/projects/information/{$project->id}") . "'><strong>{$project->title}</strong></a>
                        <a href='javascript:void(0);' class='mx-2'>
                            <i class='bx " . ($isFavorite ? 'bxs' : 'bx') . "-star favorite-icon text-warning' data-favorite='{$isFavorite}' data-id='{$project->id}' title='" . ($isFavorite ? get_label('remove_favorite', 'Click to remove from favorite') : get_label('add_favorite', 'Click to mark as favorite')) . "'></i>
                        </a><a href='javascript:void(0);' class='mr-2'>
                <i class='bx " . ($isPinned ? 'bxs' : 'bx') . "-pin pinned-icon text-success' data-pinned='{$isPinned}' data-id='{$project->id}' data-require_reload='0' title='" . ($isPinned ? get_label('click_unpin', 'Click to Unpin') : get_label('click_pin', 'Click to Pin')) . "'></i>
            </a>" . ($webGuard || $project->client_can_discuss ?
                            "<a href='" . route('projects.info', ['id' => $project->id]) . "#navs-top-discussions'  class='ms-2'>
                                <i class='bx bx-message-rounded-dots text-danger' data-bs-toggle='tooltip' data-bs-placement='right' title='" . get_label('discussions', 'Discussions') . "'></i>
                            </a>"
                            : ""),
                        'users' => $userHtml,
                        'clients' => $clientHtml,
                        'start_date' => format_date($project->start_date),
                        'end_date' => format_date($project->end_date),
                        'budget' => !empty($project->budget) && $project->budget !== null ? format_currency($project->budget) : '-',
                        'status_id' => "<div class='d-flex align-items-center'>
                            <select class='form-select form-select-sm select-bg-label-{$project->status->color} fixed-width-select' id='statusSelect' data-id='{$project->id}' data-original-status-id='{$project->status->id}' data-original-color-class='select-bg-label-{$project->status->color}'" . ($isHome ? ' data-reload="true"' : '') . ">
                                {$statusOptions}
                            </select>
                            " . ($project->note ?
                            "<i class='bx bx-notepad ms-2 text-primary' title='{$project->note}'></i>"
                            : "") . "
                        </div>",
                        'priority_id' => "<select class='form-select form-select-sm select-bg-label-" . ($project->priority ? $project->priority->color : 'secondary') . "' id='prioritySelect' data-id='{$project->id}' data-original-priority-id='" . ($project->priority ? $project->priority->id : '') . "' data-original-color-class='select-bg-label-" . ($project->priority ? $project->priority->color : 'secondary') . "'>{$priorityOptions}</select>",
                        'task_accessibility' => get_label($project->task_accessibility, ucwords(str_replace("_", " ", $project->task_accessibility))),
                        'tags' => $tagHtml ?: ' - ',
                        'created_at' => format_date($project->created_at, true),
                        'updated_at' => format_date($project->updated_at, true),
                        'actions' => $actions
                    ];
                }
            );
        return response()->json([
            "rows" => $projects->items(),
            "total" => $totalprojects,
        ]);
    }
    /**
     * List or search projects.
     *
     * This endpoint retrieves a list of projects based on various filters. The user must be authenticated to perform this action. The request allows filtering by status, user, client, priority, tag, date ranges, and other parameters.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @urlParam id int optional The ID of the project to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter projects by title or id. Example: Project
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, title, status, priority, start_date, end_date, budget, created_at, and updated_at. Example: title
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam status_ids array optional An array of status IDs to filter projects by. Example: [2, 3]
     * @queryParam user_ids array optional An array of user IDs to filter projects by. Example: [1, 2, 3]
     * @queryParam client_ids array optional An array of client IDs to filter projects by. Example: [5, 6]
     * @queryParam priority_ids array optional An array of priority IDs to filter projects by. Example: [1, 2]
     * @queryParam tag_ids array optional An array of tag IDs to filter projects by. Example: [1, 2]
     * @queryParam project_start_date_from string optional The start date range's start in YYYY-MM-DD format. Example: 2024-01-01
     * @queryParam project_start_date_to string optional The start date range's end in YYYY-MM-DD format. Example: 2024-12-31
     * @queryParam project_end_date_from string optional The end date range's start in YYYY-MM-DD format. Example: 2024-01-01
     * @queryParam project_end_date_to string optional The end date range's end in YYYY-MM-DD format. Example: 2024-12-31
     * @queryParam is_favorites boolean optional Filter projects marked as favorites. Example: true
     * @queryParam limit int optional The number of projects per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Projects retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 351,
     *       "title": "rwer",
     *       "status": "Rel test",
     *       "priority": "Default",
     *       "users": [
     *         {
     *           "id": 7,
     *           "first_name": "Madhavan",
     *           "last_name": "Vaidya",
     *           "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     *         },
     *         {
     *           "id": 183,
     *           "first_name": "Girish",
     *           "last_name": "Thacker",
     *           "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     *         }
     *       ],
     *       "clients": [],
     *       "tags": [],
     *       "start_date": "14-06-2024",
     *       "end_date": "14-06-2024",
     *       "budget": "",
     *       "created_at": "14-06-2024 17:50:09",
     *       "updated_at": "17-06-2024 19:08:16"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Project not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Projects not found",
     *   "total": 0,
     *   "data": []
     * }
     */
    public function apiList(Request $request, $id = '')
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'array',
            'user_ids.*' => 'integer|exists:users,id',
            'client_ids' => 'array',
            'client_ids.*' => 'integer|exists:clients,id',
            'priority_ids' => 'array',
            'priority_ids.*' => 'integer|exists:priorities,id',
            'tag_ids' => 'array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'status_ids' => 'array',
            'status_ids.*' => 'integer|exists:statuses,id',
        ]);
        // If validation fails, return a response
        if ($validator->fails()) {
            return formatApiValidationError(1, $validator->errors());
        }
        $search = $request->input('search');
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $status_ids = $request->input('status_ids', []);
        $priority_ids = $request->input('priority_ids', []);
        $user_ids = $request->input('user_ids', []);
        $client_ids = $request->input('client_ids', []);
        $tag_ids = $request->input('tag_ids', []);
        $start_date_from = $request->input('project_start_date_from', '');
        $start_date_to = $request->input('project_start_date_to', '');
        $end_date_from = $request->input('project_end_date_from', '');
        $end_date_to = $request->input('project_end_date_to', '');
        $is_favorites = $request->input('is_favorites', '');
        $limit = $request->input('limit', 10); // default limit
        $offset = $request->input('offset', 0); // default offset
        if ($id) {
            $project = Project::find($id);
            if (!$project) {
                return formatApiResponse(
                    false,
                    'Project not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            } else {
                return formatApiResponse(
                    false,
                    'Project retrieved successfully',
                    [
                        'total' => 1,
                        'data' => [formatProject($project)]
                    ]
                );
            }
        } else {
            $projectsQuery = isAdminOrHasAllDataAccess() ? $this->workspace->projects() : $this->user->projects();
            // Multi-select filters
            if (!empty($user_ids)) {
                $projectsQuery->whereHas('users', function ($query) use ($user_ids) {
                    $query->whereIn('users.id', $user_ids);
                });
            }
            if (!empty($client_ids)) {
                $projectsQuery->whereHas('clients', function ($query) use ($client_ids) {
                    $query->whereIn('clients.id', $client_ids);
                });
            }
            if (!empty($status_ids)) {
                $projectsQuery->whereIn('status_id', $status_ids);
            }
            if (!empty($priority_ids)) {
                $projectsQuery->whereIn('priority_id', $priority_ids);
            }
            if (!empty($tag_ids)) {
                $projectsQuery->whereHas('tags', function ($query) use ($tag_ids) {
                    $query->whereIn('tags.id', $tag_ids);
                });
            }
            if ($start_date_from && $start_date_to) {
                $projectsQuery->whereBetween('start_date', [$start_date_from, $start_date_to]);
            }
            if ($end_date_from && $end_date_to) {
                $projectsQuery->whereBetween('end_date', [$end_date_from, $end_date_to]);
            }
            if ($start_date_from) {
                $projectsQuery->where('start_date', '>=', $start_date_from);
            }
            if ($end_date_to) {
                $projectsQuery->where('end_date', '<=', $end_date_to);
            }
            if ($is_favorites) {
                // Get the IDs of the projects marked as favorites by the user
                $favoriteProjectIds = $this->user->favoriteProjects()
                    ->pluck('favoritable_id')  // Get the project IDs
                    ->toArray();
                // Filter projects based on the favorite project IDs
                $projectsQuery->whereIn('projects.id', $favoriteProjectIds);
            }
            // Fixed search functionality to respect workspace constraints
            if ($search) {
                $projectsQuery->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('projects.description', 'like', '%' . $search . '%')
                        ->orWhere('projects.id', 'like', '%' . $search . '%');
                });
            }
            $total = $projectsQuery->count(); // get total count before applying offset and limit
            $projects = $projectsQuery->leftJoin('pinned', function ($join) {
                $join->on('pinned.pinnable_id', '=', 'projects.id')
                    ->where('pinned.pinnable_type', '=', Project::class);
            })
                ->select('projects.*', 'pinned.id as pinned_id')  // Select projects and alias pinned.id as pinned_id
                ->orderByDesc('pinned.id')  // Projects that are pinned will appear first
                ->orderBy($sort, $order)  // Then order by other parameters (e.g., id or title)
                ->skip($offset)  // Apply the offset
                ->take($limit)  // Apply the limit
                ->get();
            if ($projects->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Projects not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $projects->map(function ($project) {
                return formatProject($project);
            });
            return formatApiResponse(
                false,
                'Projects retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }
    /**
     * Update the favorite status of a project.
     *
     * This endpoint updates whether a project is marked as a favorite or not. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @urlParam id int required The ID of the project to update.
     * @bodyParam is_favorite int required Indicates whether the project is a favorite. Use 1 for true and 0 for false.
     *
     * @response 200 {
     * "error": false,
     * "message": "Project favorite status updated successfully",
     * "data": {
     * "id": 438,
     * "title": "Res Test",
     * "status": "Default",
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
     * "id": 103,
     * "first_name": "Test",
     * "last_name": "Test",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     * }
     * ],
     * "tags": [
     * {
     * "id": 45,
     * "title": "Tag from update project"
     * }
     * ],
     * "start_date": null,
     * "end_date": null,
     * "budget": "1000.00",
     * "task_accessibility": "assigned_users",
     * "description": null,
     * "note": null,
     * "favorite": 1,
     * "created_at": "07-08-2024 14:38:51",
     * "updated_at": "12-08-2024 13:36:10"
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
     *   "message": "Project not found",
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
            // Find the project by ID
            $project = Project::find($id);
            // If the project is not found, return an error response
            if (!$project) {
                return formatApiResponse(
                    true,
                    'Project not found',
                    []
                );
            }
            $isFavorite = $request->input('is_favorite');
            // Check if the project is already favorited by the authenticated user/client
            $favorite = $authUser->favorites()->where('favoritable_type', Project::class)
                ->where('favoritable_id', $id)
                ->first();
            if ($isFavorite) {
                // If no existing favorite, create a new one
                if (!$favorite) {
                    $authUser->favorites()->create([
                        'favoritable_type' => Project::class,
                        'favoritable_id' => $id,
                    ]);
                }
            } else {
                // If unfavoriting, delete the record
                if ($favorite) {
                    $favorite->delete();
                }
            }
            // Return a successful response with the updated project
            return formatApiResponse(
                false,
                'Project favorite status updated successfully',
                ['data' => formatProject($project)]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the project favorite status.'
            ], 500);
        }
    }
    /**
     * Update the pinned status of a project.
     *
     * This endpoint updates whether a project is marked as pinned or not. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @urlParam id int required The ID of the project to update.
     * @bodyParam is_pinned int required Indicates whether the project is pinned. Use 1 for true and 0 for false.
     *
     * @response 200 {
     * "error": false,
     * "message": "Project pinned status updated successfully",
     * "data": {
     *   "id": 438,
     *   "title": "Res Test"
     *   // Other project details will be included in the actual response
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
     *   "message": "Project not found",
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
            // Get the authenticated user (could be either User or Client)
            $authUser = getAuthenticatedUser();
            // Find the project by ID
            $project = Project::find($id);
            // If the project is not found, return an error response
            if (!$project) {
                return formatApiResponse(
                    true,
                    'Project not found',
                    []
                );
            }
            $isPinned = $request->input('is_pinned');
            // Check if the project is already pinned by the authenticated user/client
            $pinned = $authUser->pinnedProjects()
                ->where('pinnable_id', $id)
                ->first();
            if ($isPinned) {
                // If no existing pinned item, create a new one
                if (!$pinned) {
                    $authUser->pinnedProjects()->create([
                        'pinnable_type' => Project::class,
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
            // Return a successful response with the updated project
            return formatApiResponse(
                false,
                $message,
                ['data' => formatProject($project)]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the project pinned status.'
            ], 500);
        }
    }
    public function duplicate($id)
    {
        // Define the related tables for this meeting
        $relatedTables = ['users', 'clients', 'tasks', 'tags']; // Include related tables as needed
        // Use the general duplicateRecord function
        $title = (request()->has('title') && !empty(trim(request()->title))) ? request()->title : '';
        $duplicate = duplicateRecord(Project::class, $id, $relatedTables, $title);
        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Project duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Project duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Project duplicated successfully.', 'id' => $id]);
    }
    /**
     * Upload media files to a project.
     *
     * This endpoint allows authenticated users to upload media files related to a project.
     *
     * @authenticated
     *
     * @group Project Media
     *
     * @bodyParam id int required The ID of the project to which media files are being uploaded.
     * @bodyParam media_files[] file required An array of media files to be uploaded. Maximum file size is defined in the config.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "File(s) uploaded successfully.",
     *   "id": [101, 102],
     *   "type": "media",
     *   "parent_type": "project",
     *   "parent_id": 438
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": ["The selected id is invalid."],
     *     "media_files": ["The media file size exceeds the limit."]
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Project not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred during file upload."
     * }
     */
    public function upload_media(Request $request)
    {

        $isApi = request()->get('isApi', false);
        try {
            $maxFileSizeBytes = config('media-library.max_file_size');
            $maxFileSizeKb = (int) ($maxFileSizeBytes / 1024);
            $validatedData = $request->validate([
                'id' => ['required', 'integer', 'exists:projects,id'],
                'media_files.*' => "file|max:$maxFileSizeKb"
            ]);
            $mediaIds = [];
            if ($request->hasFile('media_files')) {
                $project = Project::findOrFail($validatedData['id']);
                $mediaFiles = $request->file('media_files');
                foreach ($mediaFiles as $mediaFile) {
                    $mediaItem = $project->addMedia($mediaFile)
                        ->sanitizingFileName(function ($fileName) {
                            $sanitizedFileName = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));
                            $uniqueId = time() . '_' . mt_rand(1000, 9999);
                            $extension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
                            $baseName = pathinfo($sanitizedFileName, PATHINFO_FILENAME);
                            return "{$baseName}-{$uniqueId}.{$extension}";
                        })
                        ->toMediaCollection('project-media');
                    $mediaIds[] = $mediaItem->id;
                }
                return response()->json([
                    'error' => false,
                    'message' => 'File(s) uploaded successfully.',
                    'id' => $mediaIds,
                    'type' => 'media',
                    'parent_type' => 'project',
                    'parent_id' => $project->id
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'No file(s) chosen.'
                ]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Project not found."
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "An error occurred during file upload."
            ], 500);
        }
    }

    public function get_media($id)
    {
        try {
            $search = request('search');
            $sort = request('sort', 'id');
            $order = request('order', 'DESC');
            $project = Project::findOrFail($id);
            $media = $project->getMedia('project-media');
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
                    ? asset('storage/project-media/' . $mediaItem->file_name)
                    : $mediaItem->getFullUrl();
                $fileExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                $isImage = in_array(strtolower($fileExtension), $imageExtensions);
                if ($isImage) {
                    $html = '<a href="' . $fileUrl . '" data-lightbox="project-media">';
                    $html .= '<img src="' . $fileUrl . '" alt="' . $mediaItem->file_name . '" width="50">';
                    $html .= '</a>';
                } else {
                    $html = '<a href="' . $fileUrl . '" title="' . get_label('download', 'Download') . '">' . $mediaItem->file_name . '</a>';
                }
                $actions = '<a href="' . $fileUrl . '" title="' . get_label('download', 'Download') . '" download>' .
                    '<i class="bx bx-download bx-sm"></i>' .
                    '</a>';
                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $mediaItem->id . '" data-type="project-media" data-table="project_media_table">' .
                        '<i class="bx bx-trash text-danger"></i>' .
                        '</button>';
                }
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
            $formattedMedia = $order === 'asc'
                ? $formattedMedia->sortBy($sort)
                : $formattedMedia->sortByDesc($sort);
            return response()->json([
                'error' => false,
                'message' => 'Media files retrieved successfully.',
                'rows' => $formattedMedia->values()->toArray(),
                'total' => $formattedMedia->count(),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Project not found."
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "Could not retrieve media files."
            ], 500);
        }
    }
    /**
     * Get project media files.
     *
     * This endpoint retrieves all media files associated with a specific project, including sorting and search capabilities.
     *
     * @authenticated
     *
     * @group Project Media
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
     *       "file": "<a href='https://example.com/storage/project-media/image.jpg' data-lightbox='project-media'><img src='https://example.com/storage/project-media/image.jpg' alt='image.jpg' width='50'></a>",
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
     *   "message": "Project not found."
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
            $project = Project::findOrFail($id);
            $media = $project->getMedia('project-media');
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
                    ? asset('storage/project-media/' . $mediaItem->file_name)
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
                "message" => "Project not found."
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
     * This endpoint deletes a specified media file associated with a project. The user must be authenticated and have permission to delete media files.
     *
     * @authenticated
     *
     * @group Project Media
     *
     * @urlParam mediaId int required The ID of the media file to delete.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "File deleted successfully.",
     *   "id": 101,
     *   "title": "image.jpg",
     *   "parent_id": 438,
     *   "type": "media",
     *   "parent_type": "project"
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "File not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "File couldn't be deleted."
     * }
     */
    public function delete_media($mediaId)
    {
        try {
            $mediaItem = Media::findOrFail($mediaId);
            // Delete the file from storage
            Storage::disk($mediaItem->disk)->delete($mediaItem->getPath());
            // Delete the media item from the database
            $mediaItem->delete();
            return response()->json([
                'error' => false,
                'message' => 'File deleted successfully.',
                'id' => $mediaItem->id,
                'title' => $mediaItem->file_name,
                'parent_id' => $mediaItem->model_id,
                'type' => 'media',
                'parent_type' => 'project'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => true,
                'message' => 'File not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => "File couldn't be deleted."
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
        return response()->json(['error' => false, 'message' => 'Files(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'parent_id' => $parentIds, 'type' => 'media', 'parent_type' => 'project']);
    }
    /**
     * Store a new milestone.
     *
     * This endpoint creates a new milestone for a specified project. The user must be authenticated and have permission to create milestones.
     *
     * @authenticated
     *
     * @group Milestone Management
     *
     * @bodyParam project_id int required The ID of the project to which the milestone belongs.
     * @bodyParam title string required The title of the milestone.
     * @bodyParam status string required The status of the milestone.
     * @bodyParam start_date date optional The start date of the milestone (YYYY-MM-DD).
     * @bodyParam end_date date optional The end date of the milestone (YYYY-MM-DD).
     * @bodyParam cost numeric required The cost of the milestone.
     * @bodyParam description string optional A description of the milestone.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Milestone created successfully.",
     *   "id": 12,
     *   "type": "milestone",
     *   "parent_type": "project",
     *   "parent_id": 438
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "project_id": ["The selected project_id is invalid."],
     *     "title": ["The title field is required."],
     *     "cost": ["The cost format is invalid."]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Milestone couldn't be created."
     * }
     */
    public function store_milestone(Request $request)
    {
        $isApi = request()->get('isApi', false);
        $rules = [
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'start_date' => [
                'nullable',
                function ($attribute, $value, $fail) use ($isApi) {
                    $endDate = request()->input('end_date');
                    $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);
                    if (!empty($errors['start_date'])) {
                        foreach ($errors['start_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'end_date' => [
                'nullable',
                function ($attribute, $value, $fail) use ($isApi) {
                    $startDate = request()->input('start_date');
                    $errors = validate_date_format_and_order($startDate, $value, $isApi ? 'Y-m-d' : null);
                    if (!empty($errors['end_date'])) {
                        foreach ($errors['end_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'cost' => [
                'required',
                function ($attribute, $value, $fail) {
                    $error = validate_currency_format($value, 'cost');
                    if ($error) {
                        $fail($error);
                    }
                }
            ],
            'description' => 'nullable|string',
        ];
        try {
            $request->validate($rules);
            $formFields = $request->only(['project_id', 'title', 'status', 'description']);
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
            if ($start_date) {
                $formFields['start_date'] = format_date($start_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            }
            if ($end_date) {
                $formFields['end_date'] = format_date($end_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            }
            $formFields['cost'] = str_replace(',', '', $request->cost);
            $formFields['workspace_id'] = $this->workspace->id;
            $formFields['created_by'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;
            $milestone = Milestone::create($formFields);
            return formatApiResponse(
                false,
                'Milestone created successfully.',
                [
                    'id' => $milestone->id,
                    'type' => 'milestone',
                    'parent_type' => 'project',
                    'parent_id' => $milestone->project_id
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {

            return response()->json([
                'error' => true,
                'message' => 'Milestone couldn\'t be created.' . $e->getMessage()
            ], 500);
        }
    }
    public function get_milestones($id)
    {
        try {
            $project = Project::findOrFail($id);
            $search = request('search');
            $sort = request('sort', 'id');
            $order = request('order', 'DESC');
            $statuses = request('statuses');
            $date_between_from = request('date_between_from', '');
            $date_between_to = request('date_between_to', '');
            $start_date_from = request('start_date_from', '');
            $start_date_to = request('start_date_to', '');
            $end_date_from = request('end_date_from', '');
            $end_date_to = request('end_date_to', '');
            $milestones = $project->milestones();
            if ($search) {
                $milestones->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%')
                        ->orWhere('cost', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }
            if ($date_between_from && $date_between_to) {
                $milestones->where('start_date', '>=', $date_between_from)
                    ->where('end_date', '<=', $date_between_to);
            }
            if ($start_date_from && $start_date_to) {
                $milestones->whereBetween('start_date', [$start_date_from, $start_date_to]);
            }
            if ($end_date_from && $end_date_to) {
                $milestones->whereBetween('end_date', [$end_date_from, $end_date_to]);
            }
            if ($statuses) {
                $milestones->whereIn('status', $statuses);
            }
            $total = $milestones->count();
            $canEdit = checkPermission('edit_milestones');
            $canDelete = checkPermission('delete_milestones');
            $milestones = $milestones->orderBy($sort, $order)
                ->paginate(request("limit"))
                ->through(function ($milestone) use ($canEdit, $canDelete) {
                    $statusBadge = match ($milestone->status) {
                        'incomplete' => '<span class="badge bg-danger">' . get_label('incomplete', 'Incomplete') . '</span>',
                        'complete' => '<span class="badge bg-success">' . get_label('complete', 'Complete') . '</span>',
                        default => '<span class="badge bg-warning">' . get_label('pending', 'Pending') . '</span>'
                    };
                    $progress = '
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: ' . $milestone->progress . '%" aria-valuenow="' . $milestone->progress . '" aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
                <h6 class="mt-2">' . $milestone->progress . '%</h6>';
                    $actions = '';
                    if ($canEdit) {
                        $actions .= '<a href="javascript:void(0);" class="edit-milestone" data-bs-toggle="modal" data-bs-target="#edit_milestone_modal" data-id="' . $milestone->id . '" title="' . get_label('update', 'Update') . '">' .
                            '<i class="bx bx-edit mx-1"></i>' .
                            '</a>';
                    }
                    if ($canDelete) {
                        $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $milestone->id . '" data-type="milestone" data-table="project_milestones_table">' .
                            '<i class="bx bx-trash text-danger mx-1"></i>' .
                            '</button>';
                    }
                    return [
                        'id' => $milestone->id,
                        'title' => $milestone->title,
                        'status' => $statusBadge,
                        'progress' => $progress,
                        'cost' => format_currency($milestone->cost),
                        'start_date' => format_date($milestone->start_date),
                        'end_date' => format_date($milestone->end_date),
                        'created_by' => strpos($milestone->created_by, 'u_') === 0
                            ? formatUserHtml(User::find(substr($milestone->created_by, 2)))
                            : formatClientHtml(Client::find(substr($milestone->created_by, 2))),
                        'description' => $milestone->description,
                        'created_at' => format_date($milestone->created_at, true),
                        'updated_at' => format_date($milestone->updated_at, true),
                        'actions' => $actions ?: '-'
                    ];
                });
            return formatApiResponse(
                false,
                'Milestones Retrieved Successfully',
                ['rows' => $milestones->items(), 'total' => $total]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Project not found."
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "Could not retrieve milestones."
            ], 500);
        }
    }
    /**
     * Get a list of milestones for a project.
     *
     * This endpoint retrieves all milestones associated with a given project.
     * It supports searching, filtering, and sorting functionalities.
     *
     * @authenticated
     *
     * @group Milestone Management
     *
     * @urlParam id int required The ID of the project whose milestones are to be retrieved.
     * @queryParam search string optional Search for milestones by title, ID, cost, or description.
     * @queryParam sort string optional Field to sort by (default: "id").
     * @queryParam order string optional Sorting order (ASC/DESC, default: "DESC").
     * @queryParam statuses array optional Filter by milestone statuses.
     * @queryParam date_between_from date optional Filter milestones starting from this date.
     * @queryParam date_between_to date optional Filter milestones ending at this date.
     * @queryParam start_date_from date optional Filter milestones with a start date after this date.
     * @queryParam start_date_to date optional Filter milestones with a start date before this date.
     * @queryParam end_date_from date optional Filter milestones with an end date after this date.
     * @queryParam end_date_to date optional Filter milestones with an end date before this date.
     * @queryParam limit int optional Number of records per page.
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 12,
     *       "title": "Design Phase",
     *       "status": Complete,
     *       "progress": "75",
     *       "cost": "₹1,500.00",
     *       "start_date": "2025-03-10",
     *       "end_date": "2025-03-20",
     *       "created_by": "John Doe",
     *       "description": "Initial design phase for the project.",
     *       "created_at": "2025-03-01",
     *       "updated_at": "2025-03-05",
     *
     *     }
     *   ],
     *   "total": 1
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Project not found."
     * }
     */
    public function get_milestones_api($id)
    {
        try {
            $project = Project::findOrFail($id);
            $search = request('search');
            $sort = request('sort', 'id');
            $order = request('order', 'DESC');
            $statuses = request('statuses');
            $date_between_from = request('date_between_from', '');
            $date_between_to = request('date_between_to', '');
            $start_date_from = request('start_date_from', '');
            $start_date_to = request('start_date_to', '');
            $end_date_from = request('end_date_from', '');
            $end_date_to = request('end_date_to', '');
            $milestones = $project->milestones();
            if ($search) {
                $milestones->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('id', 'like', '%' . $search . '%')
                        ->orWhere('cost', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }
            if ($date_between_from && $date_between_to) {
                $milestones->where('start_date', '>=', $date_between_from)
                    ->where('end_date', '<=', $date_between_to);
            }
            if ($start_date_from && $start_date_to) {
                $milestones->whereBetween('start_date', [$start_date_from, $start_date_to]);
            }
            if ($end_date_from && $end_date_to) {
                $milestones->whereBetween('end_date', [$end_date_from, $end_date_to]);
            }
            if ($statuses) {
                $milestones->whereIn('status', $statuses);
            }
            $total = $milestones->count();
            $milestones = $milestones->orderBy($sort, $order)
                ->paginate(request("limit"))
                ->through(function ($milestone) {
                    $statusBadge = match ($milestone->status) {
                        'incomplete' => get_label('incomplete', 'Incomplete'),
                        'complete' => get_label('complete', 'Complete'),
                        default => get_label('pending', 'Pending')
                    };
                    $progress = $milestone->progress;
                    $creator = strpos($milestone->created_by, 'u_') === 0
                        ? User::find(substr($milestone->created_by, 2))
                        : Client::find(substr($milestone->created_by, 2));
                    return [
                        'id' => $milestone->id,
                        'title' => $milestone->title,
                        'status' => $statusBadge,
                        'progress' => $progress,
                        'cost' => format_currency($milestone->cost),
                        'start_date' => format_date($milestone->start_date, to_format: 'Y-m-d'),
                        'end_date' => format_date($milestone->end_date, to_format: 'Y-m-d'),
                        'created_by' => $creator ? ucwords($creator->first_name) . ' ' . ucwords($creator->last_name) : 'N/A',
                        'description' => $milestone->description ? $milestone->description : ' ',
                        'created_at' => format_date($milestone->created_at, to_format: 'Y-m-d'),
                        'updated_at' => format_date($milestone->updated_at, to_format: 'Y-m-d'),
                    ];
                });
            return formatApiResponse(
                false,
                'Milestones Retrieved Successfully',
                ['data' => $milestones->items(), 'total' => $total]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Project not found."
            ], 404);
        } catch (\Exception $e) {
            dd($e);
            return response()->json([
                "error" => true,
                "message" => "Could not retrieve milestones."
            ], 500);
        }
    }
    /**
     * Get details of a specific milestone.
     *
     * This endpoint retrieves details of a specific milestone by its ID.
     *
     * @authenticated
     *
     * @group Milestone Management
     *
     * @urlParam id int required The ID of the milestone to retrieve.
     *
     * @response 200 {
     *   "error": false,
     *   "milestone": {
     *     "id": 12,
     *     "title": "Design Phase",
     *     "status": "In Progress",
     *     "cost": "₹1,500.00",
     *     "start_date": "2025-03-10",
     *     "end_date": "2025-03-20",
     *     "description": "Initial design phase for the project.",
     *     "created_at": "2025-03-01",
     *     "updated_at": "2025-03-05"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Milestone not found."
     * }
     */
    public function get_milestone($id)
    {
        try {
            $milestone = Milestone::findOrFail($id);
            $milestone->cost = format_currency($milestone->cost, false, false);

            return formatApiResponse(
                false,
                'Milestone Retrieved Successfully',
                ['ms' => $milestone]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Milestone not found."
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "Could not retrieve milestone."
            ], 500);
        }
    }
    /**
     * Update an existing milestone.
     *
     * This endpoint updates a specified milestone. The user must be authenticated and have permission to modify the milestone.
     *
     * @authenticated
     *
     * @group Milestone Management
     *
     * @urlParam id int required The ID of the milestone to be updated.
     * @bodyParam title string required The updated title of the milestone.
     * @bodyParam status string required The updated status of the milestone.
     * @bodyParam start_date date optional The updated start date of the milestone (YYYY-MM-DD).
     * @bodyParam end_date date optional The updated end date of the milestone (YYYY-MM-DD).
     * @bodyParam cost numeric required The updated cost of the milestone.
     * @bodyParam progress int required The updated progress percentage of the milestone.
     * @bodyParam description string optional An updated description of the milestone.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Milestone updated successfully.",
     *   "id": 12,
     *   "type": "milestone",
     *   "parent_type": "project",
     *   "parent_id": 438
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "title": ["The title field is required."],
     *     "cost": ["The cost format is invalid."],
     *     "progress": ["The progress field is required."]
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Milestone not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Milestone couldn't be updated."
     * }
     */
    public function update_milestone(Request $request)
    {
        $isApi = request()->get('isApi', false);
        try {
            $rules = [
                'title' => ['required', 'string', 'max:255'],
                'status' => ['required', 'string'],
                'start_date' => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        $endDate = request()->input('end_date');
                        $errors = validate_date_format_and_order($value, $endDate);
                        if (!empty($errors['start_date'])) {
                            foreach ($errors['start_date'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
                'end_date' => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        $startDate = request()->input('start_date');
                        $errors = validate_date_format_and_order($startDate, $value);
                        if (!empty($errors['end_date'])) {
                            foreach ($errors['end_date'] as $error) {
                                $fail($error);
                            }
                        }
                    },
                ],
                'cost' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $error = validate_currency_format($value, 'cost');
                        if ($error) {
                            $fail($error);
                        }
                    }
                ],
                'progress' => ['required', 'integer', 'min:0', 'max:100'],
                'description' => ['nullable', 'string'],
            ];
            $request->validate($rules);
            $milestone = Milestone::findOrFail($request->input('id'));
            $milestone->update([
                'title' => $request->input('title'),
                'status' => $request->input('status'),
                'cost' => str_replace(',', '', $request->input('cost')),
                'progress' => $request->input('progress'),
                'description' => $request->input('description'),
                'start_date' => $request->filled('start_date')
                    ? format_date($request->input('start_date'), false, app('php_date_format'), 'Y-m-d')
                    : null,
                'end_date' => $request->filled('end_date')
                    ? format_date($request->input('end_date'), false, app('php_date_format'), 'Y-m-d')
                    : null,
            ]);
            return formatApiResponse(
                false,
                'Milestone Updated Successfully',
                [
                    'ms' => $milestone,
                    'id' => $milestone->id,
                    'type' => 'milestone',
                    'parent_type' => 'project',
                    'parent_id' => $milestone->project_id
                ]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Milestone not found."
            ], 404);
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "Milestone couldn't be updated."
            ], 500);
        }
    }
    /**
     * Delete a milestone.
     *
     * This endpoint deletes a specified milestone. The user must be authenticated and have permission to delete milestones.
     *
     * @authenticated
     *
     * @group Milestone Management
     *
     * @urlParam id int required The ID of the milestone to delete.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Milestone deleted successfully.",
     *   "id": 12,
     *   "title": "Design Phase",
     *   "type": "milestone",
     *   "parent_type": "project",
     *   "parent_id": 438
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Milestone not found."
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "Milestone couldn't be deleted."
     * }
     */
    public function delete_milestone($id)
    {
        try {
            $milestone = Milestone::findOrFail($id);
            // Call the deletion service to delete the milestone
            DeletionService::delete(Milestone::class, $id, 'Milestone');
            return formatApiResponse(
                false,
                'Milestone Deleted Successfully',
                [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'type' => 'milestone',
                    'parent_type' => 'project',
                    'parent_id' => $milestone->project_id
                ]
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                "error" => true,
                "message" => "Milestone not found."
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "Milestone couldn't be deleted."
            ], 500);
        }
    }
    public function delete_multiple_milestone(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:milestones,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);
        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        $parentIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $ms = Milestone::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $ms->title;
            $parentIds[] = $ms->project_id;
            DeletionService::delete(Milestone::class, $id, 'Milestone');
        }
        return response()->json(['error' => false, 'message' => 'Milestone(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'type' => 'milestone', 'parent_type' => 'project', 'parent_id' => $parentIds]);
    }
    /**
     * Update the status of a project.
     *
     * This endpoint updates the status of a specified project. The user must be authenticated and have permission to set the new status. A notification will be sent to all users and clients associated with the project.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @urlParam id int required The ID of the project whose status is to be updated.
     * @bodyParam statusId int required The ID of the new status to set for the project.
     * @bodyParam note string optional An optional note to attach to the project update.
     *
     * @response 200 {
     * "error": false,
     * "message": "Status updated successfully.",
     * "id": "438",
     * "type": "project",
     * "activity_message": "Madhavan Vaidya updated project status from Default to vbnvbnvbn",
     * "data": {
     * "id": 438,
     * "title": "Res Test",
     * "status": "vbnvbnvbn",
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
     * "id": 103,
     * "first_name": "Test",
     * "last_name": "Test",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     * }
     * ],
     * "tags": [
     * {
     * "id": 45,
     * "title": "Tag from update project"
     * }
     * ],
     * "start_date": null,
     * "end_date": null,
     * "budget": "1000.00",
     * "task_accessibility": "assigned_users",
     * "description": null,
     * "note": null,
     * "favorite": 1,
     * "created_at": "07-08-2024 14:38:51",
     * "updated_at": "12-08-2024 13:49:33"
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
    public function update_status(Request $request, $id = null)
    {
        $isApi = request()->get('isApi', false);
        if ($id) {
            $request->merge(['id' => $id]);
        }
        $rules = [
            'id' => 'required|exists:projects,id',
            'statusId' => 'required|exists:statuses,id'
        ];
        try {
            $request->validate($rules);
            $id = $request->id;
            $statusId = $request->statusId;
            $status = Status::findOrFail($statusId);
            if (canSetStatus($status)) {
                $project = Project::findOrFail($id);
                $oldStatus = $project->status_id;
                if ($project->status->id != $statusId) {
                    $currentStatus = $project->status->title;
                    $project->status_id = $statusId;
                    $project->note = $request->note;
                    $oldStatus = Status::findOrFail($oldStatus);
                    $newStatus = Status::findOrFail($statusId);
                    $project->statusTimelines()->create([
                        'status' => $newStatus->title,
                        'new_color' => $newStatus->color,
                        'previous_status' => $oldStatus->title,
                        'old_color' => $oldStatus->color,
                        'changed_at' => now(),
                    ]);
                    if ($project->save()) {
                        // Reload the project to get updated status information
                        $project = $project->fresh();
                        $newStatus = $project->status->title;
                        $notification_data = [
                            'type' => 'project_status_updation',
                            'type_id' => $id,
                            'type_title' => $project->title,
                            'updater_first_name' => $this->user->first_name,
                            'updater_last_name' => $this->user->last_name,
                            'old_status' => $currentStatus,
                            'new_status' => $newStatus,
                            'access_url' => 'projects/information/' . $id,
                            'action' => 'status_updated'
                        ];
                        $userIds = $project->users->pluck('id')->toArray();
                        $clientIds = $project->clients->pluck('id')->toArray();
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
                                'type' => 'project',
                                'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' updated project status from ' . trim($currentStatus) . ' to ' . trim($newStatus),
                                'data' => formatProject($project)
                            ]
                        );
                    } else {
                        return response()->json(['error' => true, 'message' => 'Status couldn\'t be updated.']);
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
    /**
     * Update the priority of a project.
     *
     * This endpoint updates the priority of a specified project. The user must be authenticated and have permission to set the new priority.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @urlParam id int required The ID of the project whose priority is to be updated.
     * @bodyParam priorityId int required The ID of the new priority to set for the project.
     *
     * @response 200 {
     * "error": false,
     * "message": "Priority updated successfully.",
     * "id": "438",
     * "type": "project",
     * "activity_message": "Madhavan Vaidya updated project priority from Low to Medium",
     * "data": {
     * "id": 438,
     * "title": "Res Test",
     * "status": "Test From Pro",
     * "priority": "Medium",
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
     * "id": 103,
     * "first_name": "Test",
     * "last_name": "Test",
     * "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     * }
     * ],
     * "tags": [
     * {
     * "id": 45,
     * "title": "Tag from update project"
     * }
     * ],
     * "start_date": null,
     * "end_date": null,
     * "budget": "1000.00",
     * "task_accessibility": "assigned_users",
     * "description": null,
     * "note": null,
     * "favorite": 1,
     * "created_at": "07-08-2024 14:38:51",
     * "updated_at": "12-08-2024 13:58:55"
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
     *       "The selected priority id is invalid."
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
            'id' => 'required|exists:projects,id',
            'priorityId' => 'nullable|exists:priorities,id'
        ];
        try {
            $request->validate($rules);
            $id = $request->id;
            $priorityId = $request->priorityId;
            $project = Project::findOrFail($id);
            if ($project->priority_id != $priorityId) {
                $currentPriority = $project->priority ? $project->priority->title : '-';
                $project->priority_id = $priorityId;
                if ($project->save()) {
                    // Reload the project to get updated priority information
                    $project = $project->fresh();
                    $newPriority = $project->priority ? $project->priority->title : '-';
                    $message = trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' updated project priority from ' . trim($currentPriority) . ' to ' . trim($newPriority);
                    return formatApiResponse(
                        false,
                        'Priority updated successfully.',
                        [
                            'id' => $id,
                            'type' => 'project',
                            'activity_message' => $message,
                            'data' => formatProject($project)
                        ]
                    );
                } else {
                    return response()->json(['error' => true, 'message' => 'Priority couldn\'t be updated.']);
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
    /**
     * Add a comment.
     *
     * This endpoint allows authenticated users to add comments to a specific model (e.g., tasks, projects).
     * Users can also attach files and mention other users.
     *
     * @authenticated
     *
     * @group Project Comments
     *
     * @urlParam id int required The ID of the project to add a comment to.
     * @bodyParam model_type string required The type of model being commented on (e.g., "Task", "Project").
     * @bodyParam model_id int required The ID of the model being commented on.
     * @bodyParam content string required The comment text.
     * @bodyParam parent_id int optional The ID of the parent comment (for replies).
     * @bodyParam attachments[] file optional An array of files to attach to the comment. Maximum file size is defined in the config.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Comment Added Successfully",
     *   "comment": {
     *     "id": 45,
     *     "commentable_type": "App\Models\Project",
     *     "commentable_id": 438,
     *     "content": "This is a sample comment with a mention @JohnDoe",
     *     "commenter_id": 7,
     *     "commenter_type": "App\\Models\\User",
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
        $isApi = request()->get('isApi', false);
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
     * This endpoint retrieves details of a specific comment by its ID, including any attachments.
     *
     * @authenticated
     *
     * @group Project Comments
     *
     * @urlParam id int required The ID of the comment to retrieve.
     *
     * @response 200 {
     *   "error": false,
     *   "comment": {
     *     "id": 45,
     *     "commentable_type": "App\\Models\\Project",
     *     "commentable_id": 438,
     *     "content": "This is a sample comment with a mention @JohnDoe",
     *     "commenter_id": 7,
     *     "commenter_type": "App\\Models\\User",
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
     */
    public function get_comment(Request $request, $id)
    {
        try {
            $comment = Comment::with('attachments')->findOrFail($id);
            return response()->json([
                'error' => false,
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
     * @group Project Comments
     *
     * @bodyParam comment_id int required The ID of the comment to be updated.
     * @bodyParam content string required The updated content of the comment.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Comment updated successfully.",
     *   "id": 45,
     *   "type": "project"
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
        $isApi = request()->get('isApi', false);
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
                    'type' => 'project'
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
     * @group Project Comments
     *
     * @queryParam comment_id int required The ID of the comment to delete.
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Comment deleted successfully.",
     *   "id": 45,
     *   "type": "project"
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
                    'type' => 'project'
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
     * @group Project Comments
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
     * Get all comments for a project with attachments and children.
     *
     * @authenticated
     * @group Project Comments
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
            $project = Project::findOrFail($id);

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


    public function saveViewPreference(Request $request)
    {
        $view = $request->input('view');
        $prefix = isClient() ? 'c_' : 'u_';
        if (
            UserClientPreference::updateOrCreate(
                ['user_id' => $prefix . $this->user->id, 'table_name' => 'projects'],
                ['default_view' => $view]
            )
        ) {
            return response()->json(['error' => false, 'message' => 'Default View Set Successfully.']);
        } else {
            return response()->json(['error' => true, 'message' => 'Something Went Wrong.']);
        }
    }
    public function mind_map(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        $mindMapData = $this->getMindMapData($projectId);

        $customFields = CustomField::where('module', 'project')->get();

        return view('projects.mind_map', compact('mindMapData', 'project', 'customFields'));
    }

    /**
     * Get Mind Map Data of a specific project.
     *
     * This endpoint retrieves mind map data of a specific project by its ID.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @urlParam id int required The ID of the data to retrieve.
     *
     * @response 200 {
     *   "error": false,
     *   "data":  {
     *       "id": "project_2",
     *       "topic": "hola",
            "link": "https://dev-taskify.taskhub.company/projects/information/2",
            "isroot": true,
            "level": 1,
            "children": [
                {
                    "id": "tasks",
                    "topic": "Tasks",
                    "level": 2,
                    "children": [
                        {
                            "id": "task_4",
                            "topic": "Test",
                            "link": "https://dev-taskify.taskhub.company/tasks/information/4",
                            "children": [
                                {
                                    "id": "task_users_4",
                                    "topic": "Users",
                                    "children": [
                                        {
                                            "id": "task_user_4_1",
                                            "topic": "Admin User",
                                            "link": "https://dev-taskify.taskhub.company/users/profile/1"
                                        }
                                    ]
                                },
                                {
                                    "id": "task_clients_4",
                                    "topic": "Clients",
                                    "children": []
                                }
                            ]
                        },
                        {
                            "id": "task_5",
                            "topic": "test",
                            "link": "https://dev-taskify.taskhub.company/tasks/information/5",
                            "children": [
                                {
                                    "id": "task_users_5",
                                    "topic": "Users",
                                    "children": [
                                        {
                                            "id": "task_user_5_1",
                                            "topic": "Admin User",
                                            "link": "https://dev-taskify.taskhub.company/users/profile/1"
                                        }
                                    ]
                                },
                                {
                                    "id": "task_clients_5",
                                    "topic": "Clients",
                                    "children": []
                                }
                            ]
                        },
                        {
                            "id": "task_6",
                            "topic": "test 1",
                            "link": "https://dev-taskify.taskhub.company/tasks/information/6",
                            "children": [
                                {
                                    "id": "task_users_6",
                                    "topic": "Users",
                                    "children": [
                                        {
                                            "id": "task_user_6_1",
                                            "topic": "Admin User",
                                            "link": "https://dev-taskify.taskhub.company/users/profile/1"
                                        }
                                    ]
                                },
                                {
                                    "id": "task_clients_6",
                                    "topic": "Clients",
                                    "children": []
                                }
                            ]
                        }
                    ]
                },
                {
                    "id": "users",
                    "topic": "Users",
                    "children": [
                        {
                            "id": "user_1",
                            "topic": "Admin User",
                            "link": "https://dev-taskify.taskhub.company/users/profile/1"
                        }
                    ]
                },
                {
                    "id": "clients",
                    "topic": "Clients",
                    "children": []
                },
                {
                    "id": "milestones",
                    "topic": "Milestones",
                    "children": []
                },
                {
                    "id": "media",
                    "topic": "Media",
                    "children": []
                }
            ]
        }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Project not found."
     * }
     */
    public function getMindMapData($id)
    {
        $isApi = request()->get('isApi', false);

        try {
            $project = Project::with(['users', 'tasks.users', 'clients', 'milestones', 'media'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            if ($isApi) {
                return response()->json([
                    'error' => true,
                    'message' => 'Project not found.'
                ], 404);
            }
            if (!$project) {
                return response()->json([
                    'error' => true,
                    'message' => 'Project not found.'
                ], 404);
            }
        }

        $mindMapData = [
            'meta' => [
                'name' => $project->title,
                'author' => $project->created_by,
                'version' => '1.0'
            ],
            'format' => 'node_tree', // Specify format if required by your jsMind version
            'data' => [
                'id' => 'project_' . $project->id,
                'topic' => $project->title,
                'link' => route('projects.info', $project->id),
                'isroot' => true,
                'level' => 1,
                'children' => [
                    [
                        'id' => 'tasks',
                        'topic' => 'Tasks',
                        'level' => 2,
                        'children' => $project->tasks->map(function ($task) {
                            return [
                                'id' => 'task_' . $task->id,
                                'topic' => $task->title,
                                'link' => route('tasks.info', $task->id),
                                'children' => [
                                    [
                                        'id' => 'task_users_' . $task->id, // Make it unique with task ID
                                        'topic' => 'Users',
                                        'children' => $task->users->map(function ($user) use ($task) {
                                            return [
                                                'id' => 'task_user_' . $task->id . '_' . $user->id, // Unique ID
                                                'topic' => $user->first_name . ' ' . $user->last_name,
                                                'link' => route('users.profile', $user->id)
                                            ];
                                        })->toArray()
                                    ],
                                    [
                                        'id' => 'task_clients_' . $task->id, // Make it unique with task ID
                                        'topic' => 'Clients',
                                        'children' => $task->project->clients->map(function ($client) use ($task) {
                                            return [
                                                'id' => 'task_client_' . $task->id . '_' . $client->id, // Unique ID
                                                'topic' => $client->first_name . ' ' . $client->last_name,
                                                'link' => route('clients.profile', $client->id)
                                            ];
                                        })->toArray()
                                    ]
                                ]
                            ];
                        })->toArray()
                    ],
                    [
                        'id' => 'users',
                        'topic' => 'Users',
                        'children' => $project->users->map(function ($user) {
                            return [
                                'id' => 'user_' . $user->id,
                                'topic' => $user->first_name . ' ' . $user->last_name,
                                'link' => route('users.profile', $user->id)
                            ];
                        })->toArray()
                    ],
                    [
                        'id' => 'clients',
                        'topic' => 'Clients',
                        'children' => $project->clients->map(function ($client) {
                            return [
                                'id' => 'client_' . $client->id,
                                'topic' => $client->first_name . ' ' . $client->last_name,
                                'link' => route('clients.profile', $client->id)
                            ];
                        })->toArray()
                    ],
                    [
                        'id' => 'milestones',
                        'topic' => 'Milestones',
                        'children' => $project->milestones->map(function ($milestone) {
                            return [
                                'id' => 'milestone_' . $milestone->id,
                                'topic' => $milestone->title
                            ];
                        })->toArray()
                    ],
                    [
                        'id' => 'media',
                        'topic' => 'Media',
                        'children' => $project->media->map(function ($mediaItem) {
                            $isPublicDisk = $mediaItem->disk == 'public' ? 1 : 0;
                            $fileUrl = $isPublicDisk
                                ? asset('storage/project-media/' . $mediaItem->file_name)
                                : $mediaItem->getFullUrl();
                            return [
                                'id' => 'media_' . $mediaItem->id,
                                'topic' => $mediaItem->file_name,
                                'data' => [
                                    'url' => $fileUrl
                                ],
                                'link' => $fileUrl
                            ];
                        })->toArray()
                    ],
                ]
            ]
        ];
        if ($isApi) {

            return response()->json(
                formatApiResponse(
                    false,
                    'Mind map data retrieved successfully.',
                    [
                        'data' => $mindMapData['data']
                    ],
                )
            );
        } else {
            return $mindMapData;
        }
    }
    public function ganttProjectsTasks(Request $request)
    {
        $favorite = $request->input('favorite');
        // Fetch projects based on admin/data access with tasks eagerly loaded
        $query = isAdminOrHasAllDataAccess() ? $this->workspace->projects()->with('tasks') : $this->user->projects()->with('tasks');
        // Apply favorite filter if necessary
        if ($favorite) {
            // Get the IDs of the projects marked as favorites by the user
            $favoriteProjectIds = $this->user->favoriteProjects()
                ->pluck('favoritable_id')  // Get the project IDs
                ->toArray();
            // Filter projects based on the favorite project IDs
            $query->whereIn('projects.id', $favoriteProjectIds);
        }
        // Get the projects
        $projects = $query->get();
        // Filter projects with valid start and end dates
        $filteredProjects = $projects->filter(function ($project) {
            return !is_null($project->start_date) && !is_null($project->end_date);
        });
        // Filter tasks within each project for valid start and due dates
        $filteredProjects->each(function ($project) {
            $project->tasks = $project->tasks->filter(function ($task) {
                return !is_null($task->start_date) && !is_null($task->due_date);
            });
        });
        return response()->json($filteredProjects->values());
    }
    protected function parseDate($dateString)
    {
        // Remove timezone abbreviation and parse the date
        $dateString = preg_replace('/\s\([^)]+\)$/', '', $dateString);
        try {
            $date = Carbon::parse($dateString);
            return $date->format('Y-m-d'); // Format to 'YYYY-MM-DD'
        } catch (\Exception $e) {
            return null;
        }
    }
    public function update_module_dates(Request $request)
    {
        $request->validate([
            'module' => 'required|array',
            'module.type' => 'required|string|in:project,task',
            'module.id' => 'required|integer',
            'start_date' => 'required|string',
            'end_date' => 'required|string',
        ]);
        $module = $request->input('module');
        // Preprocess and parse dates
        $startDateString = $request->input('start_date');
        $endDateString = $request->input('end_date');
        $startDate = $this->parseDate($startDateString);
        $endDate = $this->parseDate($endDateString);
        $request->validate([
            'start_date' => [
                'required',
                function ($attribute, $value, $fail) use ($startDate) {
                    if (!$startDate) {
                        $fail('The start date is not valid.');
                    }
                }
            ],
            'end_date' => [
                'required',
                function ($attribute, $value, $fail) use ($endDate, $startDate) {
                    if (!$endDate) {
                        $fail('The end date is not valid.');
                    } elseif ($endDate < $startDate) {
                        $fail('The end date must be after or equal to the start date.');
                    }
                }
            ],
        ]);
        if ($module['type'] == 'project') {
            $project = Project::find($module['id']);
            if ($project) {
                $project->start_date = $startDate;
                $project->end_date = $endDate;
                $project->save();
                return response()->json(['error' => false, 'message' => 'Project dates updated successfully.']);
            } else {
                return response()->json(['error' => true, 'message' => 'Project not found.']);
            }
        } elseif ($module['type'] == 'task') {
            $task = Task::find($module['id']);
            if ($task) {
                $task->start_date = $startDate;
                $task->due_date = $endDate;
                $task->save();
                return response()->json(['error' => false, 'message' => 'Task dates updated successfully.']);
            } else {
                return response()->json(['error' => true, 'message' => 'Task not found.']);
            }
        } else {
            return response()->json(['error' => true, 'message' => 'Unknown module type.']);
        }
    }
    /**
     * Get project status timeline.
     *
     * This endpoint retrieves the status change history of a project, sorted in descending order.
     *
     * @authenticated
     *
     * @group Project Management
     *
     * @urlParam id int required The ID of the project whose status timeline is to be retrieved.
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
     *   "message": "Project not found."
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
            $project = Project::findOrFail($id);
            $statusTimelines = $project->statusTimelines
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
                        'changed_time' => format_date(Carbon::parse($timeline->changed_at ?? null), false, to_format: 'H:i:s'),
                        'changed_at' => format_date(Carbon::parse($timeline->changed_at ?? null)),
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
                "message" => "Project not found."
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                "error" => true,
                "message" => "Could not retrieve status timelines."
            ], 500);
        }
    }

    public function calendar_view()
    {
        $customFields = CustomField::where('module', 'project')->get();
        $is_favorites = 0;
        return view('projects.calendar_view', compact('is_favorites', 'customFields'));
    }
    public function get_calendar_data(Request $request)
    {
        $start = $request->query('start');
        $end = $request->query('end');


        $projectsQuery = isAdminOrHasAllDataAccess() ? $this->workspace->projects() : $this->user->projects();

        // Apply date range filter with grouping
        // dd($start, $end, $projectsQuery->get());
        if ($start && $end) {
            $projectsQuery->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end]);
            });
        }
        // Retrieve the tasks
        $projects = $projectsQuery->get();


        // Format the tasks for FullCalendar
        $events = $projects->map(function ($project) {
            $backgroundColor = '#007bff';
            // Set the background color based on the task status
            switch ($project->status->color) {
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
            $title = $project->title . ' : ' . format_date($project->start_date);
            if ($project->end_date != $project->start_date) {
                $title .= ' ' . get_label('to', 'to') . ' ' . format_date($project->end_date);
            }
            return [
                'id' => $project->id,
                'project_info_url' => route('projects.info', ['id' => $project->id]),
                'title' => $title,
                'start' => $project->start_date,
                'end' => $project->end_date,
                'status_id' => $project->status_id,
                'priority_id' => $project->priority_id,
                'backgroundColor' => $backgroundColor,
                'borderColor' => '#ffffff',
                'textColor' => '#000000',
            ];
        });
        return response()->json($events);
    }
    public function updateProjectDates(Request $request)
    {
        $isApi = $request->get('isApi', false);
        // Validation rules for start and end dates
        $rules = [
            'id' => 'required|exists:projects,id',
            'start_date' => [
                'required',
                function ($attribute, $value, $fail) {
                    $endDate = request()->input('end_date');
                    $errors = validate_date_format_and_order($value, $endDate);
                    if (!empty($errors['start_date'])) {
                        foreach ($errors['start_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'end_date' => [
                'required',
                function ($attribute, $value, $fail) {
                    $startDate = request()->input('start_date');
                    $errors = validate_date_format_and_order($startDate, $value, endDateKey: 'end_date');
                    if (!empty($errors['end_date'])) {
                        foreach ($errors['end_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
        ];
        try {
            // Validate the request data
            $request->validate($rules);
            // Find the Project to be updated
            $project = Project::findOrFail($request->input('id'));
            // Update start and due dates
            $project->start_date = format_date($request->input('start_date'), false, app('php_date_format'), 'Y-m-d');
            $project->end_date = format_date($request->input('end_date'), false, app('php_date_format'), 'Y-m-d');
            // Save the updated project
            $project->save();
            return formatApiResponse(
                false,
                'Updated successfully.',
                [
                    'id' => $project->id,
                    'type' => 'project',
                    'data' => formatProject($project)
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

    public function getStatuses()
    {
        try {
            $statuses = Status::select('id', 'title', 'color')->get()->map(function ($status) {
                return [
                    'id' => $status->id,
                    'title' => $status->title ?? $status->name ?? 'Untitled',
                    'color' => $status->color ?? '#6c757d',
                ];
            });
            return response()->json([
                'error' => false,
                'statuses' => $statuses,
                'message' => 'Statuses retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in getStatuses: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while fetching statuses'
            ], 500);
        }
    }

    public function getPriorities()
    {
        try {
            $priorities = Priority::select('id', 'title', 'color')->get()->map(function ($priority) {
                return [
                    'id' => $priority->id,
                    'title' => $priority->title ?? $priority->name ?? 'Untitled',
                    'color' => $priority->color ?? '#6c757d',
                ];
            });
            return response()->json([
                'error' => false,
                'priorities' => $priorities,
                'message' => 'Priorities retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in getPriorities: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while fetching priorities'
            ], 500);
        }
    }
}
