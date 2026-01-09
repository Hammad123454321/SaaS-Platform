<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Workspace;
use App\Models\LeaveEditor;
use Illuminate\Support\Str;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\UserClientPreference;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
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

        $leave_requests = is_admin_or_leave_editor() ? $this->workspace->leave_requests() : $this->user->leave_requests();
        $leaveEditors = User::whereHas('leaveEditors')->get();
        return view('leave_requests.list', ['leave_requests' => $leave_requests->count(), 'leaveEditors' => $leaveEditors, 'auth_user' => $this->user]);
    }


    /**
     * Create a new leave request.
     *
     * This endpoint creates a new leave request with the provided details. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Leave Request Management
     *
     * @bodyParam reason string required The reason for the leave. Example: Family function
     * @bodyParam from_date date required The start date of the leave in the format specified in the general settings. Example: 2024-08-05
     * @bodyParam to_date date required The end date of the leave in the format specified in the general settings. Example: 2024-08-01
     * @bodyParam from_time time required_if:partialLeave,on The start time of the leave in HH:MM format. Example: 09:00
     * @bodyParam to_time time required_if:partialLeave,on The end time of the leave in HH:MM format. Example: 17:00
     * @bodyParam status string nullable The status of the leave request. Can be 'pending', 'approved', or 'rejected'. Example: pending
     * @bodyParam leaveVisibleToAll string optional Set to 'on' if the leave should be visible to all users in the workspace. Example: on
     * @bodyParam visible_to_ids array The IDs of users who can see the leave if it is not visible to all. Example: [1, 2, 3]
     * @bodyParam user_id int The ID of the user requesting the leave. Only admins or leave editors can specify this. Example: 4
     * @bodyParam partialLeave string optional Set to 'on' if the leave is partial (specific times within a day). Example: on
     * @bodyParam comment string optional An optional comment that can only be set by admin or leave editor. Example: Approved due to exceptional circumstances
     *
     * @response 200 {
     * "error": false,
     * "message": "Leave request created successfully.",
     * "id": 187,
     * "type": "leave_request",
     * "data": {
     *   "id": 187,
     *   "user_name": "Madhavan Vaidya",
     *   "user_photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png",
     *   "action_by": null,
     *   "action_by_id": null,
     *   "from_date": "Wed, 07-08-2024",
     *   "to_date": "Wed, 07-08-2024",
     *   "type": "Full",
     *   "duration": "1 day",
     *   "reason": "Test",
     *   "status": "Pending",
     *   "visible_to": null,
     *   "created_at": "07-08-2024 18:31:28",
     *   "updated_at": "07-08-2024 18:31:28"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "reason": [
     *       "The reason field is required."
     *     ],
     *     "from_date": [
     *       "The from date field is required."
     *     ],
     *     "to_date": [
     *       "The to date field is required."
     *     ],
     *     "from_time": [
     *       "The from time field is required when partial leave is checked."
     *     ],
     *     "to_time": [
     *       "The to time field is required when partial leave is checked."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while creating the leave request."
     * }
     */

    public function store(Request $request)
    {
        $isApi = request()->get('isApi', false);
        $rules = [
            'reason' => ['required'],
            'from_date' => [
                'required',
                function ($attribute, $value, $fail) use ($isApi) {
                    $endDate = request()->input('to_date');
                    $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null, 'from date', startDateKey: 'from_date');

                    // Check and handle errors for from_date specifically
                    if (!empty($errors['from_date'])) {
                        foreach ($errors['from_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'to_date' => [
                'required',
                function ($attribute, $value, $fail) use ($isApi) {
                    $startDate = request()->input('from_date');
                    $errors = validate_date_format_and_order($startDate, $value, $isApi ? 'Y-m-d' : null, endDateLabel: 'to date', endDateKey: 'to_date');

                    // Check and handle errors for to_date specifically
                    if (!empty($errors['to_date'])) {
                        foreach ($errors['to_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'from_time' => ['required_if:partialLeave,on'],
            'to_time' => ['required_if:partialLeave,on'],
            'status' => ['nullable'],
            'user_id' => 'nullable|exists:users,id',
            'visible_to_ids.*' => 'exists:users,id',
            'comment' => ['nullable']
        ];
        $messages = [
            'from_time.required_if' => 'The from time field is required when partial leave is checked.',
            'to_time.required_if' => 'The to time field is required when partial leave is checked.',
        ];

        try {
            $formFields = $request->validate($rules, $messages);
            if (!$this->user->hasRole('admin') && $request->input('status') && $request->filled('status') && $request->input('status') == 'approved') {
                return response()->json(['error' => true, 'message' => 'You cannot approve your own leave request.']);
            }

            $from_date = $request->input('from_date');
            $to_date = $request->input('to_date');
            $formFields['from_date'] = format_date($from_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            $formFields['to_date'] = format_date($to_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');

            if (is_admin_or_leave_editor() && $request->input('status') && $request->filled('status') && $request->input('status') != 'pending') {
                $formFields['action_by'] = $this->user->id;
            }

            $formFields['workspace_id'] = $this->workspace->id;
            $formFields['user_id'] = is_admin_or_leave_editor() && $request->filled('user_id') ? $request->input('user_id') : $this->user->id;
            $formFields['comment'] = is_admin_or_leave_editor() && $request->filled('comment') ? $request->input('comment') : NULL;
            $leaveVisibleToAll = $request->input('leaveVisibleToAll') && $request->filled('leaveVisibleToAll') && $request->input('leaveVisibleToAll') == 'on' ? 1 : 0;
            $formFields['visible_to_all'] = $leaveVisibleToAll;
            if ($lr = LeaveRequest::create($formFields)) {
                if ($leaveVisibleToAll == 0) {
                    $visibleToUsers = $request->input('visible_to_ids', []);
                    $lr->visibleToUsers()->sync($visibleToUsers);
                }
                $lr = LeaveRequest::find($lr->id);
                $fromDate = Carbon::parse($lr->from_date);
                $toDate = Carbon::parse($lr->to_date);

                $fromDateDayOfWeek = $fromDate->format('D');
                $toDateDayOfWeek = $toDate->format('D');
                if ($lr->from_time && $lr->to_time) {
                    $duration = 0;
                    // Loop through each day
                    while ($fromDate->lessThanOrEqualTo($toDate)) {
                        // Create Carbon instances for the start and end times of the leave request for the current day
                        $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $lr->from_time);
                        $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $lr->to_time);

                        // Calculate the duration for the current day and add it to the total duration
                        $duration += $fromDateTime->diffInMinutes($toDateTime) / 60; // Duration in hours

                        // Move to the next day
                        $fromDate->addDay();
                    }
                } else {
                    // Calculate the inclusive duration in days
                    $duration = $fromDate->diffInDays($toDate) + 1;
                }

                $leaveType = $lr->from_time && $lr->to_time ? get_label('partial', 'Partial') : get_label('full', 'Full');
                $from = $fromDateDayOfWeek . ', ' . ($lr->from_time ? format_date($lr->from_date . ' ' . $lr->from_time, true, null, null, false) : format_date($lr->from_date));
                $to = $toDateDayOfWeek . ', ' . ($lr->to_time ? format_date($lr->to_date . ' ' . $lr->to_time, true, null, null, false) : format_date($lr->to_date));
                $duration = $lr->from_time && $lr->to_time ? $duration . ' hour' . ($duration > 1 ? 's' : '') : $duration . ' day' . ($duration > 1 ? 's' : '');
                // Fetch user details based on the user_id in the leave request
                $user = User::find($lr->user_id);

                // Prepare notification data
                $notificationData = [
                    'type' => 'leave_request_creation',
                    'type_id' => $lr->id,
                    'team_member_first_name' => $user->first_name,
                    'team_member_last_name' => $user->last_name,
                    'leave_type' => $leaveType,
                    'from' => $from,
                    'to' => $to,
                    'duration' => $duration,
                    'reason' => $lr->reason,
                    'comment' => $lr->comment ?? '-',
                    'status' => ucfirst($lr->status),
                    'action' => 'created'
                ];

                $workspaceUsers = $this->workspace->users->pluck('id')->toArray();

                // Determine recipients
                $adminModelIds = DB::table('model_has_roles')
                    ->select('model_id')
                    ->where('role_id', 1)
                    ->pluck('model_id')
                    ->toArray();

                $leaveEditorIds = DB::table('leave_editors')
                    ->pluck('user_id')
                    ->toArray();

                $adminInWorkspace = array_intersect($adminModelIds, $workspaceUsers);
                $leaveEditorsInWorkspace = array_intersect($leaveEditorIds, $workspaceUsers);

                // Combine admin model_ids and leave_editor_ids
                $adminIds = array_map(function ($modelId) {
                    return 'u_' . $modelId;
                }, $adminInWorkspace);

                $leaveEditorIdsWithPrefix = array_map(function ($leaveEditorId) {
                    return 'u_' . $leaveEditorId;
                }, $leaveEditorsInWorkspace);

                // Combine admin and leave editor ids
                $recipients = array_merge($adminIds, $leaveEditorIdsWithPrefix);

                processNotifications($notificationData, $recipients);

                if ($lr->status == 'approved') {
                    // Get the timezone from the application configuration
                    $appTimezone = config('app.timezone');

                    // Get current date and time with the application's timezone
                    $currentDateTime = new \DateTime('now', new \DateTimeZone($appTimezone));

                    // Combine to_date and to_time into a single DateTime object with the application's timezone
                    $leaveEndDate = new \DateTime($lr->to_date, new \DateTimeZone($appTimezone));
                    if ($lr->to_time) {
                        // If to_time is available, set the time part of the DateTime object
                        $leaveEndDate->setTime((int) substr($lr->to_time, 0, 2), (int) substr($lr->to_time, 3, 2));
                    } else {
                        // If to_time is not available, set the end of the day
                        $leaveEndDate->setTime(23, 59, 59);
                    }

                    // Ensure both DateTime objects are in the same timezone
                    $leaveEndDate->setTimezone(new \DateTimeZone($appTimezone));

                    // Check if the leave end date and time have not passed
                    if ($currentDateTime < $leaveEndDate) {
                        if ($lr->visible_to_all == 1) {
                            $recipientTeamMembers = $this->workspace->users->pluck('id')->toArray();
                        } else {
                            $recipientTeamMembers = $lr->visibleToUsers->pluck('id')->toArray();
                            $recipientTeamMembers = array_merge($adminInWorkspace, $leaveEditorsInWorkspace, $recipientTeamMembers);
                        }

                        //Exclude requestee from alert
                        $recipientTeamMembers = array_diff($recipientTeamMembers, [$lr->user_id]);

                        $recipientTeamMemberIds = array_map(function ($userId) {
                            return 'u_' . $userId;
                        }, $recipientTeamMembers);

                        $notificationData = [
                            'type' => 'team_member_on_leave_alert',
                            'type_id' => $lr->id,
                            'team_member_first_name' => $user->first_name,
                            'team_member_last_name' => $user->last_name,
                            'leave_type' => $leaveType,
                            'from' => $from,
                            'to' => $to,
                            'duration' => $duration,
                            'reason' => $lr->reason,
                            'action' => 'team_member_on_leave_alert'
                        ];
                        processNotifications($notificationData, $recipientTeamMemberIds);
                    }
                }
                $leaveRequest = LeaveRequest::find($lr->id);
                $partialLeave = $request->input('partialLeave') && $request->filled('partialLeave') && $request->input('partialLeave') == 'on' ? 'on' : 'off';
                $leaveRequest->$leaveVisibleToAll = $leaveVisibleToAll ? 'on' : 'off';
                $leaveRequest->$partialLeave = $partialLeave;
                return formatApiResponse(
                    false,
                    'Leave request created successfully.',
                    [
                        'id' => $lr->id,
                        'type' => 'leave_request',
                        'data' => formatLeaveRequest($leaveRequest)
                    ]
                );
            } else {
                return response()->json(['error' => true, 'message' => 'Leave request couldn\'t be created.']);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the leave request.'
            ], 500);
        }
    }


    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $user_ids = request('user_ids');
        $action_by_ids = request('action_by_ids');
        $types = request('types');
        $statuses = request('statuses');
        $date_between_from = request('date_between_from') ?: "";
        $date_between_to = request('date_between_to') ?: "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $where = ['workspace_id' => $this->workspace->id];

        if (!is_admin_or_leave_editor()) {
            // If the user is not an admin or leave editor, filter by user_id
            $where['user_id'] = $this->user->id;
        }

        $leave_requests = LeaveRequest::select(
            'leave_requests.*',
            'users.photo AS user_photo',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name'),
            DB::raw('CONCAT(action_users.first_name, " ", action_users.last_name) AS action_by_name')
        )
            ->leftJoin('users', 'leave_requests.user_id', '=', 'users.id')
            ->leftJoin('users AS action_users', 'leave_requests.action_by', '=', 'action_users.id');

        if (!empty($user_ids)) {
            $leave_requests = $leave_requests->whereIn('user_id', $user_ids);
        }

        if (!empty($action_by_ids)) {
            $leave_requests = $leave_requests->whereIn('action_by', $action_by_ids);
        }

        if (!empty($statuses)) {
            $leave_requests = $leave_requests->whereIn('leave_requests.status', $statuses);
        }

        if (!empty($types)) {
            $leave_requests = $leave_requests->where(function ($query) use ($types) {
                if (in_array('full', $types)) {
                    $query->orWhereNull('from_time')->whereNull('to_time');
                }
                if (in_array('partial', $types)) {
                    $query->orWhereNotNull('from_time')->whereNotNull('to_time');
                }
            });
        }
        if ($date_between_from && $date_between_to) {
            $leave_requests = $leave_requests->where('from_date', '>=', $date_between_from)
                ->where('to_date', '<=', $date_between_to);
        }
        if ($start_date_from && $start_date_to) {
            $leave_requests = $leave_requests->whereBetween('from_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $leave_requests = $leave_requests->whereBetween('to_date', [$end_date_from, $end_date_to]);
        }
        if ($search) {
            $leave_requests = $leave_requests->where(function ($query) use ($search) {
                $query->where('reason', 'like', '%' . $search . '%')
                    ->orWhere('leave_requests.id', 'like', '%' . $search . '%');
            });
        }

        $leave_requests->where($where);
        $total = $leave_requests->count();

        $isAdmin = $this->user->hasRole('admin');
        $isAdminOrLeaveEditor = is_admin_or_leave_editor();

        $leave_requests = $leave_requests->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($leave_request) use ($isAdmin, $isAdminOrLeaveEditor) {
                // Calculate the duration in hours if both from_time and to_time are provided
                $fromDate = Carbon::parse($leave_request->from_date);
                $toDate = Carbon::parse($leave_request->to_date);

                $fromDateDayOfWeek = $fromDate->format('D');
                $toDateDayOfWeek = $toDate->format('D');

                if ($leave_request->from_time && $leave_request->to_time) {
                    $duration = 0;
                    // Loop through each day
                    while ($fromDate->lessThanOrEqualTo($toDate)) {
                        // Create Carbon instances for the start and end times of the leave request for the current day
                        $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leave_request->from_time);
                        $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leave_request->to_time);

                        // Calculate the duration for the current day and add it to the total duration
                        $duration += $fromDateTime->diffInMinutes($toDateTime) / 60; // Duration in hours

                        // Move to the next day
                        $fromDate->addDay();
                    }
                } else {
                    // Calculate the inclusive duration in days
                    $duration = $fromDate->diffInDays($toDate) + 1;
                }

                // Format "from_date" and "to_date" with labels
                $formattedDates = $duration > 1 ? format_date($leave_request->from_date) . ' ' . get_label('to', 'To') . ' ' . format_date($leave_request->to_date) : format_date($leave_request->from_date);
                $statusBadges = [
                    'pending' => '<span class="badge bg-warning">' . get_label('pending', 'Pending') . '</span>',
                    'approved' => '<span class="badge bg-success">' . get_label('approved', 'Approved') . '</span>',
                    'rejected' => '<span class="badge bg-danger">' . get_label('rejected', 'Rejected') . '</span>',
                ];
                $statusBadge = $statusBadges[$leave_request->status] ?? '';

                if ($leave_request->visible_to_all == 1) {
                    $visibleTo = get_label('all', 'All');
                } else {
                    $visibleTo = $leave_request->visibleToUsers->isEmpty()
                        ? '-'
                        : $leave_request->visibleToUsers->map(function ($user) {
                            if ($this->user->can('manage_users')) {
                                // Render clickable link if permission exists
                                $profileLink = route('users.profile', ['id' => $user->id]);
                                return '<a href="' . $profileLink . '">' . $user->first_name . ' ' . $user->last_name . '</a>';
                            } else {
                                // Render plain text if no permission
                                return $user->first_name . ' ' . $user->last_name;
                            }
                        })->implode(', ');
                }

                $actions = '';
                if ($isAdmin || $leave_request->action_by === null) {
                    $actions .= '<a href="javascript:void(0);" class="edit-leave-request" data-bs-toggle="modal" data-bs-target="#edit_leave_request_modal" data-id=' . $leave_request->id . ' title=' . get_label('update', 'Update') . '><i class="bx bx-edit mx-1"></i></a>';
                }

                if ($isAdminOrLeaveEditor || $leave_request->status == 'pending') {
                    $actions .= '<button title=' . get_label('delete', 'Delete') . ' type="button" class="btn delete" data-id=' . $leave_request->id . ' data-type="leave-requests" data-table="lr_table">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                return [
                    'id' => $leave_request->id,
                    'user_name' => formatUserHtml($leave_request->user),
                    'action_by' => formatUserHtml(User::find($leave_request->action_by)),
                    'from_date' => $fromDateDayOfWeek . ', ' . ($leave_request->from_time ? format_date($leave_request->from_date . ' ' . $leave_request->from_time, true, null, null, false) : format_date($leave_request->from_date)),
                    'to_date' => $toDateDayOfWeek . ', ' . ($leave_request->to_time ? format_date($leave_request->to_date . ' ' . $leave_request->to_time, true, null, null, false) : format_date($leave_request->to_date)),
                    'type' => $leave_request->from_time && $leave_request->to_time ? '<span class="badge bg-info">' . get_label('partial', 'Partial') . '</span>' : '<span class="badge bg-primary">' . get_label('full', 'Full') . '</span>',
                    'duration' => $leave_request->from_time && $leave_request->to_time ? number_format($duration, 2) . ' hour' . ($duration > 1 ? 's' : '') : $duration . ' day' . ($duration > 1 ? 's' : ''),
                    'reason' => $leave_request->reason,
                    'comment' => $leave_request->comment,
                    'status' => $statusBadge,
                    'visible_to' => $visibleTo,
                    'created_at' => format_date($leave_request->created_at, true),
                    'updated_at' => format_date($leave_request->updated_at, true),
                    'actions' => $actions ? $actions : '-'
                ];
            });
        return response()->json([
            "rows" => $leave_requests->items(),
            "total" => $total,
        ]);
    }

    /**
     * List or search leave requests.
     *
     * This endpoint retrieves a list of leave requests based on various filters. The user must be authenticated to perform this action. The request allows filtering by status, user, action_by, date ranges, type, and search term.
     *
     * @authenticated
     *
     * @group Leave Request Management
     *
     * @urlParam id int optional The ID of the leave request to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter leave requests by reason or id. Example: Vacation
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, from_date, to_date, type, reason, status, action_by_id, created_at, and updated_at. Example: id
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam status string optional The status of the leave request to filter by. Can be "pending", "approved", "rejected", etc. Example: pending
     * @queryParam user_id int optional The user ID to filter leave requests by. Example: 1
     * @queryParam action_by_id int optional The ID of the user who acted on the request to filter by. Example: 2
     * @queryParam start_date_from string optional The start date range's start in YYYY-MM-DD format. Example: 2024-01-01
     * @queryParam start_date_to string optional The start date range's end in YYYY-MM-DD format. Example: 2024-12-31
     * @queryParam end_date_from string optional The end date range's start in YYYY-MM-DD format. Example: 2024-01-01
     * @queryParam end_date_to string optional The end date range's end in YYYY-MM-DD format. Example: 2024-12-31
     * @queryParam type string optional The type of leave request. Can be "full" or "partial". Example: full
     * @queryParam limit int optional The number of leave requests per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Leave requests retrieved successfully",
     *   "total": 25,
     *   "data": [
     *     {
     *       "id": 175,
     *       "user_name": "Admin Test",
     *       "user_photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg",
     *       "action_by": null,
     *       "from_date": "Mon, 29-07-2024",
     *       "to_date": "Mon, 29-07-2024",
     *       "type": "Full",
     *       "duration": "1 day",
     *       "reason": "dsdsdsd",
     *       "status": "Pending",
     *       "visible_to": [
     *         {
     *           "id": 183,
     *           "first_name": "Girish",
     *           "last_name": "Thacker",
     *           "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
     *         }
     *       ],
     *       "created_at": "29-07-2024 10:02:45",
     *       "updated_at": "29-07-2024 10:02:45"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Leave request not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Leave requests not found",
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
        $action_by_id = $request->input('action_by_id', '');
        $start_date_from = $request->input('start_date_from', '');
        $start_date_to = $request->input('start_date_to', '');
        $end_date_from = $request->input('end_date_from', '');
        $end_date_to = $request->input('end_date_to', '');
        $type = $request->input('type', '');
        $limit = $request->input('limit', 10); // default limit
        $offset = $request->input('offset', 0); // default offset

        if ($id) {
            $leaveRequest = LeaveRequest::find($id);
            if (!$leaveRequest) {
                return formatApiResponse(
                    false,
                    'Leave request not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            } else {
                return formatApiResponse(
                    false,
                    'Leave request retrieved successfully',
                    [
                        'total' => 1,
                        'data' => [formatLeaveRequest($leaveRequest)]
                    ]
                );
            }
        }

        $leaveRequestsQuery = isAdminOrHasAllDataAccess() ? $this->workspace->leave_requests() : $this->user->leave_requests();

        if (!is_admin_or_leave_editor()) {
            // If the user is not an admin or leave editor, filter by user_id
            $leaveRequestsQuery->where('leave_requests.user_id', $this->user->id);
        }
        if ($status != '') {
            $leaveRequestsQuery->where('leave_requests.status', $status);
        }
        if ($user_id) {
            $leaveRequestsQuery->where('leave_requests.user_id', $user_id);
        }
        if ($action_by_id) {
            $leaveRequestsQuery->where('leave_requests.action_by', $action_by_id);
        }
        if ($start_date_from && $start_date_to) {
            $leaveRequestsQuery->whereBetween('leave_requests.from_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $leaveRequestsQuery->whereBetween('leave_requests.to_date', [$end_date_from, $end_date_to]);
        }
        if ($type) {
            if ($type == 'full') {
                $leaveRequestsQuery->whereNull('leave_requests.from_time')->whereNull('leave_requests.to_time');
            } elseif ($type == 'partial') {
                $leaveRequestsQuery->whereNotNull('leave_requests.from_time')->whereNotNull('leave_requests.to_time');
            }
        }
        if ($search) {
            $leaveRequestsQuery->where(function ($query) use ($search) {
                $query->where('leave_requests.reason', 'like', '%' . $search . '%')
                    ->orWhere('leave_requests.id', 'like', '%' . $search . '%');
            });
        }

        $total = $leaveRequestsQuery->count();

        $leaveRequests = $leaveRequestsQuery->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        if ($leaveRequests->isEmpty()) {
            return formatApiResponse(
                false,
                'Leave requests not found',
                [
                    'total' => 0,
                    'data' => []
                ]
            );
        }

        $data = $leaveRequests->map(function ($leaveRequest) {
            return formatLeaveRequest($leaveRequest);
        });

        return formatApiResponse(
            false,
            'Leave requests retrieved successfully',
            [
                'total' => $total,
                'data' => $data
            ]
        );
    }



    public function get($id)
    {
        $lr = LeaveRequest::with('user')->findOrFail($id);
        $visibleTo = $lr->visibleToUsers;
        return response()->json(['lr' => $lr, 'visibleTo' => $visibleTo]);
    }


    /**
     * Update an existing leave request.
     *
     * This endpoint updates an existing leave request with the provided details. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Leave Request Management
     *
     * @bodyParam id int required The ID of the leave request to be updated. Example: 1
     * @bodyParam reason string required The reason for the leave. Example: Family function
     * @bodyParam from_date date required The start date of the leave in the format specified in the general settings. Example: 2024-08-05
     * @bodyParam to_date date required The end date of the leave in the format specified in the general settings. Example: 2024-08-01
     * @bodyParam from_time time required_if:partialLeave,on The start time of the leave in HH:MM format. Example: 09:00
     * @bodyParam to_time time required_if:partialLeave,on The end time of the leave in HH:MM format. Example: 17:00
     * @bodyParam status string nullable The status of the leave request. Can be 'pending', 'approved', or 'rejected'. Example: pending
     * @bodyParam leaveVisibleToAll string optional Set to 'on' if the leave should be visible to all users in the workspace. Example: on
     * @bodyParam visible_to_ids array nullable The IDs of users who can see the leave if it is not visible to all. Example: [1, 2, 3]
     * @bodyParam partialLeave string optional Set to 'on' if the leave is partial (specific times within a day). Example: on
     * @bodyParam comment string optional An optional comment that can only be set by admin or leave editor. Example: Approved due to exceptional circumstances
     *
     * @response 200 {
     * "error": false,
     * "message": "Leave request updated successfully.",
     * "id": 187,
     * "type": "leave_request",
     * "data": {
     *   "id": 187,
     *   "user_name": "Madhavan Vaidya",
     *   "user_photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png",
     *   "action_by": null,
     *   "action_by_id": null,
     *   "from_date": "Wed, 07-08-2024",
     *   "to_date": "Wed, 07-08-2024",
     *   "type": "Full",
     *   "duration": "1 day",
     *   "reason": "Test",
     *   "status": "Pending",
     *   "visible_to": null,
     *   "created_at": "07-08-2024 18:31:28",
     *   "updated_at": "07-08-2024 18:31:28"
     * }
     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The id field is required.",
     *       "The selected id is invalid."
     *     ],
     *     "reason": [
     *       "The reason field is required."
     *     ],
     *     "from_date": [
     *       "The from date field is required."
     *     ],
     *     "to_date": [
     *       "The to date field is required."
     *     ],
     *     "from_time": [
     *       "The from time field is required when partial leave is checked."
     *     ],
     *     "to_time": [
     *       "The to time field is required when partial leave is checked."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the leave request."
     * }
     */

    public function update(Request $request)
    {
        $isApi = request()->get('isApi', false);
        $isAdminOrLe = is_admin_or_leave_editor();
        $rules = [
            'id' => 'required|exists:leave_requests,id', // Ensure the leave request exists
            'reason' => ['required'],
            'from_date' => [
                'required',
                function ($attribute, $value, $fail) use ($isApi) {
                    $endDate = request()->input('to_date');
                    $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null, 'from date', startDateKey: 'from_date');

                    // Check and handle errors for from_date specifically
                    if (!empty($errors['from_date'])) {
                        foreach ($errors['from_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'to_date' => [
                'required',
                function ($attribute, $value, $fail) use ($isApi) {
                    $startDate = request()->input('from_date');
                    $errors = validate_date_format_and_order($startDate, $value, $isApi ? 'Y-m-d' : null, endDateLabel: 'to date', endDateKey: 'to_date');

                    // Check and handle errors for to_date specifically
                    if (!empty($errors['to_date'])) {
                        foreach ($errors['to_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'from_time' => ['required_if:partialLeave,on'],
            'to_time' => ['required_if:partialLeave,on'],
            'status' => $isAdminOrLe ? 'required|in:pending,approved,rejected' : 'nullable|in:pending,approved,rejected',
            'visible_to_ids.*' => 'exists:users,id',
            'comment' => ['nullable']
        ];
        $messages = [
            'from_time.required_if' => 'The from time field is required when partial leave is checked.',
            'to_time.required_if' => 'The to time field is required when partial leave is checked.',
        ];
        try {
            $validatedData = $request->validate($rules, $messages);

            // Find the leave request by its ID
            $leaveRequest = LeaveRequest::findOrFail($validatedData['id']);
            $currentStatus = $leaveRequest->status;
            $newStatus = $validatedData['status'] ?? $currentStatus;

            if (!is_null($leaveRequest->action_by) && !$this->user->hasRole('admin')) {
                return response()->json([
                    'error' => true,
                    'message' => 'Once actioned only admin can update leave request.',
                ]);
            }

            if ($leaveRequest->user_id == $this->user->id && !$this->user->hasRole('admin') && $request->input('status') && $request->filled('status') && $request->input('status') == 'approved') {
                return response()->json([
                    'error' => true,
                    'message' => 'You can not approve own leave request.',
                ]);
            }

            if (in_array($currentStatus, ['approved', 'rejected']) && $newStatus == 'pending') {
                return response()->json([
                    'error' => true,
                    'message' => 'You cannot set the status to pending if it has already been approved or rejected.',
                ]);
            }

            $from_date = $request->input('from_date');
            $to_date = $request->input('to_date');
            $validatedData['from_date'] = format_date($from_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            $validatedData['to_date'] = format_date($to_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d');
            if ($newStatus != $currentStatus) {
                $validatedData['action_by'] = $this->user->id;
            }
            $leaveVisibleToAll = $request->input('leaveVisibleToAll') && $request->filled('leaveVisibleToAll') && $request->input('leaveVisibleToAll') == 'on' ? 1 : 0;
            $validatedData['visible_to_all'] = $leaveVisibleToAll;
            $validatedData['comment'] = is_admin_or_leave_editor() && $request->filled('comment') ? $request->input('comment') : NULL;
            // Update the status of the leave request
            if ($leaveRequest->update($validatedData)) {
                $leaveRequest = $leaveRequest->fresh();
                if ($leaveVisibleToAll == 0) {
                    // Sync the visibleToUsers with the provided visible_to_ids
                    $visibleToUsers = $request->input('visible_to_ids', []);
                    $leaveRequest->visibleToUsers()->sync($visibleToUsers);
                } else {
                    // Detach all users from the visibleToUsers relationship
                    $leaveRequest->visibleToUsers()->detach();
                }
                if ($newStatus != $currentStatus) {
                    $fromDate = Carbon::parse($leaveRequest->from_date);
                    $toDate = Carbon::parse($leaveRequest->to_date);

                    $fromDateDayOfWeek = $fromDate->format('D');
                    $toDateDayOfWeek = $toDate->format('D');
                    if ($leaveRequest->from_time && $leaveRequest->to_time) {
                        $duration = 0;
                        // Loop through each day
                        while ($fromDate->lessThanOrEqualTo($toDate)) {
                            // Create Carbon instances for the start and end times of the leave request for the current day
                            $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leaveRequest->from_time);
                            $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leaveRequest->to_time);

                            // Calculate the duration for the current day and add it to the total duration
                            $duration += $fromDateTime->diffInMinutes($toDateTime) / 60; // Duration in hours

                            // Move to the next day
                            $fromDate->addDay();
                        }
                    } else {
                        // Calculate the inclusive duration in days
                        $duration = $fromDate->diffInDays($toDate) + 1;
                    }

                    $leaveType = $leaveRequest->from_time && $leaveRequest->to_time ? get_label('partial', 'Partial') : get_label('full', 'Full');
                    $from = $fromDateDayOfWeek . ', ' . ($leaveRequest->from_time ? format_date($leaveRequest->from_date . ' ' . $leaveRequest->from_time, true, null, null, false) : format_date($leaveRequest->from_date));
                    $to = $toDateDayOfWeek . ', ' . ($leaveRequest->to_time ? format_date($leaveRequest->to_date . ' ' . $leaveRequest->to_time, true, null, null, false) : format_date($leaveRequest->to_date));
                    $duration = $leaveRequest->from_time && $leaveRequest->to_time ? $duration . ' hour' . ($duration > 1 ? 's' : '') : $duration . ' day' . ($duration > 1 ? 's' : '');
                    // Fetch user details based on the user_id in the leave request
                    $user = User::find($leaveRequest->user_id);

                    // Prepare notification data
                    $notificationData = [
                        'type' => 'leave_request_status_updation',
                        'type_id' => $leaveRequest->id,
                        'team_member_first_name' => $user->first_name,
                        'team_member_last_name' => $user->last_name,
                        'updater_first_name' => $this->user->first_name,
                        'updater_last_name' => $this->user->last_name,
                        'leave_type' => $leaveType,
                        'from' => $from,
                        'to' => $to,
                        'duration' => $duration,
                        'reason' => $leaveRequest->reason,
                        'comment' => $leaveRequest->comment ?? '-',
                        'old_status' => ucfirst($currentStatus),
                        'new_status' => ucfirst($newStatus),
                        'action' => 'status_updated'
                    ];
                    $workspaceUsers = $this->workspace->users->pluck('id')->toArray();
                    // Determine recipients
                    $adminModelIds = DB::table('model_has_roles')
                        ->select('model_id')
                        ->where('role_id', 1)
                        ->pluck('model_id')
                        ->toArray();

                    $leaveEditorIds = DB::table('leave_editors')
                        ->pluck('user_id')
                        ->toArray();

                    $adminInWorkspace = array_intersect($adminModelIds, $workspaceUsers);
                    $leaveEditorsInWorkspace = array_intersect($leaveEditorIds, $workspaceUsers);

                    // Combine admin model_ids and leave_editor_ids
                    $adminIds = array_map(function ($modelId) {
                        return 'u_' . $modelId;
                    }, $adminInWorkspace);

                    $leaveEditorIdsWithPrefix = array_map(function ($leaveEditorId) {
                        return 'u_' . $leaveEditorId;
                    }, $leaveEditorsInWorkspace);

                    $userWithPrefix = 'u_' . $leaveRequest->user_id;

                    // Combine admin and leave editor ids
                    $recipients = array_merge($adminIds, $leaveEditorIdsWithPrefix, [$userWithPrefix]);
                    processNotifications($notificationData, $recipients);

                    if ($newStatus == 'approved') {
                        // Get the timezone from the application configuration
                        $appTimezone = config('app.timezone');

                        // Get current date and time with the application's timezone
                        $currentDateTime = new \DateTime('now', new \DateTimeZone($appTimezone));

                        // Combine to_date and to_time into a single DateTime object with the application's timezone
                        $leaveEndDate = new \DateTime($leaveRequest->to_date, new \DateTimeZone($appTimezone));
                        if ($leaveRequest->to_time) {
                            // If to_time is available, set the time part of the DateTime object
                            $leaveEndDate->setTime((int) substr($leaveRequest->to_time, 0, 2), (int) substr($leaveRequest->to_time, 3, 2));
                        } else {
                            // If to_time is not available, set the end of the day
                            $leaveEndDate->setTime(23, 59, 59);
                        }

                        // Ensure both DateTime objects are in the same timezone
                        $leaveEndDate->setTimezone(new \DateTimeZone($appTimezone));

                        // Check if the leave end date and time have not passed
                        if ($currentDateTime < $leaveEndDate) {
                            if ($leaveRequest->visible_to_all == 1) {
                                $recipientTeamMembers = $this->workspace->users->pluck('id')->toArray();
                            } else {
                                $recipientTeamMembers = $leaveRequest->visibleToUsers->pluck('id')->toArray();
                                $recipientTeamMembers = array_merge($adminInWorkspace, $leaveEditorsInWorkspace, $recipientTeamMembers);
                            }

                            //Exclude requestee from alert
                            $recipientTeamMembers = array_diff($recipientTeamMembers, [$leaveRequest->user_id]);

                            $recipientTeamMemberIds = array_map(function ($userId) {
                                return 'u_' . $userId;
                            }, $recipientTeamMembers);

                            $notificationData = [
                                'type' => 'team_member_on_leave_alert',
                                'type_id' => $leaveRequest->id,
                                'team_member_first_name' => $user->first_name,
                                'team_member_last_name' => $user->last_name,
                                'leave_type' => $leaveType,
                                'from' => $from,
                                'to' => $to,
                                'duration' => $duration,
                                'reason' => $leaveRequest->reason,
                                'action' => 'team_member_on_leave_alert'
                            ];
                            processNotifications($notificationData, $recipientTeamMemberIds);
                        }
                    }
                }

                return formatApiResponse(
                    false,
                    'Leave request updated successfully.',
                    [
                        'id' => $leaveRequest->id,
                        'type' => 'leave_request',
                        'data' => formatLeaveRequest($leaveRequest)
                    ]
                );
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Leave request couldn\'t updated.'
                ]);
            }
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the leave request.'
            ], 500);
        }
    }

    public function update_editors(Request $request)
    {

        $userIds = $request->input('user_ids') ?? [];
        $currentLeaveEditorUserIds = LeaveEditor::pluck('user_id')->toArray();
        $usersToDetach = array_diff($currentLeaveEditorUserIds, $userIds);
        LeaveEditor::whereIn('user_id', $usersToDetach)->delete();
        foreach ($userIds as $assignedUserId) {
            // Check if a leave editor with the same user_id already exists
            $existingLeaveEditor = LeaveEditor::where('user_id', $assignedUserId)->first();

            if (!$existingLeaveEditor) {
                // Create a new LeaveEditor only if it doesn't exist
                $leaveEditor = new LeaveEditor();
                $leaveEditor->user_id = $assignedUserId;
                $leaveEditor->save();
            }
        }
        Session::flash('message', 'Leave editors updated successfully.');
        return response()->json(['error' => false]);
    }

    /**
     * Remove the specified leave request.
     *
     * This endpoint deletes a leave request item based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Leave Request Management
     *
     * @urlParam id int required The ID of the leave request to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Leave request deleted successfully.",
     *   "id": 1,
     *   "type": "leave_request",
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Leave request not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the leave request."
     * }
     */
    public function destroy($id)
    {
        $LeaveRequest = LeaveRequest::find($id);
        if ($LeaveRequest) {
            $response = DeletionService::delete(LeaveRequest::class, $id, 'Leave request');
            $responseData = json_decode($response->getContent(), true);
            if ($responseData['error']) {
                // Handle error response
                return response()->json($responseData);
            }
            $LeaveRequest->notificationsForLeaveRequest()->delete();
            return formatApiResponse(
                false,
                'Leave request deleted successfully.',
                [
                    'id' => $id,
                    'type' => 'leave_request',
                    'data' => []
                ]
            );
        } else {
            return formatApiResponse(
                true,
                'Leave request not found.',
                []
            );
        }
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:leave_requests,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $LeaveRequest = LeaveRequest::find($id);
            if ($LeaveRequest) {
                $deletedIds[] = $id;
                $LeaveRequest->notificationsForLeaveRequest()->delete();
                DeletionService::delete(LeaveRequest::class, $id, 'Leave request');
            }
        }

        return response()->json(['error' => false, 'message' => 'Leave request(s) deleted successfully.', 'id' => $deletedIds, 'type' => 'leave_request']);
    }

    public function saveViewPreference(Request $request)
    {
        $view = $request->input('view');
        $prefix = isClient() ? 'c_' : 'u_';
        if (
            UserClientPreference::updateOrCreate(
                ['user_id' => $prefix . $this->user->id, 'table_name' => 'leave_requests'],
                ['default_view' => $view]
            )
        ) {
            return response()->json(['error' => false, 'message' => 'Default View Set Successfully.']);
        } else {
            return response()->json(['error' => true, 'message' => 'Something Went Wrong.']);
        }
    }
    public function calendar_view()
    {
        return view('leave_requests.calendar_view');
    }
    public function get_calendar_data(Request $request)
    {
        // dd($request->all());
        // Parse date range with proper timezone handling
        $start = $request->query('date_from')
            ? format_date($request->query('date_from'), false, app('php_date_format'), 'Y-m-d')
            : Carbon::now()->startOfMonth();

        $end = $request->query('date_to')
            ? format_date($request->query('date_to'), false, app('php_date_format'), 'Y-m-d')
            : Carbon::now()->endOfMonth();

        // Retrieve leave requests based on user access
        $leaveRequestsQuery = isAdminOrHasAllDataAccess()
            ? $this->workspace->leave_requests()
            : $this->user->leave_requests();

        // dd($start, $end, $leaveRequestsQuery->get());
        // Apply date range filter
        $leave_requests = $leaveRequestsQuery->where(function ($query) use ($start, $end) {


            $query->whereBetween('from_date', [$start, $end])
                ->orWhereBetween('to_date', [$start, $end]);
        })->get();


        // Format leave request for FullCalendar
        $events = $leave_requests->map(function ($leave_request) {
            switch ($leave_request->status) {
                case 'approved':
                    $backgroundColor = '#4caf50';
                    $borderColor = '#4caf50';
                    $textColor = '#ffffff';
                    break;
                case 'pending':
                    $backgroundColor = '#ffeb3b';
                    $borderColor = '#ffeb3b';
                    $textColor = '#000000';
                    break;
                case 'rejected':
                    $backgroundColor = '#f44336';
                    $borderColor = '#f44336';
                    $textColor = '#ffffff';
                    break;
                default:
            }


            return [
                'id' => $leave_request->id,
                'title' => ucwords($leave_request->user->first_name . ' ' . $leave_request->user->last_name) . ' (' . ucwords($leave_request->status) . ')',
                'start' => $leave_request->from_date,
                'end' => $leave_request->to_date,
                'from_time' => $leave_request->from_time,
                'end_time' => $leave_request->to_time,
                'backgroundColor' => $backgroundColor,
                'borderColor' => $borderColor,
                'textColor' => $textColor,
                'description' => "
            <strong>Reason:</strong> " . ucwords(Str::limit($leave_request->reason, 20, '....')) . "<br>
            <strong>Status:</strong> " . ucfirst($leave_request->status) . "<br>
           <strong>From:</strong> " . format_date($leave_request->from_date) . " at " . ($leave_request->from_time ? date('H:i', strtotime($leave_request->from_time)) : '00:00') . "<br>
<strong>To:</strong> " . format_date($leave_request->to_date) . " at " . ($leave_request->to_time ? date('H:i', strtotime($leave_request->to_time)) : '24:00'),
                'allDay' => false,
                'extendedProps' => [
                    'status' => $leave_request->status,
                ]
            ];
        });

        return response()->json($events);
    }
}
