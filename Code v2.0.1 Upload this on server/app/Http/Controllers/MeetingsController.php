<?php

namespace App\Http\Controllers;

use DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\UserClientPreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class MeetingsController extends Controller
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
        $meetings = isAdminOrHasAllDataAccess() ? $this->workspace->meetings : $this->user->meetings;
        return view('meetings.meetings', compact('meetings'));
    }

    /**
     * Create a new meeting.
     *
     * This endpoint creates a new meeting with the provided details. The user must be authenticated to perform this action. The request validates various fields, including title, start and end dates, start and end times, and participant IDs.
     *
     * @authenticated
     *
     * @group Meeting Management
     *
     * @bodyParam title string required The title of the meeting. Example: Project Kickoff
     * @bodyParam start_date string required The start date of the meeting in the format specified in the general settings. Example: 25-07-2024
     * @bodyParam end_date string required The end date of the meeting in the format specified in the general settings. Example: 25-07-2024
     * @bodyParam start_time string required The start time of the meeting in the format HH:MM. Example: 10:00
     * @bodyParam end_time string required The end time of the meeting in the format HH:MM. Example: 11:00
     * @bodyParam user_ids array nullable An array of user IDs to be assigned to the meeting. Example: [1, 2, 3]
     * @bodyParam client_ids array nullable An array of client IDs to be assigned to the meeting. Example: [4, 5]
     *
     * @response 200 {
     * "error": false,
     * "message": "Meeting created successfully.",
     * "id": 119,
     * "data": {
     *   "id": 119,
     *   "title": "From API",
     *   "start_date": "25-07-2024",
     *   "start_time": "15:00:00",
     *   "end_date": "25-08-2024",
     *   "end_time": "11:41:05",
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
     *   "status": "Ongoing",
     *   "created_at": "07-08-2024 17:11:05",
     *   "updated_at": "07-08-2024 17:11:05"
     * }
     * }
     *
     * @response 422 {
     *  "error": true,
     *  "message": "Validation errors occurred",
     *  "errors": {
     *    "title": ["The title field is required."],
     *    "start_date": ["The start date field is required."],
     *    ...
     *  }
     * }
     * @response 500 {
     *  "error": true,
     *  "message": "An error occurred while creating the meeting."
     * }
     */


    public function store(Request $request)
    {
        $isApi = request()->get('isApi', false);
        $rules = [
            'title' => 'required|string',
            'start_date' => [
                'required',
                function ($attribute, $value, $fail) use ($isApi) {
                    $endDate = request()->input('end_date');
                    $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);

                    // Check and handle errors for start_date specifically
                    if (!empty($errors['start_date'])) {
                        foreach ($errors['start_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'end_date' => [
                'required',
                function ($attribute, $value, $fail) use ($isApi) {
                    $startDate = request()->input('start_date');
                    $errors = validate_date_format_and_order($startDate, $value, $isApi ? 'Y-m-d' : null);

                    // Check and handle errors for end_date specifically
                    if (!empty($errors['end_date'])) {
                        foreach ($errors['end_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:clients,id',
        ];

        try {
            $formFields = $request->validate($rules);

            $start_date = $request->input('start_date');
            $start_time = $request->input('start_time');
            $end_date = $request->input('end_date');
            $end_time = $request->input('end_time');

            $formFields['start_date_time'] = format_date($start_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d', false) . ' ' . $start_time;
            $formFields['end_date_time'] = format_date($end_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d', false) . ' ' . $end_time;

            $formFields['workspace_id'] = getWorkspaceId();
            $formFields['user_id'] = getAuthenticatedUser()->id;
            $userIds = $request->input('user_ids', []);
            $clientIds = $request->input('client_ids', []);

            // Set creator as a participant automatically if !isAdminOrHasAllDataAccess
            if (!isAdminOrHasAllDataAccess()) {
                if (getGuardName() == 'client' && !in_array($this->user->id, $clientIds)) {
                    array_splice($clientIds, 0, 0, $this->user->id);
                } else if (getGuardName() == 'web' && !in_array($this->user->id, $userIds)) {
                    array_splice($userIds, 0, 0, $this->user->id);
                }
            }

            $new_meeting = Meeting::create($formFields);
            $meeting_id = $new_meeting->id;
            $meeting = Meeting::find($meeting_id);
            $meeting->users()->attach($userIds);
            $meeting->clients()->attach($clientIds);

            // Prepare notification data
            $notification_data = [
                'type' => 'meeting',
                'type_id' => $meeting_id,
                'type_title' => $meeting->title,
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
            return formatApiResponse(
                false,
                'Meeting created successfully.',
                [
                    'id' => $meeting_id,
                    'data' => formatMeeting($meeting)
                ]
            );
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while creating the meeting.'
            ], 500);
        }
    }

    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $statuses = request('statuses', []);
        $user_ids = request('user_ids', []);
        $client_ids = request('client_ids', []);
        $date_between_from = request('date_between_from') ?: "";
        $date_between_to = request('date_between_to') ?: "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $meetings = isAdminOrHasAllDataAccess() ? $this->workspace->meetings() : $this->user->meetings();
        if ($search) {
            $meetings = $meetings->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        if (!empty($user_ids)) {
            $meetings = $meetings->whereHas('users', function ($query) use ($user_ids) {
                $query->whereIn('users.id', $user_ids);
            });
        }

        if (!empty($client_ids)) {
            $meetings = $meetings->whereHas('clients', function ($query) use ($client_ids) {
                $query->whereIn('clients.id', $client_ids);
            });
        }
        if ($date_between_from && $date_between_to) {
            $date_between_from = $date_between_from . ' 00:00:00';
            $date_between_to = $date_between_to . ' 23:59:59';
            $meetings = $meetings->where('start_date_time', '>=', $date_between_from)
                ->where('end_date_time', '<=', $date_between_to);
        }
        if ($start_date_from && $start_date_to) {
            $start_date_from = $start_date_from . ' 00:00:00';
            $start_date_to = $start_date_to . ' 23:59:59';
            $meetings = $meetings->whereBetween('start_date_time', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $end_date_from = $end_date_from . ' 00:00:00';
            $end_date_to = $end_date_to . ' 23:59:59';
            $meetings = $meetings->whereBetween('end_date_time', [$end_date_from, $end_date_to]);
        }
        if (!empty($statuses)) {
            $meetings = $meetings->where(function ($query) use ($statuses) {
                if (in_array('ongoing', $statuses)) {
                    $query->orWhere(function ($q) {
                        $q->where('start_date_time', '<=', Carbon::now(config('app.timezone')))
                            ->where('end_date_time', '>=', Carbon::now(config('app.timezone')));
                    });
                }

                if (in_array('yet_to_start', $statuses)) {
                    $query->orWhere('start_date_time', '>', Carbon::now(config('app.timezone')));
                }

                if (in_array('ended', $statuses)) {
                    $query->orWhere('end_date_time', '<', Carbon::now(config('app.timezone')));
                }
            });
        }
        $totalmeetings = $meetings->count();

        $canCreate = checkPermission('create_meetings');
        $canEdit = checkPermission('edit_meetings');
        $canDelete = checkPermission('delete_meetings');

        $currentDateTime = Carbon::now(config('app.timezone'));
        $meetings = $meetings->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($meeting) use ($canEdit, $canDelete, $canCreate, $currentDateTime) {

            $currentDateTime = Carbon::now(config('app.timezone'));
            $meetingStartTime = \Carbon\Carbon::parse($meeting->start_date_time, config('app.timezone'));

            // Correct approach: Convert stored UTC times to local timezone for comparison
            $currentDateTime = Carbon::now(config('app.timezone')); // Current time in your timezone

            // Parse stored UTC times and convert to your timezone
            $meetingStart = Carbon::parse($meeting->start_date_time, config('app.timezone'));
            $meetingEnd = Carbon::parse($meeting->end_date_time, config('app.timezone'));

            if ($currentDateTime < $meetingStart) {
                $diff = $currentDateTime->diff($meetingStart);
                $status = 'Will start in ' . $diff->format('%a days %H hours %I minutes %S seconds');
            } elseif ($currentDateTime > $meetingEnd) {
                $diff = $meetingEnd->diff($currentDateTime);
                $status = 'Ended before ' . $diff->format('%a days %H hours %I minutes %S seconds');
            } else {
                $status = 'Ongoing';
            }

                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-meeting" data-id="' . $meeting->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $meeting->id . '" data-type="meetings" data-table="meetings_table">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                if ($canCreate) {
                    $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $meeting->id . '" data-title="' . $meeting->title . '" data-type="meetings" data-table="meetings_table" title="' . get_label('duplicate', 'Duplicate') . '">' .
                        '<i class="bx bx-copy text-warning mx-2"></i>' .
                        '</a>';
                }

                if ($status == 'Ongoing') {
                    $actions .= '<a href="' . url("/meetings/join/{$meeting->id}") . '" title="Join">' .
                        '<i class="bx bx-arrow-to-right text-success mx-3"></i>' .
                        '</a>';
                }

                $actions = $actions ?: '-';

                $userHtml = '';
                if (!empty($meeting->users) && count($meeting->users) > 0) {
                    $userHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($meeting->users as $user) {
                        $userHtml .= "<li class='avatar avatar-sm pull-up'><a href='" . url("/users/profile/{$user->id}") . "' title='{$user->first_name} {$user->last_name}'><img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    if ($canEdit) {
                        $userHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-meeting update-users-clients" data-id="' . $meeting->id . '"><span class="bx bx-edit"></span></a></li>';
                    }
                    $userHtml .= '</ul>';
                } else {
                    $userHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                    if ($canEdit) {
                        $userHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-meeting update-users-clients" data-id="' . $meeting->id . '">' .
                            '<span class="bx bx-edit"></span>' .
                            '</a>';
                    }
                }

                $clientHtml = '';
                if (!empty($meeting->clients) && count($meeting->clients) > 0) {
                    $clientHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($meeting->clients as $client) {
                        $clientHtml .= "<li class='avatar avatar-sm pull-up'><a href='" . url("/clients/profile/{$client->id}") . "' title='{$client->first_name} {$client->last_name}'><img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    if ($canEdit) {
                        $clientHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-meeting update-users-clients" data-id="' . $meeting->id . '"><span class="bx bx-edit"></span></a></li>';
                    }
                    $clientHtml .= '</ul>';
                } else {
                    $clientHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                    if ($canEdit) {
                        $clientHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-meeting update-users-clients" data-id="' . $meeting->id . '">' .
                            '<span class="bx bx-edit"></span>' .
                            '</a>';
                    }
                }

                return [
                    'id' => $meeting->id,
                    'title' => ($status == 'Ongoing')
                        ? '<a href="/meetings/join/' . $meeting->id . '" target="_blank" class="text-primary" title="Join">' .
                        '<i class="bx bx-arrow-to-right text-success mx-2"></i> ' . $meeting->title .
                        '</a>'
                        : $meeting->title,
                    'start_date_time' => format_date($meeting->start_date_time, true, null, null, false),
                    'end_date_time' => format_date($meeting->end_date_time, true, null, null, false),
                    'users' => $userHtml,
                    'clients' => $clientHtml,
                    'status' => $status,
                    'created_at' => format_date($meeting->created_at, true),
                    'updated_at' => format_date($meeting->updated_at, true),
                    'actions' => $actions
                ];
            });
        return response()->json([
            "rows" => $meetings->items(),
            "total" => $totalmeetings,
        ]);
    }

    /**
     * List or search meetings.
     *
     * This endpoint retrieves a list of meetings based on various filters. The user must be authenticated to perform this action. The request allows filtering by status, user, client, date ranges, and other parameters.
     *
     * @authenticated
     *
     * @group Meeting Management
     *
     * @urlParam id int optional The ID of the meeting to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter meetings by title or id. Example: Meeting
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, title, start_date_time, end_date_time, created_at, and updated_at. Example: title
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam status string optional The status of the meeting to filter by. Can be "ongoing", "ended", or "yet_to_start". Example: ongoing
     * @queryParam user_id int optional The user ID to filter meetings by. Example: 1
     * @queryParam client_id int optional The client ID to filter meetings by. Example: 5
     * @queryParam start_date_from string optional The start date range's start in YYYY-MM-DD format. Example: 2024-01-01
     * @queryParam start_date_to string optional The start date range's end in YYYY-MM-DD format. Example: 2024-12-31
     * @queryParam end_date_from string optional The end date range's start in YYYY-MM-DD format. Example: 2024-01-01
     * @queryParam end_date_to string optional The end date range's end in YYYY-MM-DD format. Example: 2024-12-31
     * @queryParam limit int optional The number of meetings per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Meetings retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 351,
     *       "title": "Project Kickoff",
     *       "start_date": "2024-07-01",
     *       "start_time": "10:00:00",
     *       "end_date": "2024-07-01",
     *       "end_time": "11:00:00",
     *       "users": [
     *         {
     *           "id": 7,
     *           "first_name": "Madhavan",
     *           "last_name": "Vaidya",
     *           "photo": "https://test-taskify.infinitietech.com/storage/photos/yxNYBlFLALdLomrL0JzUY2USPLILL9Ocr16j4n2o.png"
     *         }
     *       ],
     *       "clients": [],
     *       "status": "Ongoing",
     *       "created_at": "14-06-2024 17:50:09",
     *       "updated_at": "17-06-2024 19:08:16"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Meeting not found",
     *   "total": 0,
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Meetings not found",
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
        $start_date_from = $request->input('start_date_from', '');
        $start_date_to = $request->input('start_date_to', '');
        $end_date_from = $request->input('end_date_from', '');
        $end_date_to = $request->input('end_date_to', '');
        $limit = $request->input('limit', 10); // default limit
        $offset = $request->input('offset', 0); // default offset

        if ($id) {
            $meeting = Meeting::find($id);
            if (!$meeting) {
                return formatApiResponse(
                    false,
                    'Meeting not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            } else {
                return formatApiResponse(
                    false,
                    'Meeting retrieved successfully',
                    [
                        'total' => 1,
                        'data' => [formatMeeting($meeting)]
                    ]
                );
            }
        } else {
            $meetingsQuery = isAdminOrHasAllDataAccess() ? $this->workspace->meetings() : $this->user->meetings();

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
                $meetingsQuery = $user->meetings();
            }
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
                $meetingsQuery = $client->meetings();
            }
            if ($start_date_from && $start_date_to) {
                $start_date_from = $start_date_from . ' 00:00:00';
                $start_date_to = $start_date_to . ' 23:59:59';
                $meetingsQuery->whereBetween('start_date_time', [$start_date_from, $start_date_to]);
            }
            if ($end_date_from && $end_date_to) {
                $end_date_from = $end_date_from . ' 00:00:00';
                $end_date_to = $end_date_to . ' 23:59:59';
                $meetingsQuery->whereBetween('end_date_time', [$end_date_from, $end_date_to]);
            }
            if ($status) {
                if ($status === 'ongoing') {
                    $meetingsQuery->where('start_date_time', '<=', Carbon::now(config('app.timezone')))
                        ->where('end_date_time', '>=', Carbon::now(config('app.timezone')));
                } elseif ($status === 'yet_to_start') {
                    $meetingsQuery->where('start_date_time', '>', Carbon::now(config('app.timezone')));
                } elseif ($status === 'ended') {
                    $meetingsQuery->where('end_date_time', '<', Carbon::now(config('app.timezone')));
                }
            }
            $meetingsQuery->when($search, function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });

            $total = $meetingsQuery->count(); // get total count before applying offset and limit

            $meetings = $meetingsQuery->orderBy($sort, $order)
                ->skip($offset)
                ->take($limit)
                ->get();

            if ($meetings->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Meetings not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }
            $data = $meetings->map(function ($meeting) {
                return formatMeeting($meeting);
            });

            return formatApiResponse(
                false,
                'Meetings retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }

    public function get($id)
    {
        $meeting = Meeting::with('users', 'clients')->findOrFail($id);

        $meeting->start_date = \Carbon\Carbon::parse($meeting->start_date_time)->format('Y-m-d');
        $meeting->start_time = \Carbon\Carbon::parse($meeting->start_date_time)->format('H:i:s');
        $meeting->end_date = \Carbon\Carbon::parse($meeting->end_date_time)->format('Y-m-d');
        $meeting->end_time = \Carbon\Carbon::parse($meeting->end_date_time)->format('H:i:s');

        return response()->json(['error' => false, 'meeting' => $meeting]);
    }

    /**
     * Update an existing meeting.
     *
     * This endpoint updates an existing meeting with the provided details. The user must be authenticated to perform this action. The request validates various fields, including title, dates, and times.
     *
     * @authenticated
     *
     * @group Meeting Management
     *
     * @bodyParam id int required The ID of the meeting to update. Example: 1
     * @bodyParam title string required The title of the meeting. Example: Updated Meeting Title
     * @bodyParam start_date string required The start date of the meeting in the format specified in the general settings. Example: 2024-08-01
     * @bodyParam end_date string required The end date of the meeting in the format specified in the general settings. Example: 2024-08-31
     * @bodyParam start_time string required The start time of the meeting. Example: 09:00
     * @bodyParam end_time string required The end time of the meeting. Example: 10:00
     * @bodyParam user_ids array|null optional Array of user IDs to be associated with the meeting. Example: [2, 3]
     * @bodyParam client_ids array|null optional Array of client IDs to be associated with the meeting. Example: [5, 6]
     *
     * @response 200 {
     * "error": false,
     * "message": "Meeting updated successfully.",
     * "id": 119,
     * "data": {
     *   "id": 119,
     *   "title": "From API",
     *   "start_date": "25-07-2024",
     *   "start_time": "15:00:00",
     *   "end_date": "25-08-2024",
     *   "end_time": "11:45:15",
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
     *   "status": "Ongoing",
     *   "created_at": "07-08-2024 17:11:05",
     *   "updated_at": "07-08-2024 17:15:15"
     * }

     * }
     *
     * @response 422 {
     *   "error": true,
     *   "message": "Validation errors occurred",
     *   "errors": {
     *     "id": [
     *       "The meeting ID is required.",
     *       "The meeting ID does not exist in our records."
     *     ],
     *     "start_date": [
     *       "The start date must be before or equal to the end date."
     *     ],
     *     "start_time": [
     *       "The start time field is required."
     *     ],
     *     "end_time": [
     *       "The end time field is required."
     *     ]
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while updating the meeting."
     * }
     */
    public function update(Request $request)
    {
        $isApi = request()->get('isApi', false);
        $rules = [
            'id' => 'required|exists:meetings,id',
            'title' => 'required',
            'start_date' => [
                'required',
                function ($attribute, $value, $fail) use ($isApi) {
                    $endDate = request()->input('end_date');
                    $errors = validate_date_format_and_order($value, $endDate, $isApi ? 'Y-m-d' : null);

                    // Check and handle errors for start_date specifically
                    if (!empty($errors['start_date'])) {
                        foreach ($errors['start_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'end_date' => [
                'required',
                function ($attribute, $value, $fail) use ($isApi) {
                    $startDate = request()->input('start_date');
                    $errors = validate_date_format_and_order($startDate, $value, $isApi ? 'Y-m-d' : null);

                    // Check and handle errors for end_date specifically
                    if (!empty($errors['end_date'])) {
                        foreach ($errors['end_date'] as $error) {
                            $fail($error);
                        }
                    }
                },
            ],
            'start_time' => 'required',
            'end_time' => 'required',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id', // Validate that each user_id exists in the users table
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:clients,id', // Validate that each client_id exists in the clients table
        ];

        try {
            // Validate the request
            $formFields = $request->validate($rules);

            $id = $request->input('id');
            $start_date = $request->input('start_date');
            $start_time = $request->input('start_time');
            $end_date = $request->input('end_date');
            $end_time = $request->input('end_time');

            // Combine date and time fields
            $formFields['start_date_time'] = format_date($start_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d', false) . ' ' . $start_time;
            $formFields['end_date_time'] = format_date($end_date, false, $isApi ? 'Y-m-d' : app('php_date_format'), 'Y-m-d', false) . ' ' . $end_time;

            $userIds = $request->input('user_ids') ?? [];
            $clientIds = $request->input('client_ids') ?? [];

            // Find the meeting and update its details
            $meeting = Meeting::findOrFail($id);

            // Get current list of users and clients associated with the meeting
            $existingUserIds = $meeting->users->pluck('id')->toArray();
            $existingClientIds = $meeting->clients->pluck('id')->toArray();

            // Update meeting and its relationships
            $meeting->update($formFields);
            $meeting->users()->sync($userIds);
            $meeting->clients()->sync($clientIds);

            // Exclude old users and clients from receiving notification
            $userIds = array_diff($userIds, $existingUserIds);
            $clientIds = array_diff($clientIds, $existingClientIds);

            // Prepare notification data
            $notificationData = [
                'type' => 'meeting',
                'type_id' => $id,
                'type_title' => $meeting->title,
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
            processNotifications($notificationData, $recipients);
            $meeting = $meeting->fresh();
            return response()->json([
                'error' => false,
                'message' => 'Meeting updated successfully.',
                'id' => $meeting->id,
                'data' => formatMeeting($meeting)
            ]);
        } catch (ValidationException $e) {
            return formatApiValidationError($isApi, $e->errors());
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the meeting.'
            ], 500);
        }
    }

    /**
     * Remove the specified meeting.
     *
     * This endpoint deletes a meeting based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Meeting Management
     *
     * @urlParam id int required The ID of the meeting to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Meeting deleted successfully.",
     *   "id": 1,
     *   "title": "Meeting Title",
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Meeting not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the meeting."
     * }
     */

    public function destroy($id)
    {
        $meeting = Meeting::find($id);
        if ($meeting) {
            $response = DeletionService::delete(Meeting::class, $id, 'Meeting');
            $responseData = json_decode($response->getContent(), true);
            if ($responseData['error']) {
                // Handle error response
                return response()->json($responseData);
            }
            $meeting->notificationsForMeeting()->delete();
            return $response;
        } else {
            return formatApiResponse(
                true,
                'Meeting not found.',
                []
            );
        }
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:meetings,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedMeetings = [];
        $deletedMeetingTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $meeting = Meeting::find($id);
            if ($meeting) {
                $deletedMeetings[] = $id;
                $deletedMeetingTitles[] = $meeting->title;
                $meeting->notificationsForMeeting()->delete();
                DeletionService::delete(Meeting::class, $id, 'Meeting');
            }
        }

        return response()->json(['error' => false, 'message' => 'Meetings(s) deleted successfully.', 'id' => $deletedMeetings, 'titles' => $deletedMeetingTitles]);
    }

    public function join(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $currentDateTime = Carbon::now(config('app.timezone'));
        if ($currentDateTime < $meeting->start_date_time) {
            return redirect('/meetings')->with('error', 'Meeting is yet to start');
        } elseif ($currentDateTime > $meeting->end_date_time) {
            return redirect('/meetings')->with('error', 'Meeting has been ended');
        } else {
            if ($meeting->users->contains($this->user->id) || isAdminOrHasAllDataAccess()) {
                $is_meeting_admin = $this->user->id == $meeting['user_id'];
                $meeting_id = $meeting['id'];
                $room_name = $meeting['title'];
                $user_email = $this->user->email;
                $user_display_name = $this->user->first_name . ' ' . $this->user->last_name;
                return view('meetings.join_meeting', compact('is_meeting_admin', 'meeting_id', 'room_name', 'user_email', 'user_display_name'));
            } else {
                return redirect('/meetings')->with('error', 'You are not authorized to join this meeting');
            }
        }
    }

    public function joinWebView(Request $request, $id)
    {
        if ($request->has('token')) {
            $token = $request->query('token');

            // Set the Authorization header with the token
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }
        if (Auth::guard('sanctum')->check()) {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();
        } else {
            // User is not authenticated
            return redirect('/');
        }
        $meeting = Meeting::findOrFail($id);
        $currentDateTime = Carbon::now(config('app.timezone'));
        if ($currentDateTime < $meeting->start_date_time) {
            return redirect('/meetings')->with('error', 'Meeting is yet to start');
        } elseif ($currentDateTime > $meeting->end_date_time) {
            return redirect('/meetings')->with('error', 'Meeting has been ended');
        } else {
            if ($meeting->users->contains($user->id) || isAdminOrHasAllDataAccess()) {
                $is_meeting_admin = $user->id == $meeting['user_id'];
                $meeting_id = $meeting['id'];
                $room_name = $meeting['title'];
                $user_email = $user->email;
                $user_display_name = $user->first_name . ' ' . $user->last_name;
                return view('meetings.join_meeting', compact('is_meeting_admin', 'meeting_id', 'room_name', 'user_email', 'user_display_name'));
            } else {
                return redirect('/meetings')->with('error', 'You are not authorized to join this meeting');
            }
        }
    }

    public function duplicate($id)
    {
        // Define the related tables for this meeting
        $relatedTables = ['users', 'clients']; // Include related tables as needed

        // Use the general duplicateRecord function
        $title = (request()->has('title') && !empty(trim(request()->title))) ? request()->title : '';
        $duplicateMeeting = duplicateRecord(Meeting::class, $id, $relatedTables, $title);
        if (!$duplicateMeeting) {
            return response()->json(['error' => true, 'message' => 'Meeting duplication failed.']);
        }
        return response()->json(['error' => false, 'message' => 'Meeting duplicated successfully.', 'id' => $id]);
    }

    // Calendar View for the Meetings

    public function calendar_view()
    {

        return view('meetings.calendar_view');
    }

    public function get_calendar_data(Request $request)
    {
        // Parse date range with proper timezone handling
        $start = $request->query('start')
            ? Carbon::parse($request->query('start'), config('app.timezone'))
            : Carbon::now(config('app.timezone'))->startOfMonth();

        $end = $request->query('end')
            ? Carbon::parse($request->query('end'), config('app.timezone'))
            : Carbon::now(config('app.timezone'))->endOfMonth();


        // Retrieve meetings based on user access
        $meetingsQuery = isAdminOrHasAllDataAccess()
            ? $this->workspace->meetings()
            : $this->user->meetings();



        // Apply date range filter
        $meetings = $meetingsQuery->where(function ($query) use ($start, $end) {
            $query->whereBetween('start_date_time', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->orWhereBetween('end_date_time', [$start->toDateTimeString(), $end->toDateTimeString()]);
        })->get();


        // Current time for status calculations
        $currentDateTime = Carbon::now(config('app.timezone'));

        // Format meetings for FullCalendar
        $events = $meetings->map(function ($meeting) use ($currentDateTime) {
            $startTime = Carbon::parse($meeting->start_date_time, config('app.timezone'));
            $endTime = Carbon::parse($meeting->end_date_time, config('app.timezone'));

            // Determine meeting status and styling
            if ($currentDateTime < $startTime) {
                $status = 'Upcoming';
                $backgroundColor = '#9bafff'; // Blue
                $borderColor = '#0056b3';
                $textColor = '#000000';
                $description = 'Starts in ' . $this->formatTimeRemaining($currentDateTime->diff($startTime));
            } elseif ($currentDateTime > $endTime) {
                $status = 'Ended';
                $backgroundColor = '#FF8080'; // Red
                $borderColor = '#495057';
                $textColor = '#000000';
                $description = 'Ended ' . $this->formatTimeRemaining($endTime->diff($currentDateTime)) . ' ago';
            } else {
                $status = 'Ongoing';
                $backgroundColor = '#a0e4a3'; // Green
                $borderColor = '#1e7e34';
                $textColor = '#000000';
                $description = 'Currently in progress';
            }

            return [
                'id' => $meeting->id,
                'title' => $meeting->title . ' (' . $status . ')',
                'start' => $startTime->toIso8601String(),
                'end' => $endTime->toIso8601String(),
                'url' => route('meetings.join', ['id' => $meeting->id]),
                'backgroundColor' => $backgroundColor,
                'borderColor' => $borderColor,
                'textColor' => $textColor,
                'description' => $description,
                'allDay' => $meeting->is_all_day ?? false,
                'extendedProps' => [
                    'status' => $status,
                    'organizer' => $meeting->organizer->name ?? 'Unknown',
                    'location' => $meeting->location ?? null,
                ]
            ];
        });

        return response()->json($events);
    }

    /**
     * Format a DateInterval into a human-readable string
     *
     * @param \DateInterval $interval
     * @return string
     */
    private function formatTimeRemaining(\DateInterval $interval)
    {
        $parts = [];

        if ($interval->d > 0) {
            $parts[] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
        }

        if ($interval->h > 0) {
            $parts[] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
        }

        if ($interval->i > 0) {
            $parts[] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
        }

        // Only show seconds if less than an hour remains
        if (empty($parts) || ($interval->d == 0 && $interval->h == 0)) {
            $parts[] = $interval->s . ' second' . ($interval->s > 1 ? 's' : '');
        }

        return implode(', ', $parts);
    }

    public function saveViewPreference(Request $request)
    {
        $view = $request->input('view');
        $prefix = isClient() ? 'c_' : 'u_';
        if (
            UserClientPreference::updateOrCreate(
                ['user_id' => $prefix . $this->user->id, 'table_name' => 'meetings'],
                ['default_view' => $view]
            )
        ) {
            return response()->json(['error' => false, 'message' => 'Default View Set Successfully.']);
        } else {
            return response()->json(['error' => true, 'message' => 'Something Went Wrong.']);
        }
    }

}
