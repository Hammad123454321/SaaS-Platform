<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Models\Workspace;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\UserClientPreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ActivityLogController extends Controller
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
    public function index(Request $request)
    {
        $types = getControllerNames();
        return view('activity_log.list', ['types' => $types]);
    }

    /**
     * List or search activity logs.
     *
     * This endpoint retrieves a list of activity logs based on various filters. The user must be authenticated to perform this action. The request allows filtering by date ranges, user, client, activity type, and other parameters.
     *
     * @authenticated
     *
     * @group Activity Log Management
     *
     * @urlParam id int optional The ID of the activity log to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter activity logs. Example: update
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, created_at, and updated_at. Example: created_at
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam date_from string optional The start date range's start in YYYY-MM-DD format. Example: 2024-01-01
     * @queryParam date_to string optional The end date range's end in YYYY-MM-DD format. Example: 2024-12-31
     * @queryParam user_id int optional The user ID to filter activity logs by. Example: 1
     * @queryParam client_id int optional The client ID to filter activity logs by. Example: 5
     * @queryParam activity string optional The activity type to filter by. Example: update
     * @queryParam type string optional The type of activity to filter by. Example: task
     * @queryParam type_id int optional The type ID to filter activity logs by. Example: 10
     * @queryParam limit int optional The number of logs per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Activity logs retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *   "id": 974,
     *   "actor_id": 183,
     *   "actor_name": "Girish Thacker",
     *   "actor_type": "User",
     *   "type_id": 31,
     *   "parent_type_id": "",
     *   "type": "Payslip",
     *   "parent_type": "",
     *   "type_title": "CTR-31",
     *   "parent_type_title": "",
     *   "activity": "Created",
     *   "message": "Girish Thacker created payslip PSL-31",
     *   "created_at": "06-08-2024 18:10:41",
     *   "updated_at": "06-08-2024 18:10:41"
     *     }
     *   ]
     * }
     * @response 200 {
     *   "error": true,
     *   "message": "Activity logs not found",
     *   "total": 0,
     *   "data": []
     * }
     */

    public function list($id = null)
    {

        // dd(request());
        $isApi = request()->get('isApi', false);
        $search = request('search', '');
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $date_from = (request('date_from')) ? request('date_from') : "";
        $date_to = (request('date_to')) ? request('date_to') : "";
        $user_ids = request('user_ids');
        $client_ids = request('client_ids');
        $activities = request('activities');
        $types = request('types');
        $type = (request('type')) ? request('type') : "";
        $type_id = (request('type_id')) ? request('type_id') : "";
        $where = ['activity_logs.workspace_id' => $this->workspace->id];
        $date_from = $date_from ? date('Y-m-d H:i:s', strtotime($date_from . ' 00:00:00')) : null;
        $date_to = $date_to ? date('Y-m-d H:i:s', strtotime($date_to . ' 23:59:59')) : null;
        $offset = (int) request('offset', 0);
        $limit = (int) request('limit', 10);
        $activity_log_query = ActivityLog::select(
            'activity_logs.*',
            DB::raw(
                '
            CASE
                WHEN activity_logs.actor_type = "user" THEN CONCAT(users.first_name, " ", users.last_name)
                WHEN activity_logs.actor_type = "client" THEN CONCAT(clients.first_name, " ", clients.last_name)
            END AS actor_name'
            ),
            DB::raw(
                "
    CASE
        WHEN activity_logs.type = 'allowance' THEN allowances.title
        WHEN activity_logs.type = 'user' THEN
            (
                SELECT CONCAT(first_name, ' ', last_name)
                FROM users
                WHERE id = activity_logs.type_id
            )
        WHEN activity_logs.type = 'client' THEN
            (
                SELECT CONCAT(first_name, ' ', last_name)
                FROM clients
                WHERE id = activity_logs.type_id
            )
        WHEN activity_logs.type = 'contract' THEN contracts.title
        WHEN activity_logs.type = 'contract_type' THEN contract_types.type
        WHEN activity_logs.type = 'deduction' THEN deductions.title
        WHEN activity_logs.type = 'note' THEN notes.title
        WHEN activity_logs.type = 'payment_method' THEN payment_methods.title
        WHEN activity_logs.type = 'project' THEN projects.title
        WHEN activity_logs.type = 'task' THEN tasks.title
        WHEN activity_logs.type = 'meeting' THEN meetings.title
        WHEN activity_logs.type = 'status' THEN statuses.title
        WHEN activity_logs.type = 'priority' THEN priorities.title
        WHEN activity_logs.type = 'tag' THEN tags.title
        WHEN activity_logs.type = 'todo' THEN todos.title
        WHEN activity_logs.type = 'workspace' THEN workspaces.title
        WHEN activity_logs.type = 'media' THEN media.file_name
        WHEN activity_logs.type = 'tax' THEN taxes.title
        WHEN activity_logs.type = 'unit' THEN units.title
        WHEN activity_logs.type = 'item' THEN items.title
        WHEN activity_logs.type = 'expense_type' THEN expense_types.title
        WHEN activity_logs.type = 'expense' THEN expenses.title
        WHEN activity_logs.type = 'milestone' THEN milestones.title
        ELSE '-'
    END AS type_title,
    CASE
    WHEN activity_logs.type = 'task' THEN 'Project'
    WHEN activity_logs.type = 'media' THEN activity_logs.parent_type
    WHEN activity_logs.type = 'milestone' THEN activity_logs.parent_type
    ELSE '-'
END AS parent_type,
CASE
WHEN activity_logs.type = 'task' THEN
    CASE
        WHEN activity_logs.parent_type_id IS NOT NULL THEN
            (SELECT title FROM projects WHERE id = activity_logs.parent_type_id)
        ELSE
            (SELECT title FROM projects WHERE id = tasks.project_id)
    END

    WHEN activity_logs.type = 'milestone' THEN
    (SELECT title FROM projects WHERE id = milestones.project_id)


WHEN activity_logs.type = 'media' THEN
    CASE
        WHEN activity_logs.parent_type = 'project' THEN
            CASE
                WHEN activity_logs.parent_type_id IS NOT NULL THEN
                    (SELECT title FROM projects WHERE id = activity_logs.parent_type_id)
                ELSE
                    (SELECT title FROM projects WHERE id = media.model_id)
            END
        WHEN activity_logs.parent_type = 'task' THEN
            CASE
                WHEN activity_logs.parent_type_id IS NOT NULL THEN
                    (SELECT title FROM tasks WHERE id = activity_logs.parent_type_id)
                ELSE
                    (SELECT title FROM tasks WHERE id = media.model_id)
            END
        ELSE '-'
    END
ELSE '-'
END AS parent_type_title,


        CASE
            WHEN activity_logs.type = 'task' THEN
                CASE
                    WHEN activity_logs.parent_type_id IS NOT NULL THEN
                    activity_logs.parent_type_id
                    ELSE
                    tasks.project_id
                END
                WHEN activity_logs.type = 'media' THEN
                CASE
                    WHEN activity_logs.parent_type_id IS NOT NULL THEN
                        activity_logs.parent_type_id
                    ELSE
                        media.model_id
                END
                WHEN activity_logs.type = 'milestone' THEN
                CASE
                    WHEN activity_logs.parent_type_id IS NOT NULL THEN
                        activity_logs.parent_type_id
                    ELSE
                        milestones.project_id
                END
            ELSE '-'
        END AS parent_type_id
    "
            )
        )->leftJoin('allowances', function ($join) {
            $join->on('activity_logs.type_id', '=', 'allowances.id')
                ->where('activity_logs.type', '=', 'allowance');
        })
            ->leftJoin('contract_types', function ($join) {
                $join->on('activity_logs.type_id', '=', 'contract_types.id')
                    ->where('activity_logs.type', '=', 'contract_type');
            })
            ->leftJoin('deductions', function ($join) {
                $join->on('activity_logs.type_id', '=', 'deductions.id')
                    ->where('activity_logs.type', '=', 'deduction');
            })
            ->leftJoin('notes', function ($join) {
                $join->on('activity_logs.type_id', '=', 'notes.id')
                    ->where('activity_logs.type', '=', 'note');
            })
            ->leftJoin('payment_methods', function ($join) {
                $join->on('activity_logs.type_id', '=', 'payment_methods.id')
                    ->where('activity_logs.type', '=', 'payment_method');
            })
            ->leftJoin('projects', function ($join) {
                $join->on('activity_logs.type_id', '=', 'projects.id')
                    ->where('activity_logs.type', '=', 'project');
            })
            ->leftJoin('tasks', function ($join) {
                $join->on('activity_logs.type_id', '=', 'tasks.id')
                    ->where('activity_logs.type', '=', 'task');
            })
            ->leftJoin('meetings', function ($join) {
                $join->on('activity_logs.type_id', '=', 'meetings.id')
                    ->where('activity_logs.type', '=', 'meeting');
            })
            ->leftJoin('statuses', function ($join) {
                $join->on('activity_logs.type_id', '=', 'statuses.id')
                    ->where('activity_logs.type', '=', 'status');
            })
            ->leftJoin('priorities', function ($join) {
                $join->on('activity_logs.type_id', '=', 'priorities.id')
                    ->where('activity_logs.type', '=', 'priority');
            })
            ->leftJoin('tags', function ($join) {
                $join->on('activity_logs.type_id', '=', 'tags.id')
                    ->where('activity_logs.type', '=', 'tag');
            })
            ->leftJoin('users', function ($join) {
                $join->on('activity_logs.actor_id', '=', 'users.id')
                    ->where('activity_logs.actor_type', '=', 'user');
            })
            ->leftJoin('clients', function ($join) {
                $join->on('activity_logs.actor_id', '=', 'clients.id')
                    ->where('activity_logs.actor_type', '=', 'client');
            })
            ->leftJoin('contracts', function ($join) {
                $join->on('activity_logs.type_id', '=', 'contracts.id')
                    ->where('activity_logs.type', '=', 'contract');
            })
            ->leftJoin('todos', function ($join) {
                $join->on('activity_logs.type_id', '=', 'todos.id')
                    ->where('activity_logs.type', '=', 'todo');
            })
            ->leftJoin('workspaces', function ($join) {
                $join->on('activity_logs.type_id', '=', 'workspaces.id')
                    ->where('activity_logs.type', '=', 'workspace');
            })
            ->leftJoin('media', function ($join) {
                $join->on('activity_logs.type_id', '=', 'media.id')
                    ->where('activity_logs.type', '=', 'media');
            })
            ->leftJoin('taxes', function ($join) {
                $join->on('activity_logs.type_id', '=', 'taxes.id')
                    ->where('activity_logs.type', '=', 'tax');
            })
            ->leftJoin('units', function ($join) {
                $join->on('activity_logs.type_id', '=', 'units.id')
                    ->where('activity_logs.type', '=', 'unit');
            })
            ->leftJoin('items', function ($join) {
                $join->on('activity_logs.type_id', '=', 'items.id')
                    ->where('activity_logs.type', '=', 'item');
            })
            ->leftJoin('expense_types', function ($join) {
                $join->on('activity_logs.type_id', '=', 'expense_types.id')
                    ->where('activity_logs.type', '=', 'expense_type');
            })
            ->leftJoin('milestones', function ($join) {
                $join->on('activity_logs.type_id', '=', 'milestones.id')
                    ->where('activity_logs.type', '=', 'milestone');
            })
            ->leftJoin('expenses', function ($join) {
                $join->on('activity_logs.type_id', '=', 'expenses.id')
                    ->where('activity_logs.type', '=', 'expense');
            });

        // if (Auth::guard('client')->check()) {
        //     $where['activity_logs.actor_id'] = $this->user->id;
        //     $where['activity_logs.actor_type'] = 'client';
        // } elseif (!isAdminOrHasAllDataAccess()) {
        //     $where['activity_logs.actor_id'] = $this->user->id;
        //     $where['activity_logs.actor_type'] = 'user';
        // }

        if (!empty($activities)) {
            // Handle multi-select activity filter
            $activity_log_query->whereIn('activity_logs.activity', $activities);
        }

        if (!empty($user_ids)) {
            // Handle multi-select user_id filter
            $activity_log_query->where(function ($query) use ($user_ids) {
                $query->whereIn('activity_logs.actor_id', $user_ids)
                    ->where('activity_logs.actor_type', 'user');
            });
        }

        if (!empty($client_ids)) {
            // Handle multi-select client_id filter
            $activity_log_query->where(function ($query) use ($client_ids) {
                $query->whereIn('activity_logs.actor_id', $client_ids)
                    ->where('activity_logs.actor_type', 'client');
            });
        }

        if ($type && $type_id) {
            $activity_log_query->where(function ($query) use ($type) {
                $query->where('activity_logs.type', $type)
                    ->orWhere('activity_logs.parent_type', $type);
            })
                ->where(function ($query) use ($type_id) {
                    $query->where('activity_logs.type_id', $type_id)
                        ->orWhere('activity_logs.parent_type_id', $type_id);
                });
        }
        if (!empty($types)) {
            $activity_log_query->whereIn('activity_logs.type', $types);
        }

        if ($date_from && $date_to) {
            $activity_log_query->whereBetween('activity_logs.created_at', [$date_from, $date_to]);
        }

        if ($search) {
            $activity_log_query->where(function ($query) use ($search) {
                $query->where('activity_logs.id', 'like', '%' . $search . '%')
                    ->orWhere('activity_logs.workspace_id', 'like', '%' . $search . '%')
                    ->orWhere('activity_logs.actor_id', 'like', '%' . $search . '%')
                    ->orWhere('activity_logs.actor_type', 'like', '%' . $search . '%')
                    ->orWhere('activity_logs.type_id', 'like', '%' . $search . '%')
                    ->orWhere('activity_logs.activity', 'like', '%' . $search . '%')
                    ->orWhere('activity_logs.activity', 'like', '%' . str_replace(' ', '_', $search) . '%')
                    ->orWhere('activity_logs.message', 'like', '%' . $search . '%')
                    ->orWhere('activity_logs.type', 'like', '%' . $search . '%');
            });
        }
        $activity_log_query = $activity_log_query->where($where);
        $total = $activity_log_query->count();
        $canDelete = checkPermission('delete_activity_log');

        $activity_log_query = $activity_log_query->orderBy($sort, $order);

        if ($id) {
            // If $id is provided, get the specific activity log
            $activity_log = $activity_log_query->find($id);

            if ($activity_log) {
                $user = $activity_log->actor_type == 'user' ? User::find($activity_log->actor_id) : Client::find($activity_log->actor_id);
                $activity_log_data = [
                    'id' => $activity_log->id,
                    'actor_id' => $activity_log->actor_id,
                    'actor_name' => $activity_log->actor_name,
                    'actor_type' => ucfirst($activity_log->actor_type),
                    'actor_profile' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg'),
                    'type_id' => $activity_log->type_id,
                    'parent_type_id' => $activity_log->parent_type_id,
                    'type' => ucfirst(str_replace('_', ' ', $activity_log->type)),
                    'parent_type' => ucfirst(str_replace('_', ' ', $activity_log->parent_type)),
                    'type_title' => $activity_log->type_title,
                    'parent_type_title' => $activity_log->parent_type_title,
                    'activity' => ucfirst(str_replace('_', ' ', $activity_log->activity)),
                    'message' => $activity_log->message,
                    'created_at' => $isApi
                        ? format_date($activity_log->created_at, to_format: 'Y-m-d')
                        : format_date($activity_log->created_at, true),
                    'updated_at' => $isApi
                        ? format_date($activity_log->updated_at, to_format: 'Y-m-d')
                        : format_date($activity_log->updated_at, true)
                ];
                if (!$isApi) {
                    $activity_log_data['actions'] = $canDelete ? '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $activity_log->id . '" data-type="activity-log" data-table="activity_log_table">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>' : '-';
                }

                if ($isApi) {
                    // For API response
                    return response()->json([
                        "error" => false,
                        "message" => "Activity log retrieved successfully",
                        "data" => array_map(function ($value) {
                            return $value === '-' ? '' : $value;
                        }, $activity_log_data)
                    ]);
                }

                return response()->json([
                    "row" => $activity_log_data
                ]);
            }

            return $isApi ? response()->json([
                "error" => false,
                "message" => "Activity log not found",
                "data" => []
            ]) : response()->json([
                            "error" => "Activity log not found"
                        ]);
        }

        // For paginated results
        // Clone the query to get the total count before applying offset and limit
        $totalCountQuery = clone $activity_log_query;
        $total = $totalCountQuery->count();

        // Apply offset and limit
        $activity_log_query = $activity_log_query->offset($offset)->limit($limit);

        // Fetch the results with the specified offset and limit
        $activity_log = $activity_log_query->get()->map(function ($activity_log) use ($canDelete, $isApi) {
            if ($activity_log->type == 'payslip') {
                $activity_log->type_title = get_label("payslip_id_prefix", "PSL-") . $activity_log->type_id;
            }
            if ($activity_log->type == 'estimate') {
                $activity_log->type_title = get_label("estimate_id_prefix", "ESTMT-") . $activity_log->type_id;
            }
            if ($activity_log->type == 'invoice') {
                $activity_log->type_title = get_label("invoice_id_prefix", "INVC-") . $activity_log->type_id;
            }
            if ($activity_log->type == 'payment') {
                $activity_log->type_title = get_label("payment_id", "Payment ID") . ' ' . $activity_log->type_id;
            }
            $user = $activity_log->actor_type == 'user' ? User::find($activity_log->actor_id) : Client::find($activity_log->actor_id);
            $activity_log_data = [
                'id' => $activity_log->id,
                'actor_id' => $activity_log->actor_id,
                'actor_name' => $isApi
                    ? $activity_log->actor_name
                    : (
                        ($activity_log->actor_type == 'user' && $this->user->can('manage_users') && $user)
                        ? '<div class="d-flex align-items-center">
                <div class="avatar avatar-sm pull-up" title="' . $activity_log->actor_name . '">
                    <a href="' . route('users.profile', ['id' => $user->id]) . '">
                        <img src="' . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . '" alt="Avatar" class="rounded-circle">
                    </a>
                </div>
                <div class="mx-2">
                    <h6 class="mb-1">' . $activity_log->actor_name . '</h6>
                </div>
            </div>'
                        : (
                            ($activity_log->actor_type == 'client' && $this->user->can('manage_clients') && $user)
                            ? '<div class="d-flex align-items-center">
                    <div class="avatar avatar-sm pull-up" title="' . $activity_log->actor_name . '">
                        <a href="' . route('clients.profile', ['id' => $user->id]) . '">
                            <img src="' . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . '" alt="Avatar" class="rounded-circle">
                        </a>
                    </div>
                    <div class="mx-2">
                        <h6 class="mb-1">' . $activity_log->actor_name . '</h6>
                    </div>
                </div>'
                            : $activity_log->actor_name
                        )
                    ),

                'actor_type' => ucfirst($activity_log->actor_type),
                'actor_profile' => $user ? ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) : null,
                'type_id' => $activity_log->type_id,
                'parent_type_id' => $activity_log->parent_type_id,
                'type' => ucfirst(str_replace('_', ' ', $activity_log->type)),
                'parent_type' => ucfirst(str_replace('_', ' ', $activity_log->parent_type)),
                'type_title' => $activity_log->type_title,
                'parent_type_title' => $activity_log->parent_type_title,
                'activity' => ucfirst(str_replace('_', ' ', $activity_log->activity)),
                'message' => $activity_log->message,
                'created_at' => $isApi
                    ? format_date($activity_log->created_at, to_format: 'Y-m-d')
                    : format_date($activity_log->created_at, true),
                'updated_at' => $isApi
                    ? format_date($activity_log->updated_at, to_format: 'Y-m-d')
                    : format_date($activity_log->updated_at, true)
            ];

            if (!$isApi) {
                $activity_log_data['actions'] = $canDelete ? '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $activity_log->id . '" data-type="activity-log" data-table="activity_log_table"><i class="bx bx-trash text-danger mx-1"></i></button>' : '-';
            }

            return $activity_log_data;
        });
        if ($isApi) {
            return response()->json([
                "error" => false,
                "message" => $activity_log->isEmpty() ? "Activity logs not found" : "Activity logs retrieved successfully",
                "total" => $total,
                "data" => array_map(function ($log) {
                    return array_map(function ($value) {
                        return $value === '-' ? '' : $value;
                    }, $log);
                }, $activity_log->toArray())
            ]);
        }

        return response()->json([
            "rows" => $activity_log,
            "total" => $total,
        ]);
    }

    /**
     * Remove the specified activity log.
     *
     * This endpoint deletes a activity log based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Activity Log Management
     *
     * @urlParam id int required The ID of the activity log to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Record deleted successfully.",
     *   "title": null,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Record not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the activity log."
     * }
     */

    public function destroy($id)
    {
        $response = DeletionService::delete(ActivityLog::class, $id, 'Record');
        return $response;
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:activity_logs,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            DeletionService::delete(ActivityLog::class, $id, 'Record');
        }

        return response()->json(['error' => false, 'message' => 'Record(s) deleted successfully.']);
    }
    public function calendar_view()
    {
        return view('activity_log.calendar_view');
    }

    public function get_calendar_data(Request $request)
    {

        $activity_logs = $this->list(); // Adjust limit dynamically
        $activity_logs = $activity_logs->original['rows'];
        // Define color codes for different activity types
        $colors = [
            'Created' => '#a0e4a3',   // Green
            'Updated' => '#ffca66',   // Yellow
            'Deleted' => '#ff6b5c',   // Red
            'Duplicated' => '#6ed4f0', // Cyan
            'Uploaded' => '#9bafff',  // Blue
            'Updated status' => '#6ed4f0', // Gray
            'Updated priority' => '#6ed4f0', // Gray
            'Signed' => '#aab0b8',   // Green
            'Unsigned' => '#4f5b67',
            'Stopped' => '#6ed4f0',
            'Started' => '#6ed4f0',
            'Paused' => '#6ed4f0',
        ];

        $calendarData = [];
        foreach ($activity_logs as $activity) {
            try {
                $format = app('php_date_format') . ' H:i:s'; // Use 24-hour format
                $carbonDate = \Carbon\Carbon::createFromFormat($format, $activity['created_at'])
                    ->toIso8601String();
            } catch (\Exception $e) {
                dd("Error parsing date:", $activity['created_at'], $e->getMessage());
            }


            $url = generateActivityUrl($activity);
            $calendarData[] = [
                'id' => $activity['id'],
                'title' => $activity['message'],
                'start' => $carbonDate,
                'end' => $carbonDate,
                'url' => $url,
                'backgroundColor' => $colors[$activity['activity']] ?? '#000000',
                'textColor' => '#000000',
                'allDay' => false,
                'type' => $activity['type'],
            ];
        }



        return response()->json($calendarData);
    }



    public function saveViewPreference(Request $request)
    {
        $view = $request->input('view');
        $prefix = isClient() ? 'c_' : 'u_';
        if (
            UserClientPreference::updateOrCreate(
                ['user_id' => $prefix . $this->user->id, 'table_name' => 'activity_logs'],
                ['default_view' => $view]
            )
        ) {
            return response()->json(['error' => false, 'message' => 'Default View Set Successfully.']);
        } else {
            return response()->json(['error' => true, 'message' => 'Something Went Wrong.']);
        }
    }
}