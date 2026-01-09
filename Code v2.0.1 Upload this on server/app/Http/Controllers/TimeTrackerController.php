<?php

namespace App\Http\Controllers;

use DB;
use Carbon\Carbon;
use App\Models\Workspace;
use App\Models\TimeTracker;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class TimeTrackerController extends Controller
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
        $timesheet = isAdminOrHasAllDataAccess() ? $this->workspace->timesheets : $this->user->timesheets;
        return view('time_trackers.timesheet', compact('timesheet'));
    }



    /**
     * Start a new time tracker.
     *
     * Creates a new time tracking record for the authenticated user with the current start time and an optional message.
     *
     * @group Time Tracker Management
     *
     * @bodyParam message string optional A description or note for the time tracking session. Example: Working on project X
     * @bodyParam isApi boolean optional Whether to return a formatted API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Timer has been started successfully.",
     *   "data": {
     *     "id": 1,
     *     "user": "<a href=\"/users/3\">John Doe</a>",
     *     "start_date_time": "30 May, 2025 14:34:00",
     *     "end_date_time": "-",
     *     "message": "Working on project X",
     *     "created_at": "30 May, 2025",
     *     "updated_at": "30 May, 2025"
     *   }
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function store(Request $request)
    {


        $isApi = request()->get('isApi', false);

        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['user_id'] =  $this->user->id;
        $formFields['start_date_time'] = date('Y-m-d H:i:s');
        $formattedDateTime = format_date($formFields['start_date_time'], true);

        if ($request->has('message') && !empty($request->input('message'))) {
            $formFields['message'] = $request->input('message');
        }

        try {



            $new_record = TimeTracker::create($formFields);
            $recorded_id = $new_record->id;

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Timer has been started successfully.',
                    [
                        'data' => formatTimeTracker($new_record)
                    ],
                    200
                );
            }

            return response()->json(['error' => false, 'message' => 'Timer has been started successfully.', 'id' => $recorded_id, 'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' started time tracker ' . trim($formattedDateTime), 'type' => 'time_tracker', 'operation' => 'started']);
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
     * Stop an existing time tracker.
     *
     * Updates an existing time tracking record with the current end time and an optional message.
     *
     * @group Time Tracker Management
     *
     * @bodyParam record_id integer required The ID of the time tracker to stop. Must exist in the `time_trackers` table. Example: 1
     * @bodyParam message string optional A description or note for the time tracking session. Example: Completed project X task
     * @bodyParam isApi boolean optional Whether to return a formatted API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Timer has been stopped successfully.",
     *   "data": {
     *     "id": 1,
     *     "user": "<a href=\"/users/3\">John Doe</a>",
     *     "start_date_time": "30 May, 2025 14:34:00",
     *     "end_date_time": "30 May, 2025 16:34:00",
     *     "message": "Completed project X task",
     *     "created_at": "30 May, 2025",
     *     "updated_at": "30 May, 2025"
     *   }
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "No query results for model [App\\Models\\TimeTracker] 9999",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     *   "data": []
     * }
     */

    public function update(Request $request)
    {
        $isApi = request()->get('isApi', false);

        $formFields['end_date_time'] =  date('Y-m-d H:i:s');
        $formattedDateTime = format_date($formFields['end_date_time'], true);
        if ($request->has('message') && !empty($request->input('message'))) {
            $formFields['message'] = $request->input('message');
        }


        try {


            $time_tracker = TimeTracker::findOrFail($request->input('record_id'));
            $time_tracker->update($formFields);

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Timer has been stopped successfully.',
                    [
                        'data' => formatTimeTracker($time_tracker)
                    ]
                );
            }

            return response()->json(['error' => false, 'message' => 'Timer has been stopped successfully.', 'id' => $request->input('record_id'), 'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' stopped time tracker ' . trim($formattedDateTime), 'type' => 'time_tracker', 'operation' => 'stopped']);
        } catch (\Exception $e) {
            return formatApiResponse(
                true,
                config('app.debug') ? $e->getMessage() : 'An error occurred',
                [],
                500
            );
        }
    }

    public function list()
    {
        $search = request('search');
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $user_id = request('user_id', '');
        $date_between_from = request('date_between_from') ?: "";
        $date_between_to = request('date_between_to') ?: "";
        $start_date_from = request('start_date_from', '');
        $start_date_to = request('start_date_to', '');
        $end_date_from = request('end_date_from', '');
        $end_date_to = request('end_date_to', '');
        $limit = request('limit', 10); // Provide a default limit if not present
        $offset = request('offset', 0); // Provide a default offset if not present

        $timesheet = TimeTracker::select(
            'time_trackers.*',
            'users.photo as user_photo',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name')
        )
            ->leftJoin('users', 'time_trackers.user_id', '=', 'users.id')
            ->where('workspace_id', $this->workspace->id);

        if (!isAdminOrHasAllDataAccess()) {
            $timesheet = $timesheet->where('user_id', $this->user->id);
        }

        if ($date_between_from && $date_between_to) {
            $date_between_from = $date_between_from . ' 00:00:00';
            $date_between_to = $date_between_to . ' 23:59:59';
            $timesheet = $timesheet->where('start_date_time', '>=', $date_between_from)
                ->where('end_date_time', '<=', $date_between_to);
        }

        if ($start_date_from && $start_date_to) {
            $start_date_from = $start_date_from . ' 00:00:00';
            $start_date_to = $start_date_to . ' 23:59:59';
            $timesheet = $timesheet->whereBetween('start_date_time', [$start_date_from, $start_date_to]);
        }

        if ($end_date_from && $end_date_to) {
            $end_date_from = $end_date_from . ' 00:00:00';
            $end_date_to = $end_date_to . ' 23:59:59';
            $timesheet = $timesheet->whereBetween('end_date_time', [$end_date_from, $end_date_to]);
        }

        if ($user_id) {
            $timesheet = $timesheet->where('user_id', $user_id);
        }

        if ($search) {
            $timesheet = $timesheet->where(function ($query) use ($search) {
                $query->where('message', 'like', '%' . $search . '%');
            });
        }

        $total = $timesheet->count();

        $timesheet = $timesheet->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $timesheet->transform(function ($timesheet) {
            $formattedDuration = '-';
            if ($timesheet->end_date_time) {
                $startDateTime = Carbon::parse($timesheet->start_date_time);
                $endDateTime = Carbon::parse($timesheet->end_date_time);

                // Calculate the difference between start and end date times
                $duration = $endDateTime->diff($startDateTime);

                // Check if the duration spans multiple days
                if ($duration->days > 0) {
                    // Format with days if the duration spans multiple days
                    $formattedDuration = $duration->format('%D days %H:%I:%S');
                } else {
                    // Format as usual without days if the duration is within the same day
                    $formattedDuration = $duration->format('%H:%I:%S');
                }
            }

            return [
                'id' => $timesheet->id,
                'user' => formatUserHtml($timesheet->user),
                'start_date_time' => format_date($timesheet->start_date_time, true),
                'end_date_time' => $timesheet->end_date_time ? format_date($timesheet->end_date_time, true) : '-',
                'duration' => $formattedDuration,
                'message' => $timesheet->message,
                'created_at' => format_date($timesheet->created_at, true),
                'updated_at' => format_date($timesheet->updated_at, true),
            ];
        });

        return response()->json([
            "rows" => $timesheet,
            "total" => $total,
        ]);
    }

    /**
     * Delete a time tracker.
     *
     * Deletes the specified time tracking record.
     *
     * @group Time Tracker Management
     *
     * @urlParam id integer required The ID of the time tracker to delete. Must exist in the `time_trackers` table. Example: 1
     * @bodyParam isApi boolean optional Whether to return a formatted API response. Defaults to false. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Record deleted successfully.",

     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "No query results for model [App\\Models\\TimeTracker] 9999",

     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred.",
     * }
     */

    public function destroy($id)
    {

        try {

            $isApi = request()->get('isApi', false);

            DeletionService::delete(TimeTracker::class, $id, 'Record');

            if ($isApi) {
                return formatApiResponse(
                    false,
                    'Record deleted successfully.',
                    [],
                    200
                );
            }
            return response()->json(['error' => false, 'message' => 'Record deleted successfully.', 'id' => $id, 'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' deleted time tracker record', 'type' => 'time_tracker']);
        } catch (\Exception $e) {
            return formatApiResponse(
                false,
                config('app.debug') ? $e->getMessage() : 'An error occurred.',
                [],
                500
            );
        }
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:time_trackers,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $deletedIds[] = $id;
            DeletionService::delete(TimeTracker::class, $id, 'Record');
        }

        return response()->json(['error' => false, 'message' => 'Record(s) deleted successfully.', 'id' => $deletedIds, 'activity_message' => trim($this->user->first_name) . ' ' . trim($this->user->last_name) . ' deleted time tracker record', 'type' => 'time_tracker']);
    }

    /**
     * Get list of time trackers.
     *
     * Returns a list of time tracking records for the current workspace in API format, with optional filtering and sorting.
     *
     * @group Time Tracker Management
     *
     * @queryParam search string optional Search term to filter time trackers by message. Example: project
     * @queryParam sort string optional Field to sort by. Defaults to id. Example: start_date_time
     * @queryParam order string optional Sort order: ASC or DESC. Defaults to DESC. Example: ASC
     * @queryParam limit integer optional Number of records to return. Defaults to 10. Example: 20
     * @queryParam offset integer optional Number of records to skip. Defaults to 0. Example: 10
     * @queryParam user_id integer optional Filter by user ID. Example: 3
     * @queryParam date_between_from string optional Start date for filtering records (YYYY-MM-DD). Example: 2025-05-01
     * @queryParam date_between_to string optional End date for filtering records (YYYY-MM-DD). Example: 2025-05-31
     * @queryParam start_date_from string optional Start date for filtering start_date_time (YYYY-MM-DD). Example: 2025-05-01
     * @queryParam start_date_to string optional End date for filtering start_date_time (YYYY-MM-DD). Example: 2025-05-31
     * @queryParam end_date_from string optional Start date for filtering end_date_time (YYYY-MM-DD). Example: 2025-05-01
     * @queryParam end_date_to string optional End date for filtering end_date_time (YYYY-MM-DD). Example: 2025-05-31
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Record(s) retrieved successfully!",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user": "<a href=\"/users/3\">John Doe</a>",
     *       "start_date_time": "30 May, 2025 14:34:00",
     *       "end_date_time": "30 May, 2025 16:34:00",
     *       "message": "Working on project X",
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
            $sort = request('sort', 'id');
            $order = request('order', 'DESC');
            $user_id = request('user_id', '');
            $date_between_from = request('date_between_from') ?: "";
            $date_between_to = request('date_between_to') ?: "";
            $start_date_from = request('start_date_from', '');
            $start_date_to = request('start_date_to', '');
            $end_date_from = request('end_date_from', '');
            $end_date_to = request('end_date_to', '');
            $limit = request('limit', 10); // Provide a default limit if not present
            $offset = request('offset', 0); // Provide a default offset if not present

            $timesheet = TimeTracker::select(
                'time_trackers.*',
                'users.photo as user_photo',
                DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name')
            )
                ->leftJoin('users', 'time_trackers.user_id', '=', 'users.id')
                ->where('workspace_id', $this->workspace->id);

            if (!isAdminOrHasAllDataAccess()) {
                $timesheet = $timesheet->where('user_id', $this->user->id);
            }

            if ($date_between_from && $date_between_to) {
                $date_between_from = $date_between_from . ' 00:00:00';
                $date_between_to = $date_between_to . ' 23:59:59';
                $timesheet = $timesheet->where('start_date_time', '>=', $date_between_from)
                    ->where('end_date_time', '<=', $date_between_to);
            }

            if ($start_date_from && $start_date_to) {
                $start_date_from = $start_date_from . ' 00:00:00';
                $start_date_to = $start_date_to . ' 23:59:59';
                $timesheet = $timesheet->whereBetween('start_date_time', [$start_date_from, $start_date_to]);
            }

            if ($end_date_from && $end_date_to) {
                $end_date_from = $end_date_from . ' 00:00:00';
                $end_date_to = $end_date_to . ' 23:59:59';
                $timesheet = $timesheet->whereBetween('end_date_time', [$end_date_from, $end_date_to]);
            }

            if ($user_id) {
                $timesheet = $timesheet->where('user_id', $user_id);
            }

            if ($search) {
                $timesheet = $timesheet->where(function ($query) use ($search) {
                    $query->where('message', 'like', '%' . $search . '%');
                });
            }

            $total = $timesheet->count();

            $timesheet = $timesheet->orderBy($sort, $order)
                ->offset($offset)
                ->limit($limit)
                ->get();

            $timesheet->transform(function ($timesheet) {
                $formattedDuration = '-';
                if ($timesheet->end_date_time) {
                    $startDateTime = Carbon::parse($timesheet->start_date_time);
                    $endDateTime = Carbon::parse($timesheet->end_date_time);

                    // Calculate the difference between start and end date times
                    $duration = $endDateTime->diff($startDateTime);

                    // Check if the duration spans multiple days
                    if ($duration->days > 0) {
                        // Format with days if the duration spans multiple days
                        $formattedDuration = $duration->format('%D days %H:%I:%S');
                    } else {
                        // Format as usual without days if the duration is within the same day
                        $formattedDuration = $duration->format('%H:%I:%S');
                    }
                }

                return formatTimeTracker($timesheet);
            });

            return formatApiResponse(
                false,
                'Record(s) retrieved successfully!',
                [
                    'total' => $total,
                    'data' => $timesheet
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
}
