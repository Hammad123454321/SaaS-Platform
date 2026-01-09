<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Project;
use App\Models\Workspace;
use App\Models\CustomField;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
class HomeController extends Controller
{
    protected $workspace;
    protected $user;
    protected $statuses;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // fetch session and use it in entire class with constructor
            $this->workspace = Workspace::find(getWorkspaceId());
            $this->user = getAuthenticatedUser();
            return $next($request);
        });
        $this->statuses = Status::all();
    }
    public function index(Request $request)
    {
        $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects ?? [] : $this->user->projects ?? [];
        $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks ?? [] : $this->user->tasks() ?? [];
        $tasks = $tasks ? $tasks->count() : 0;
        $users = $this->workspace->users ?? [];
        $clients = $this->workspace->clients ?? [];
        $todos = $this->user->todos()
            ->orderBy('is_completed', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
        $total_todos = $this->user->todos;
        $meetings = isAdminOrHasAllDataAccess() ? $this->workspace->meetings ?? [] : $this->user->meetings ?? [];
        if ($this->workspace) {
            $activities = $this->workspace->activity_logs()->orderBy('id', 'desc')->limit(10)->get();
        } else {
            $activities = collect(); // Return an empty collection to avoid errors
        }
        $customFields = CustomField::all();
        return view('dashboard', ['users' => $users, 'clients' => $clients, 'projects' => $projects, 'tasks' => $tasks, 'todos' => $todos, 'total_todos' => $total_todos, 'meetings' => $meetings, 'auth_user' => $this->user, 'activities' => $activities, 'customFields' => $customFields]);
    }
    public function upcoming_birthdays()
    {
        $search = request('search');
        $sort = request('sort', 'dob');
        $order = request('order', 'ASC');
        $upcoming_days = (int)request('upcoming_days', 30); // Cast to integer, default to 30 if not provided
        $user_ids = request('user_ids');
        $client_ids = request('client_ids');
        $page = request('page', 1);  // Get current page, default to 1
        $limit = request('limit', 10);
        $users = $this->workspace->users();
        $clients = $this->workspace->clients();
        // Calculate the current date
        $currentDate = today();
        $currentYear = $currentDate->format('Y');
        // Calculate the range for upcoming birthdays (e.g., 365 days from today)
        $upcomingDate = $currentDate->copy()->addDays($upcoming_days);
        $currentDateString = $currentDate->format('Y-m-d');
        $upcomingDateString = $upcomingDate->format('Y-m-d');
        // Users Query
        $users = $users->whereRaw("
    DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob)
    + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR)
    BETWEEN ? AND ?
    AND DATEDIFF(DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob)
    + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) <= ?
    AND (
        (YEAR(CURRENT_DATE()) - YEAR(dob) >= 0)
        OR
        (YEAR(CURRENT_DATE()) - YEAR(dob) = 1 AND DATE_FORMAT(CURRENT_DATE(), '%m-%d') <= DATE_FORMAT(dob, '%m-%d'))
    )
", [$currentDateString, $upcomingDateString, $upcoming_days])
            ->orderByRaw("DATEDIFF(DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob)
    + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) " . $order);
        // Clients Query
        $clients = $clients->whereRaw("
    DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob)
    + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR)
    BETWEEN ? AND ?
    AND DATEDIFF(DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob)
    + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) <= ?
    AND (
        YEAR(CURRENT_DATE()) - YEAR(dob) > 0
        OR
        (YEAR(CURRENT_DATE()) - YEAR(dob) = 1 AND DATE_FORMAT(CURRENT_DATE(), '%m-%d') <= DATE_FORMAT(dob, '%m-%d'))
    )
", [$currentDateString, $upcomingDateString, $upcoming_days])
            ->orderByRaw("DATEDIFF(DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob)
    + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) " . $order);
        // Search by full name (first name + last name)
        if ($search) {
            $users->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('users.id', 'LIKE', "%$search%")
                    ->orWhere('dob', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
            $clients->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('clients.id', 'LIKE', "%$search%")
                    ->orWhere('dob', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }
        // Check if both user_ids and client_ids are provided
        if (!empty($user_ids) && !empty($client_ids)) {
            // Filter users and clients based on the provided ids
            $users->whereIn('users.id', $user_ids);
            $clients->whereIn('clients.id', $client_ids);
        } else {
            // Filter by user_ids if provided
            if (!empty($user_ids)) {
                $users->whereIn('users.id', $user_ids);
                // Clear client records if user_ids are provided
                $clients->whereIn('clients.id', []);  // No clients if user_ids are present
            }
            // Filter by client_ids if provided
            if (!empty($client_ids)) {
                $clients->whereIn('clients.id', $client_ids);
                // Clear user records if client_ids are provided
                $users->whereIn('users.id', []);  // No users if client_ids are present
            }
        }
        // If both are empty, consider both users and clients
        if (empty($user_ids) && empty($client_ids)) {
            // Merge results as before
            $total = $users->count() + $clients->count();
        } else {
            // Only the relevant records are included based on the filter
            $total = max($users->count(), $clients->count());  // Total from either users or clients
        }
        // Merge the results into a collection
        $usersCollection = $users->get()->map(function ($user) {
            $user->type = 'user';
            return $user;
        });
        $clientsCollection = $clients->get()->map(function ($client) {
            $client->type = 'client';
            return $client;
        });
        $mergedCollection = $usersCollection->merge($clientsCollection);
        $page = request('page', 1); // Default to page 1
        $limit = request('limit', 10); // Default limit
        $total = $mergedCollection->count();
        $paginated = $mergedCollection
            ->sortBy(function ($item) use ($currentDate, $currentYear) {
                $birthdayDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item['dob']);
            $birthdayDate->year = $currentDate->year;
                if ($birthdayDate->lt($currentDate)) {
                    $birthdayDate->year = $currentDate->year + 1;
            }
                $daysLeft = $currentDate->diffInDays($birthdayDate);
                return $daysLeft; // Sort by days left for upcoming birthdays
            })
            ->forPage($page, $limit);
        $formattedResults = $paginated->map(function ($item) use ($currentDate, $currentYear) {
            $birthdayDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item['dob']);
            $birthdayDateYear = $birthdayDate->year;
            $yearDifference = $currentYear - $birthdayDateYear;
            $ordinalSuffix = getOrdinalSuffix($yearDifference);
            $birthdayDate->year = $currentDate->year;
            if ($birthdayDate->lt($currentDate)) {
                $birthdayDate->year = $currentDate->year + 1;
            }
            $daysLeft = $currentDate->diffInDays($birthdayDate);
            $emoji = '';
            $label = '';
            if ($daysLeft === 0) {
                $emoji = ' 🥳';
                $label = '<span class="badge bg-label-success mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> ' . get_label('birthday', 'Birthday') . ' ' . get_label('today', 'Today') . '</span>' . $emoji;
            } elseif ($daysLeft === 1) {
                $label = '<span class="badge bg-label-warning mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> ' . get_label('birthday', 'Birthday') . ' ' . get_label('tomorrow', 'Tomorrow') . '</span>';
            } elseif ($daysLeft === 2) {
                $label = '<span class="badge bg-label-primary mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> ' . get_label('birthday', 'Birthday') . ' ' . get_label('day_after_tomorrow', 'Day After Tomorrow') . '</span>';
            }
            $type = $item['type'] ?? 'user';  // Default to 'user' if 'type' is not set
            $formattedMember = $type === 'user' ? formatUserHtml((object) $item) : ($type === 'client' ? formatClientHtml((object) $item) : '');
            // Type label
            $typeLabel = $type === 'user' ? '<span class="badge bg-label-info">' . get_label('user', 'User') . '</span>' : ($type === 'client' ? '<span class="badge bg-label-primary">' . get_label('client', 'Client') . '</span>' : '');
            return [
                'id' => $item['id'],
                'member' => $formattedMember,
                'age' => $currentDate->diffInYears($birthdayDate),
                'days_left' => $daysLeft,
                'dob' => $birthdayDate->format('D, M d, Y') . ' ' . $label,
                'type' => $typeLabel,
            ];
        });
        return response()->json([
            'rows' => $formattedResults->values(),
            "total" => $total,
        ]);
    }
    /**
     * List or search users with birthdays today or upcoming.
     *
     * This endpoint retrieves a list of users with birthdays occurring today or within a specified range of days. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Dashboard Management
     *
     * @queryParam search string Optional. The search term to filter users by first name or last name or combination of first name and last name or User ID or date of birth. Example: John
     * @queryParam order string Optional. The sort order for the `dob` column. Acceptable values are `ASC` or `DESC`. Default is `ASC`. Example: DESC
     * @queryParam upcoming_days integer Optional. The number of days from today to consider for upcoming birthdays. Default is 30. Example: 15
     * @queryParam user_ids array Optional. The specific user IDs to filter the results. Example: [123, 456]
     * @queryParam limit integer Optional. The number of results to return per page. Default is 15. Example: 10
     * @queryParam offset integer Optional. The number of results to skip before starting to collect the result set. Default is 0. Example: 5
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Upcoming birthdays retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "member": "John Doe",
     *       "photo": "http://example.com/storage/photos/john_doe.jpg",
     *       "birthday_count": 30,
     *       "days_left": 10,
     *       "dob": "Tue, 2024-08-08"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Upcoming birthdays not found.",
     *   "data": []
     * }
     */
    public function upcomingBirthdaysApi(Request $request)
    {
        $search = $request->input('search');
        $order = $request->input('order', 'ASC');
        $upcoming_days = (int)$request->input('upcoming_days', 30);
        $user_ids = (array)$request->input('user_ids', []);
        $client_ids = (array)$request->input('client_ids', []);
        $limit = (int)$request->input('limit', 15);
        $offset = (int)$request->input('offset', 0);
        $users = $this->workspace->users();
        $clients = $this->workspace->clients();
        $currentDate = today();
        $currentYear = $currentDate->year;
        $upcomingDate = $currentDate->copy()->addDays($upcoming_days);
        $currentDateString = $currentDate->format('Y-m-d');
        $upcomingDateString = $upcomingDate->format('Y-m-d');
        $birthdayWhereRaw = "
             DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob)
             + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR)
             BETWEEN ? AND ?
             AND DATEDIFF(DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob)
             + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) <= ?
         ";
        $bindings = [$currentDateString, $upcomingDateString, $upcoming_days];
        $users->whereRaw($birthdayWhereRaw, $bindings);
        $clients->whereRaw($birthdayWhereRaw, $bindings);
        if ($search) {
            $users->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('users.id', 'LIKE', "%$search%")
                    ->orWhere('dob', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
            $clients->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('clients.id', 'LIKE', "%$search%")
                    ->orWhere('dob', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }
        // Filters based on user_ids and client_ids
        if (!empty($user_ids) && !empty($client_ids)) {
            $users->whereIn('users.id', $user_ids);
            $clients->whereIn('clients.id', $client_ids);
        } elseif (!empty($user_ids)) {
            $users->whereIn('users.id', $user_ids);
            $clients->whereIn('clients.id', []); // Clear clients
        } elseif (!empty($client_ids)) {
            $clients->whereIn('clients.id', $client_ids);
            $users->whereIn('users.id', []); // Clear users
        }
        $usersCollection = $users->get()->map(function ($user) {
            $user->type = 'user';
            return $user;
        });
        $clientsCollection = $clients->get()->map(function ($client) {
            $client->type = 'client';
            return $client;
        });
        $merged = $usersCollection->merge($clientsCollection);
        if ($merged->isEmpty()) {
            return formatApiResponse(
                false,
                'Upcoming birthdays not found.',
                ['data' => []]
            );
        }
        $sorted = $merged->sortBy(function ($item) use ($currentDate, $order) {
            $birthdayDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item->dob);
            $birthdayDate->year = $currentDate->year;
            if ($birthdayDate->lt($currentDate)) {
                $birthdayDate->year++;
            }
            return $currentDate->diffInDays($birthdayDate);
        }, SORT_REGULAR, $order === 'DESC');
        $paginated = $sorted->slice($offset, $limit)->values();
        $formatted = $paginated->map(function ($item) use ($currentDate, $currentYear) {
            $birthdayDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item->dob);
            $yearDifference = $currentYear - $birthdayDate->year;
            $ordinalSuffix = getOrdinalSuffix($yearDifference);
            $birthdayDate->year = $currentDate->year;
            if ($birthdayDate->lt($currentDate)) {
                $birthdayDate->year++;
            }
            $daysLeft = $currentDate->diffInDays($birthdayDate);
            $emoji = '';
            $label = '';
            if ($daysLeft === 0) {
                $emoji = ' 🥳';
                $label = "{$yearDifference}{$ordinalSuffix} Birthday Today{$emoji}";
            } elseif ($daysLeft === 1) {
                $label = "{$yearDifference}{$ordinalSuffix} Birthday Tomorrow";
            } elseif ($daysLeft === 2) {
                $label = "{$yearDifference}{$ordinalSuffix} Birthday Day After Tomorrow";
            } else {
                $label = "{$yearDifference}{$ordinalSuffix} Birthday in {$daysLeft} days";
            }
            return [
                'id' => $item->id,
                'member' => $item->first_name . ' ' . $item->last_name,
                'photo' => $item->photo ? asset('storage/' . $item->photo) : asset('storage/photos/no-image.jpg'),
                'birthday_count' => $yearDifference,
                'days_left' => $daysLeft,
                'dob' => $birthdayDate->format('D, M d, Y'),
                'type' => $item->type,
                'label' => $label,
            ];
        });
        return formatApiResponse(
            false,
            'Upcoming birthdays retrieved successfully',
            [
                'total' => $merged->count(),
                'data' => $formatted,
            ]
        );
    }
    public function upcoming_work_anniversaries()
    {
        $search = request('search');
        $sort = request('sort', 'doj');
        $order = request('order', 'ASC');
        $upcoming_days = (int)request('upcoming_days', 30);
        $user_ids = request('user_ids', []);
        $client_ids = request('client_ids', []);
        $page = request('page', 1);
        $limit = request('limit', 10);
        $currentDate = today();
        $currentYear = $currentDate->year;
        $upcomingDate = $currentDate->copy()->addDays($upcoming_days);
        $currentDateString = $currentDate->format('Y-m-d');
        $upcomingDateString = $upcomingDate->format('Y-m-d');
        // Reusable work anniversary SQL
        $workAnniversarySql = "
        DATE_ADD(DATE_FORMAT(doj, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(doj)
        + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(doj, '%m-%d'), 1, 0) YEAR)
        BETWEEN ? AND ?
        AND DATEDIFF(
            DATE_ADD(DATE_FORMAT(doj, '%Y-%m-%d'),
            INTERVAL YEAR(CURRENT_DATE()) - YEAR(doj)
            + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(doj, '%m-%d'), 1, 0) YEAR),
            CURRENT_DATE()
        ) <= ?
        AND (
            (YEAR(CURRENT_DATE()) - YEAR(doj) >= 0)
            OR
            (YEAR(CURRENT_DATE()) - YEAR(doj) = 1 AND DATE_FORMAT(CURRENT_DATE(), '%m-%d') <= DATE_FORMAT(doj, '%m-%d'))
        )
    ";
        // Users Query
        $users = $this->workspace->users()
            ->select('users.*')
            ->whereRaw($workAnniversarySql, [$currentDateString, $upcomingDateString, $upcoming_days]);
        // Clients Query
        $clients = $this->workspace->clients()
            ->select('clients.*')
            ->whereRaw($workAnniversarySql, [$currentDateString, $upcomingDateString, $upcoming_days]);
        // Search
        if ($search) {
            $users->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('users.id', 'LIKE', "%$search%")
                    ->orWhere('doj', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
            $clients->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('clients.id', 'LIKE', "%$search%")
                    ->orWhere('doj', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }
        // ID filtering
        if (!empty($user_ids)) {
            $users->whereIn('users.id', $user_ids);
        }
        if (!empty($client_ids)) {
            $clients->whereIn('clients.id', $client_ids);
        }
        // Distinct enforcement before get
        $usersCollection = $users->distinct('users.id')->get()->map(function ($user) {
            $user->type = 'user';
            return $user;
        });
        $clientsCollection = $clients->distinct('clients.id')->get()->map(function ($client) {
            $client->type = 'client';
            return $client;
        });
        // Merge and deduplicate by type-id composite key
        $mergedCollection = $usersCollection->merge($clientsCollection)->unique(function ($item) {
            return $item['type'] . '-' . $item['id'];
        });
        // Sort and paginate
        $paginated = $mergedCollection
            ->sortBy(function ($item) use ($currentDate) {
                $anniversaryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item['doj']);
            $anniversaryDate->year = $currentDate->year;
                if ($anniversaryDate->lt($currentDate)) {
                    $anniversaryDate->year = $currentDate->year + 1;
            }
                return $currentDate->diffInDays($anniversaryDate);
            })
            ->forPage($page, $limit);
        // Format final output
        $formattedResults = $paginated->map(function ($item) use ($currentDate, $currentYear) {
            $anniversaryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item['doj']);
            $yearDifference = $currentYear - $anniversaryDate->year;
            $ordinalSuffix = getOrdinalSuffix($yearDifference);
            $anniversaryDate->year = $currentDate->year;
            if ($anniversaryDate->lt($currentDate)) {
                $anniversaryDate->year = $currentDate->year + 1;
            }
            $daysLeft = $currentDate->diffInDays($anniversaryDate);
            $label = '';
            if ($daysLeft === 0) {
                $label = '<span class="badge bg-label-success mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> ' . get_label('work_anniversary', 'Work Anniversary') . ' ' . get_label('today', 'Today') . ' 🥳</span>';
            } elseif ($daysLeft === 1) {
                $label = '<span class="badge bg-label-warning mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> ' . get_label('work_anniversary', 'Work Anniversary') . ' ' . get_label('tomorrow', 'Tomorrow') . '</span>';
            } elseif ($daysLeft === 2) {
                $label = '<span class="badge bg-label-primary mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> ' . get_label('work_anniversary', 'Work Anniversary') . ' ' . get_label('day_after_tomorrow', 'Day After Tomorrow') . '</span>';
            }
            return [
                'id' => $item['id'],
                'member' => $item['type'] === 'user' ? formatUserHtml((object)$item) : formatClientHtml((object)$item),
                'days_left' => $daysLeft,
                'wa_date' => $anniversaryDate->format('D, M d, Y') . ' ' . $label,
                'type' => $item['type'] === 'user'
                    ? '<span class="badge bg-label-primary">' . get_label('user', 'User') . '</span>'
                    : '<span class="badge bg-label-info">' . get_label('client', 'Client') . '</span>',
            ];
        });
        return response()->json([
            'rows' => $formattedResults->values(),
            'total' => $mergedCollection->count(),
        ]);
    }
    /**
     * List or search users with work anniversaries today or upcoming.
     *
     * This endpoint retrieves a list of users with work anniversaries occurring today or within a specified range of days. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Dashboard Management
     *
     * @queryParam search string Optional. The search term to filter users by first name or last name or combination of first name and last name or User ID or date of joining. Example: John
     * @queryParam order string Optional. The sort order for the `doj` column. Acceptable values are `ASC` or `DESC`. Default is `ASC`. Example: DESC
     * @queryParam upcoming_days integer Optional. The number of days from today to consider for upcoming work anniversaries. Default is 30. Example: 15
     * @queryParam user_ids array Optional. The specific user IDs to filter the results. Example: [123, 456]
     * @queryParam limit integer Optional. The number of results to return per page. Default is 15. Example: 10
     * @queryParam offset integer Optional. The number of results to skip before starting to collect the result set. Default is 0. Example: 5
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Upcoming work anniversaries retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "member": "John Doe",
     *       "photo": "http://example.com/storage/photos/john_doe.jpg",
     *       "anniversary_count": 5,
     *       "days_left": 10,
     *       "doj": "Tue, 2024-08-08"
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Upcoming work anniversaries not found.",
     *   "data": []
     * }
     */
    public function upcomingWorkAnniversariesApi(Request $request)
    {
        $search = $request->input('search');
        $order = $request->input('order', 'ASC');
        $upcoming_days = (int)$request->input('upcoming_days', 30);
        $user_ids = (array)$request->input('user_ids', []);
        $client_ids = (array)$request->input('client_ids', []);
        $limit = (int)$request->input('limit', 15);
        $offset = (int)$request->input('offset', 0);
        $users = $this->workspace->users();
        $clients = $this->workspace->clients();
        $currentDate = today();
        $currentYear = $currentDate->year;
        $upcomingDate = $currentDate->copy()->addDays($upcoming_days);
        $currentDateString = $currentDate->format('Y-m-d');
        $upcomingDateString = $upcomingDate->format('Y-m-d');
        $whereRaw = "
             DATE_ADD(DATE_FORMAT(doj, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(doj)
             + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(doj, '%m-%d'), 1, 0) YEAR)
             BETWEEN ? AND ?
             AND DATEDIFF(DATE_ADD(DATE_FORMAT(doj, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(doj)
             + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(doj, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) <= ?
         ";
        $bindings = [$currentDateString, $upcomingDateString, $upcoming_days];
        $users->whereRaw($whereRaw, $bindings);
        $clients->whereRaw($whereRaw, $bindings);
        if ($search) {
            $users->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('users.id', 'LIKE', "%$search%")
                    ->orWhere('doj', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
            $clients->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('clients.id', 'LIKE', "%$search%")
                    ->orWhere('doj', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }
        if (!empty($user_ids) && !empty($client_ids)) {
            $users->whereIn('users.id', $user_ids);
            $clients->whereIn('clients.id', $client_ids);
        } elseif (!empty($user_ids)) {
            $users->whereIn('users.id', $user_ids);
            $clients->whereIn('clients.id', []); // Clear clients
        } elseif (!empty($client_ids)) {
            $clients->whereIn('clients.id', $client_ids);
            $users->whereIn('users.id', []); // Clear users
        }
        $usersCollection = $users->get()->map(function ($user) {
            $user->type = 'user';
            return $user;
        });
        $clientsCollection = $clients->get()->map(function ($client) {
            $client->type = 'client';
            return $client;
        });
        $merged = $usersCollection->merge($clientsCollection);
        if ($merged->isEmpty()) {
            return formatApiResponse(
                false,
                'Upcoming work anniversaries not found.',
                ['data' => []]
            );
        }
        $sorted = $merged->sortBy(function ($item) use ($currentDate) {
            $anniversaryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item->doj);
            $anniversaryDate->year = $currentDate->year;
            if ($anniversaryDate->lt($currentDate)) {
                $anniversaryDate->year++;
            }
            return $currentDate->diffInDays($anniversaryDate);
        }, SORT_REGULAR, $order === 'DESC');
        $paginated = $sorted->slice($offset, $limit)->values();
        $formatted = $paginated->map(function ($item) use ($currentDate, $currentYear) {
            $anniversaryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $item->doj);
            $yearDifference = $currentYear - $anniversaryDate->year;
            $ordinalSuffix = getOrdinalSuffix($yearDifference);
            $anniversaryDate->year = $currentDate->year;
            if ($anniversaryDate->lt($currentDate)) {
                $anniversaryDate->year++;
            }
            $daysLeft = $currentDate->diffInDays($anniversaryDate);
            $emoji = '';
            $label = '';
            if ($daysLeft === 0) {
                $emoji = ' 🎉';
                $label = "{$yearDifference}{$ordinalSuffix} Work Anniversary Today{$emoji}";
            } elseif ($daysLeft === 1) {
                $label = "{$yearDifference}{$ordinalSuffix} Work Anniversary Tomorrow";
            } elseif ($daysLeft === 2) {
                $label = "{$yearDifference}{$ordinalSuffix} Work Anniversary Day After Tomorrow";
            } else {
                $label = "{$yearDifference}{$ordinalSuffix} Work Anniversary in {$daysLeft} days";
            }
            return [
                'id' => $item->id,
                'member' => $item->first_name . ' ' . $item->last_name,
                'photo' => $item->photo ? asset('storage/' . $item->photo) : asset('storage/photos/no-image.jpg'),
                'anniversary_count' => $yearDifference,
                'days_left' => $daysLeft,
                'doj' => $anniversaryDate->format('D, M d, Y'),
                'label' => $label,
                'type' => $item->type,
            ];
        });
        return formatApiResponse(
            false,
            'Upcoming work anniversaries retrieved successfully',
            [
                'total' => $merged->count(),
                'data' => $formatted,
            ]
        );
    }
    public function members_on_leave()
    {
        $search = request('search');
        $sort = request('sort', 'from_date');
        $order = request('order', 'ASC');
        $upcoming_days = (int)request('upcoming_days', 30);
        $user_ids = request('user_ids', []);
        $limit = request('limit', 10);
        $page = request('page', 1);
        $currentDate = today();
        $upcomingDate = $currentDate->copy()->addDays($upcoming_days);
        $timezone = config('app.timezone');
        // Base leave query with GROUP BY user_id to prevent duplicates
        $leaveUsers = DB::table('leave_requests')
            ->leftJoin('users', 'leave_requests.user_id', '=', 'users.id')
            ->leftJoin('leave_request_visibility', 'leave_requests.id', '=', 'leave_request_visibility.leave_request_id')
            ->select(
                'users.id as user_id',
                'first_name',
                'last_name',
                DB::raw('MIN(from_date) as from_date'),
                DB::raw('MAX(to_date) as to_date'),
                DB::raw('GROUP_CONCAT(from_time) as from_times'),
                DB::raw('GROUP_CONCAT(to_time) as to_times')
            )
            ->where('leave_requests.status', 'approved')
            ->where('workspace_id', $this->workspace->id)
            ->where(function ($q) use ($currentDate, $upcomingDate) {
                $q->where('from_date', '<=', $upcomingDate)
                    ->where('to_date', '>=', $currentDate);
            });
        if (!is_admin_or_leave_editor()) {
            $leaveUsers->where(function ($query) {
                $query->where('leave_requests.user_id', '=', $this->user->id)
                    ->orWhere('leave_request_visibility.user_id', '=', $this->user->id)
                    ->orWhere('leave_requests.visible_to_all', '=', 1);
            });
        }
        if ($search) {
            $leaveUsers->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('users.id', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }
        if (!empty($user_ids)) {
            $leaveUsers->whereIn('leave_requests.user_id', $user_ids);
        }
        $leaveUsers = $leaveUsers->groupBy('leave_requests.user_id');
        // Order by earliest from_date or custom sort
        $leaveUsers = $leaveUsers->orderBy($sort, $order);
        // Pagination using manual chunking
        $results = collect($leaveUsers->get())->forPage($page, $limit);
        $formattedResults = $results->map(function ($user) use ($currentDate, $timezone) {
            $fromDate = Carbon::parse($user->from_date);
            $toDate = Carbon::parse($user->to_date);
            $daysLeft = max(0, $currentDate->diffInDays($fromDate));
            $currentDateTime = Carbon::now($timezone);
            $currentTime = $currentDateTime->format('H:i:s');
            $hasPartial = str_contains($user->from_times, ':') && str_contains($user->to_times, ':');
            $label = '';
            if ($daysLeft === 0 && $hasPartial) {
                $label = ' <span class="badge bg-label-info">' . get_label('on_partial_leave', 'On Partial Leave') . '</span>';
            } elseif ($daysLeft === 0 && !$hasPartial) {
                $label = ' <span class="badge bg-label-success">' . get_label('on_leave', 'On Leave') . '</span>';
            } elseif ($daysLeft === 1) {
                $label = ' <span class="badge bg-label-primary">' . get_label('on_leave_tomorrow', 'On Leave From Tomorrow') . '</span>';
            } elseif ($daysLeft === 2) {
                $label = ' <span class="badge bg-label-warning">' . get_label('on_leave_day_after_tomorow', 'On Leave From Day After Tomorrow') . '</span>';
            }
            $duration = $hasPartial
                ? get_label('partial', 'Partial')
                : $fromDate->diffInDays($toDate) + 1 . ' ' . get_label('days', 'days');
            return [
                'id' => $user->user_id,
                'member' => formatUserHtml(User::find($user->user_id)),
                'from_date' => $fromDate->format('D, M d, Y'),
                'to_date' => $toDate->format('D, M d, Y'),
                'type' => $hasPartial
                    ? '<span class="badge bg-label-info">' . get_label('partial', 'Partial') . '</span>'
                    : '<span class="badge bg-label-primary">' . get_label('full', 'Full') . '</span>',
                'duration' => $duration,
                'days_left' => $daysLeft,
                'label' => $label,
            ];
        });
        return response()->json([
            'rows' => $formattedResults->values(),
            'total' => DB::table('leave_requests')
                ->where('status', 'approved')
                ->where('workspace_id', $this->workspace->id)
                ->where(function ($q) use ($currentDate, $upcomingDate) {
                    $q->where('from_date', '<=', $upcomingDate)
                        ->where('to_date', '>=', $currentDate);
                })
                ->distinct('user_id')
                ->count('user_id'),
        ]);
    }
    /**
     * List members currently on leave or scheduled to be on leave.
     *
     * This endpoint retrieves a list of members who are currently on leave or scheduled to be on leave within a specified range of days.
     * The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Dashboard Management
     *
     * @queryParam search string Optional. The search term to filter users by first name or last name or combination of first name and last name or User ID or date of joining. Example: John
     * @queryParam sort string Optional. The field to sort by. Acceptable values are `from_date` and `to_date`. Default is `from_date`. Example: to_date
     * @queryParam order string Optional. The sort order. Acceptable values are `ASC` or `DESC`. Default is `ASC`. Example: DESC
     * @queryParam upcoming_days integer Optional. The number of days from today to consider for upcoming leave. Default is 30. Example: 15
     * @queryParam user_ids array Optional. The specific user IDs to filter the results. Example: [123, 456]
     * @queryParam limit integer Optional. The number of results to return per page. Default is 15. Example: 10
     * @queryParam offset integer Optional. The number of results to skip before starting to collect the result set. Default is 0. Example: 5
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Members on leave retrieved successfully.",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "member": "John Doe",
     *       "photo": "http://example.com/storage/photos/john_doe.jpg",
     *       "from_date": "Mon, 2024-07-15",
     *       "to_date": "Fri, 2024-07-19",
     *       "type": "Full",
     *       "duration": "5 days",
     *       "days_left": 0
     *     }
     *   ]
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Members on leave not found.",
     *   "data": []
     * }
     */
    public function membersOnLeaveApi(Request $request)
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "from_date";
        $order = (request('order')) ? request('order') : "ASC";
        $upcoming_days = (request('upcoming_days')) ? request('upcoming_days') : 30;
        $user_ids = request('user_ids', []);
        $limit = (int)$request->input('limit', 15); // Cast to integer, default to 15 if not provided
        $offset = (int)$request->input('offset', 0); // Cast to integer, default to 0 if not provided
        // Calculate the current date
        $currentDate = today();
        // Calculate the range for upcoming work anniversaries (e.g., 30 days from today)
        $upcomingDate = $currentDate->copy()->addDays($upcoming_days);
        // Query members on leave based on 'start_date' in the 'leave_requests' table
        $leaveUsers = DB::table('leave_requests')
            ->selectRaw('*, leave_requests.user_id as UserId')
            ->leftJoin('users', 'leave_requests.user_id', '=', 'users.id')
            ->leftJoin('leave_request_visibility', 'leave_requests.id', '=', 'leave_request_visibility.leave_request_id')
            ->where(function ($leaveUsers) use ($currentDate, $upcomingDate) {
                $leaveUsers->where('from_date', '<=', $upcomingDate)
                    ->where('to_date', '>=', $currentDate);
            })
            ->where('leave_requests.status', '=', 'approved')
            ->where('workspace_id', '=', $this->workspace->id);
        if (!is_admin_or_leave_editor()) {
            $leaveUsers->where(function ($query) {
                $query->where('leave_requests.user_id', '=', $this->user->id)
                    ->orWhere('leave_request_visibility.user_id', '=', $this->user->id)
                    ->orWhere('leave_requests.visible_to_all', '=', 1);
            });
        }
        // Search by full name (first name + last name)
        if (!empty($search)) {
            $leaveUsers->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('users.id', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }
        if (!empty($user_ids)) {
            $leaveUsers->whereIn('leave_requests.user_id', (array)$user_ids);
        }
        $total = $leaveUsers->count();
        if ($total == 0) {
            return formatApiResponse(
                false,
                'Members on leave not found',
                []
            );
        }
        $leaveUsers = $leaveUsers->orderBy($sort, $order)
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function ($user) use ($currentDate) {
            $fromDate = \Carbon\Carbon::createFromFormat('Y-m-d', $user->from_date);
            // Set the year to the current year
            $fromDate->year = $currentDate->year;
                // Calculate days left until the user's return from leave
                $daysLeft = $currentDate->diffInDays($fromDate);
                if ($fromDate->lt($currentDate)) {
                    $daysLeft = 0;
            }
                $fromDate = Carbon::parse($user->from_date);
                $toDate = Carbon::parse($user->to_date);
                if ($user->from_time && $user->to_time) {
                    $duration = 0;
                    // Loop through each day
                    while ($fromDate->lessThanOrEqualTo($toDate)) {
                        // Create Carbon instances for the start and end times of the leave request for the current day
                        $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $user->from_time);
                    $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $user->to_time);
                    // Calculate the duration for the current day and add it to the total duration
                    $duration += $fromDateTime->diffInMinutes($toDateTime) / 60; // Duration in hours
                    // Move to the next day
                    $fromDate->addDay();
                    }
                } else {
                    // Calculate the inclusive duration in days
                    $duration = $fromDate->diffInDays($toDate) + 1;
                }
                $fromDateDayOfWeek = $fromDate->format('D');
                $toDateDayOfWeek = $toDate->format('D');
                return [
                    'id' => $user->UserId,
                    'member' => $user->first_name . ' ' . $user->last_name,
                    'photo' => $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg'),
                    'from_date' =>  format_date($user->from_date, to_format: 'Y-m-d'),
                    'from_time' => $user->from_time ? Carbon::parse($user->from_time)->format('h:i A') : '',
                    'to_date' =>  format_date($user->to_date, to_format: 'Y-m-d'),
                    'to_time' => $user->to_time ? Carbon::parse($user->to_time)->format('h:i A') : '',
                    'type' => $user->from_time && $user->to_time ? get_label('partial', 'Partial') : get_label('full', 'Full'),
                    'duration' => $user->from_time && $user->to_time ? $duration . ' hour' . ($duration > 1 ? 's' : '') : $duration . ' day' . ($duration > 1 ? 's' : ''),
                    'days_left' => $daysLeft,
                ];
            });
        return formatApiResponse(
            false,
            'Members on leave retrieved successfully',
            [
                'total' => $total,
                'data' => $leaveUsers,
            ]
        );
    }
    public function upcoming_birthdays_calendar(Request $request)
    {
        $users = $this->workspace->users()->get();
        $clients = $this->workspace->clients()->get();
        $startDate = \Carbon\Carbon::parse($request->startDate);
        $endDate = \Carbon\Carbon::parse($request->endDate);
        $currentDate = today();  // Today's date
        $events = [];
        // Helper function to calculate birthdays for users/clients
        $calculateBirthdays = function ($entities, $type, $color) use ($startDate, $endDate, $currentDate) {
            $entityEvents = [];
            foreach ($entities as $entity) {
                if (!empty($entity->dob)) {
                    $birthday = \Carbon\Carbon::createFromFormat('Y-m-d', $entity->dob);
                    $birthdayDateYear = $birthday->year;
                    $yearDifference = $startDate->year - $birthdayDateYear;
                    $ordinalSuffix = getOrdinalSuffix($yearDifference);
                    // Start checking from the birthday of the current year
                    $birthdayThisYear = $birthday->copy()->year($currentDate->year);
                    // Ensure the birthday is not shown for the birth year
                    if ($birthdayThisYear->year <= $birthday->year) {
                        $birthdayThisYear->year = $birthday->year + 1;
                    }
                    // Loop to find birthdays within the range
                    while ($birthdayThisYear->lte($endDate)) {
                        if ($birthdayThisYear->gte($startDate)) {
                            $age = $birthdayThisYear->year - $birthdayDateYear;
                            $ordinalSuffix = getOrdinalSuffix($age);
                            $entityEvents[] = [
                                'userId' => $entity->id,
                                'type' => $type,
                                'title' => $entity->first_name . ' ' . $entity->last_name . get_label('s', '\'s ') . $age . ' ' . $ordinalSuffix . ' ' . get_label('birthday', 'Birthday'),
                                'start' => $birthdayThisYear->format('Y-m-d'),
                                'backgroundColor' => $color,
                                'borderColor' => $color,
                                'textColor' => '#ffffff',
                            ];
                        }
                        $birthdayThisYear->addYear();
                    }
                }
            }
            return $entityEvents;
        };
        // Calculate birthdays for users
        $userBirthdays = $calculateBirthdays($users, 'user', '#007bff');
        // Calculate birthdays for clients
        $clientBirthdays = $calculateBirthdays($clients, 'client', '#17a2b8');
        // Merge all events
        $events = array_merge($userBirthdays, $clientBirthdays);
        return response()->json($events);
    }
    public function upcoming_work_anniversaries_calendar(Request $request)
    {
        $users = $this->workspace->users()->get();
        $clients = $this->workspace->clients()->get();
        $startDate = \Carbon\Carbon::parse($request->startDate);  // Date range start
        $endDate = \Carbon\Carbon::parse($request->endDate);      // Date range end
        $currentDate = today();  // Today's date
        $events = [];
        // Helper function to calculate events for users/clients
        $calculateEvents = function ($entities, $type, $color) use ($startDate, $endDate, $currentDate) {
            $entityEvents = [];
            foreach ($entities as $entity) {
                if (!empty($entity->doj)) {
                    $doj = \Carbon\Carbon::createFromFormat('Y-m-d', $entity->doj);
                    $dojYear = $doj->year;
                    $yearDifference = $startDate->year - $dojYear;
                    $ordinalSuffix = getOrdinalSuffix($yearDifference);
                    // Start checking from the birthday of the current year
                    $waDateThisYear = $doj->copy()->year($currentDate->year);
                    // Ensure the birthday is not shown for the birth year
                    if ($waDateThisYear->year <= $doj->year) {
                        $waDateThisYear->year = $doj->year + 1;
                    }
                    // Loop to find work anniversaries within the range, including future years
                    while ($waDateThisYear->lte($endDate)) {
                        if ($waDateThisYear->gte($startDate)) {
                            $yearsOfService = $waDateThisYear->year - $dojYear;
                            $ordinalSuffix = getOrdinalSuffix($yearsOfService);
                            $entityEvents[] = [
                                'userId' => $entity->id,
                                'type' => $type,
                                'title' => $entity->first_name . ' ' . $entity->last_name . get_label('s', '\'s ') . $yearsOfService . ' ' . $ordinalSuffix . ' ' . get_label('work_anniversary', 'Work Anniversary'),
                                'start' => $waDateThisYear->format('Y-m-d'),
                                'backgroundColor' => $color,
                                'borderColor' => $color,
                                'textColor' => '#ffffff',
                            ];
                        }
                        $waDateThisYear->addYear();
                    }
                }
            }
            return $entityEvents;
        };
        // Calculate events for users
        $userEvents = $calculateEvents($users, 'user', '#007bff');
        // Calculate events for clients
        $clientEvents = $calculateEvents($clients, 'client', '#17a2b8');
        // Merge all events
        $events = array_merge($userEvents, $clientEvents);
        return response()->json($events);
    }
    public function members_on_leave_calendar(Request $request)
    {
        $currentDate = today();
        // Parse the start and end dates from the request
        $startDate = \Carbon\Carbon::parse($request->startDate);
        $endDate = \Carbon\Carbon::parse($request->endDate);
        // Fetch leave requests within the specified date range
        $leaveRequests = DB::table('leave_requests')
            ->selectRaw('*, leave_requests.user_id as UserId')
            ->leftJoin('users', 'leave_requests.user_id', '=', 'users.id')
            ->leftJoin('leave_request_visibility', 'leave_requests.id', '=', 'leave_request_visibility.leave_request_id')
            ->where('leave_requests.status', '=', 'approved')
            ->where('workspace_id', '=', $this->workspace->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('from_date', [$startDate, $endDate])
                    ->orWhereBetween('to_date', [$startDate, $endDate])
                    ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                        $subQuery->where('from_date', '<=', $startDate)
                            ->where('to_date', '>=', $endDate);
                    });
            });
        // Add condition to restrict results based on user roles
        if (!is_admin_or_leave_editor()) {
            $leaveRequests->where(function ($query) {
                $query->where('leave_requests.user_id', '=', $this->user->id)
                    ->orWhere('leave_request_visibility.user_id', '=', $this->user->id);
            });
        }
        $time_format = get_php_date_time_format(true);
        $time_format = str_replace(':s', '', $time_format);
        // Get leave requests and format for calendar
        $events = $leaveRequests->get()->map(function ($leave) {
            // Get the user's name
            $title = $leave->first_name . ' ' . $leave->last_name;
            if ($leave->from_time && $leave->to_time) {
                // If both start and end times are present, format them accordingly
                $formattedStartDateTime = format_date($leave->from_date . ' ' . $leave->from_time, true, null, null, false);
                $formattedEndDateTime = format_date($leave->to_date . ' ' . $leave->to_time, true, null, null, false);
                $title .= ' : ' . $formattedStartDateTime . ' ' . get_label('to', 'to') . ' ' . $formattedEndDateTime;
                $backgroundColor = '#02C5EE';
            } else {
                // If only dates are present, show just the formatted date
                $title .= ' : ' . format_date($leave->from_date);
                if ($leave->to_date != $leave->from_date) {
                    $title .= ' ' . get_label('to', 'to') . ' ' . format_date($leave->to_date);
                }
                $backgroundColor = '#007bff';
            }
            return [
                'userId' => $leave->UserId,
                'title' => $title,
                'start' => $leave->from_date,
                'end' => $leave->to_date,
                'startTime' => $leave->from_time,
                'endTime' => $leave->to_time,
                'backgroundColor' => $backgroundColor,
                'borderColor' => $backgroundColor,
                'textColor' => '#ffffff'
            ];
        });
        return response()->json($events);
    }
    /**
     * Get Statistics
     *
     * This endpoint retrieves workspace-specific statistics related to projects, tasks, users, clients, todos, and meetings. The user must be authenticated and have the necessary permissions to manage (if applicable) each respective module.
     *
     * @group Dashboard Management
     *
     * @authenticated
     *
     * @response {
     *   "error": false,
     *   "message": "Statistics retrieved successfully",
     *   "data": {
     *     "total_projects": 8,
     *     "total_tasks": 8,
     *     "total_users": 8,
     *     "total_clients": 8,
     *     "total_meetings": 8,
     *     "total_todos": 0,
     *     "completed_todos": 0,
     *     "pending_todos": 0,
     *     "status_wise_projects": [
     *       {
     *         "id": 1,
     *         "title": "In Progress",
     *         "color": "primary",
     *         "total_projects": 4
     *       },
     *       {
     *         "id": 2,
     *         "title": "Completed",
     *         "color": "success",
     *         "total_projects": 4
     *       }
     *     ],
     *     "status_wise_tasks": [
     *       {
     *         "id": 1,
     *         "title": "In Progress",
     *         "color": "primary",
     *         "total_tasks": 4
     *       },
     *       {
     *         "id": 2,
     *         "title": "Completed",
     *         "color": "success",
     *         "total_tasks": 4
     *       }
     *     ]
     *   }
     * }
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while retrieving statistics: Internal server error message"
     * }
     */
    public function getStatistics()
    {
        try {
            // Define an array of colors
            $colors = [
                '#63ed7a',
                '#ffa426',
                '#fc544b',
                '#6777ef',
                '#FF00FF',
                '#53ff1a',
                '#ff3300',
                '#0000ff',
                '#00ffff',
                '#99ff33',
                '#003366',
                '#cc3300',
                '#ffcc00',
                '#ff9900',
                '#3333cc',
                '#ffff00',
                '#FF5733',
                '#33FF57',
                '#5733FF',
                '#FFFF33',
                '#A6A6A6',
                '#FF99FF',
                '#6699FF',
                '#666666',
                '#FF6600',
                '#9900CC',
                '#FF99CC',
                '#FFCC99',
                '#99CCFF',
                '#33CCCC',
                '#CCFFCC',
                '#99CC99',
                '#669999',
                '#CCCCFF',
                '#6666FF',
                '#FF6666',
                '#99CCCC',
                '#993366',
                '#339966',
                '#99CC00',
                '#CC6666',
                '#660033',
                '#CC99CC',
                '#CC3300',
                '#FFCCCC',
                '#6600CC',
                '#FFCC33',
                '#9933FF',
                '#33FF33',
                '#FFFF66',
                '#9933CC',
                '#3300FF',
                '#9999CC',
                '#0066FF',
                '#339900',
                '#666633',
                '#330033',
                '#FF9999',
                '#66FF33',
                '#6600FF',
                '#FF0033',
                '#009999',
                '#CC0000',
                '#999999',
                '#CC0000',
                '#CCCC00',
                '#00FF33',
                '#0066CC',
                '#66FF66',
                '#FF33FF',
                '#CC33CC',
                '#660099',
                '#663366',
                '#996666',
                '#6699CC',
                '#663399',
                '#9966CC',
                '#66CC66',
                '#0099CC',
                '#339999',
                '#00CCCC',
                '#CCCC99',
                '#FF9966',
                '#99FF00',
                '#66FF99',
                '#336666',
                '#00FF66',
                '#3366CC',
                '#CC00CC',
                '#00FF99',
                '#FF0000',
                '#00CCFF',
                '#000000',
                '#FFFFFF'
            ];
            // Initialize response data
            $statusCountsProjects = [];
            $statusCountsTasks = [];
            $total_projects_count = 0;
            $total_tasks_count = 0;
            $total_users_count = 0;
            $total_clients_count = 0;
            $total_todos_count = 0;
            $total_completed_todos_count = 0;
            $total_pending_todos_count = 0;
            $total_meetings_count = 0;
            // Fetch total counts
            if ($this->user->can('manage_projects')) {
                $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects ?? [] : $this->user->projects ?? [];
                $total_projects_count = $projects->count();
            }
            if ($this->user->can('manage_tasks')) {
                $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks ?? [] : $this->user->tasks() ?? [];
                $total_tasks_count = $tasks->count();
            }
            if ($this->user->can('manage_users')) {
                $users = $this->workspace->users ?? [];
                $total_users_count = count($users);
            }
            if ($this->user->can('manage_clients')) {
                $clients = $this->workspace->clients ?? [];
                $total_clients_count = count($clients);
            }
            $todos = $this->user->todos;
            $total_todos_count = $todos->count();
            $total_completed_todos_count = $todos->where('is_completed', true)->count();
            $total_pending_todos_count = $todos->where('is_completed', false)->count();
            if ($this->user->can('manage_meetings')) {
                $meetings = isAdminOrHasAllDataAccess() ? $this->workspace->meetings ?? [] : $this->user->meetings ?? [];
                $total_meetings_count = $meetings->count();
            }
            // Assign colors to status-wise projects
            if ($this->user->can('manage_projects')) {
                foreach ($this->statuses as $status) {
                    $projectCount = isAdminOrHasAllDataAccess() ? count($status->projects) : $this->user->status_projects($status->id)->count();
                    $statusCountsProjects[] = [
                        'id' => $status->id,
                        'title' => $status->title,
                        'color' => $status->color,
                        'chart_color' => '0Xff' . strtoupper(ltrim($colors[array_rand($colors)], '#')),
                        // Assign random chart color
                        'total_projects' => $projectCount
                    ];
                }
                usort($statusCountsProjects, fn($a, $b) => $b['total_projects'] <=> $a['total_projects']);
            }
            // Assign colors to status-wise tasks
            if ($this->user->can('manage_tasks')) {
                foreach ($this->statuses as $status) {
                    $taskCount = isAdminOrHasAllDataAccess() ? count($status->tasks) : $this->user->status_tasks($status->id)->count();
                    $statusCountsTasks[] = [
                        'id' => $status->id,
                        'title' => $status->title,
                        'color' => $status->color,
                        'chart_color' => '0Xff' . strtoupper(ltrim($colors[array_rand($colors)], '#')),    // Assign random chart color
                        'total_tasks' => $taskCount
                    ];
                }
                usort($statusCountsTasks, fn($a, $b) => $b['total_tasks'] <=> $a['total_tasks']);
            }
            // Return response
            return formatApiResponse(
                false,
                'Statistics retrieved successfully.',
                [
                    'data' => [
                        'total_projects' => $total_projects_count,
                        'total_tasks' => $total_tasks_count,
                        'total_users' => $total_users_count,
                        'total_clients' => $total_clients_count,
                        'total_meetings' => $total_meetings_count,
                        'total_todos' => $total_todos_count,
                        'completed_todos' => $total_completed_todos_count,
                        'pending_todos' => $total_pending_todos_count,
                        'status_wise_projects' => $statusCountsProjects,
                        'status_wise_tasks' => $statusCountsTasks
                    ]
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while retrieving statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
}
