<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Workspace;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class NotificationsController extends Controller
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
        $types = [
            'project',
            'task',
            'workspace',
            'meeting',
            'leave_request',
            'project_comment_mention',
            'task_comment_mention',
            'birthday_wish',
            'work_anniversary_wish'
            // Add more types as needed
        ];
        $notifications_count = $this->user->notifications()->count();

        return view('notifications.list', ['notifications_count' => $notifications_count, 'types' => $types]);
    }

    public function mark_all_as_read()
    {
        $notifications = $this->user->notifications()->get();

        foreach ($notifications as $notification) {
            $this->user->notifications()->updateExistingPivot($notification->id, ['read_at' => now()]);
        }
        Session::flash('message', 'All notifications marked as read.');
        return response()->json(['error' => false]);
    }



    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $statuses = request('statuses') ?: [];
        $types = request('types') ?: [];
        $notification_types = request('notification_types') ?: [];
        $user_ids = request('user_ids') ?: [];
        $client_ids = request('client_ids') ?: [];
        $date_from = (request('date_from')) ? request('date_from') : "";
        $date_to = (request('date_to')) ? request('date_to') : "";
        $date_from = $date_from ? date('Y-m-d H:i:s', strtotime($date_from . ' 00:00:00')) : null;
        $date_to = $date_to ? date('Y-m-d H:i:s', strtotime($date_to . ' 23:59:59')) : null;

        // Check if the logged-in user is a user or a client
        if (isClient()) {
            $pivotTable = 'client_notifications';
        } else {
            $pivotTable = 'notification_user';
        }

        if (!empty($user_ids) && isAdminOrHasAllDataAccess()) {
            $notifications = User::whereIn('id', $user_ids)->firstOrFail()->notifications();
            $pivotTable = 'notification_user';
        } elseif (!empty($client_ids) && isAdminOrHasAllDataAccess()) {
            $notifications = Client::whereIn('id', $client_ids)->firstOrFail()->notifications();
            $pivotTable = 'client_notifications';
        } else {
            $notifications = isAdminOrHasAllDataAccess() ? $this->workspace->notifications() : $this->user->notifications();
        }

        if ($search) {
            $notifications = $notifications->where(function ($query) use ($search) {
                $query->where('id', 'like', '%' . $search . '%')
                    ->orWhere('title', 'like', '%' . $search . '%')
                    ->orWhere('message', 'like', '%' . $search . '%');
            });
        }

        if (!empty($statuses)) {
            $notifications = $notifications->where(function ($query) use ($statuses, $pivotTable) {
                if (in_array('read', $statuses)) {
                    $query->whereNotNull("{$pivotTable}.read_at");
                }
                if (in_array('unread', $statuses)) {
                    $query->orWhereNull("{$pivotTable}.read_at");
                }
            });
        }

        if ($sort === "status") {
            // Sort by read status
            $notifications = $notifications->orderBy(function ($query) use ($pivotTable) {
                return $query->selectRaw("CASE WHEN {$pivotTable}.read_at IS NULL THEN 0 ELSE 1 END");
            }, $order);
        } else {
            // Sort by other columns
            $notifications = $notifications->orderBy($sort, $order);
        }

        if (!empty($types)) {
            $notifications = $notifications->whereIn('type', $types);
        }

        if (!empty($notification_types)) {
            $notifications = $notifications->where(function ($query) use ($notification_types, $pivotTable) {
                foreach ($notification_types as $type) {
                    if ($type == 'system') {
                        $query->orWhere(function ($q) use ($pivotTable) {
                            $q->where("{$pivotTable}.is_system", 1);
                        });
                    } elseif ($type == 'push') {
                        $query->orWhere(function ($q) use ($pivotTable) {
                            $q->where("{$pivotTable}.is_push", 1);
                        });
                    }
                }
            });
        }

        if ($date_from && $date_to) {
            $notifications = $notifications->whereBetween('notifications.created_at', [$date_from, $date_to]);
        }

        $total = $notifications->count();

        $canDelete = checkPermission('delete_system_notifications');

        $notifications = $notifications->paginate(request("limit"));

        $notifications->through(function ($notification) use ($canDelete) {
            // Construct the base URL based on the notification type
            $baseUrl = '';
            if ($notification->type == 'project') {
                $baseUrl = url('/projects/information/' . $notification->type_id);
            } else if ($notification->type == 'task') {
                $baseUrl = url('/tasks/information/' . $notification->type_id);
            } else if ($notification->type == 'workspace') {
                $baseUrl = url('/workspaces');
            } else if ($notification->type == 'meeting') {
                $baseUrl = url('/meetings');
            } else if ($notification->type == 'leave_request') {
                $baseUrl = url('/leave-requests');
            }
            $readAt = $notification->notification_user_read_at
                ? $notification->notification_user_read_at
                : ($notification->client_notifications_read_at
                    ? $notification->client_notifications_read_at
                    : (isset($notification->pivot) && $notification->pivot->read_at
                        ? $notification->pivot->read_at
                        : null));

            $markAsAction = is_null($readAt) ? get_label('mark_as_read', 'Mark as read') : get_label('mark_as_unread', 'Mark as unread');
            $iconClass = is_null($readAt) ? 'bx bx-check text-secondary mx-1' : 'bx bx-check-double text-success mx-1';

            // Check if the notification is assigned to the currently logged-in user or client
            $isAssignedToCurrentUser = $notification->users->contains('id', $this->user->id) || $notification->clients->contains('id', $this->user->id);

            // Construct the HTML for the mark as read/unread action only if the notification is assigned to the current user
            if ($isAssignedToCurrentUser) {
                $actionsHtml = '<a href="javascript:void(0)" data-id="' . $notification->id . '" data-needconfirm="true" title="' . $markAsAction . '" class="card-link update-notification-status"><i class="' . $iconClass . '"></i></a>';
            } else {
                // If the notification is not assigned to the current user, do not display mark as read/unread option
                $actionsHtml = '';
            }

            $statusBadge = is_null($readAt) ? '<span class="badge bg-danger">' . get_label('unread', 'Unread') . '</span>' : '<span class="badge bg-success">' . get_label('read', 'Read') . '</span>';

            // Append view option only if $notification->type is 'project' or 'task'
            if ($notification->action != 'team_member_on_leave_alert' && $notification->type != 'birthday_wish' && $notification->type != 'work_anniversary_wish') {
                $actionsHtml .= '<a href="' . $baseUrl . '" title="' . get_label('view', 'View') . '" class="card-link update-notification-status" data-id="' . $notification->id . '"><i class="bx bx-info-circle mx-1"></i></a>';
            }
            if ($canDelete) {
                $actionsHtml .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $notification->id . '" data-type="notifications">' .
                    '<i class="bx bx-trash text-danger mx-1"></i>' .
                    '</button>';
            }
            $isSystem = $notification->notification_user_is_system
                ?? $notification->client_notifications_is_system
                ?? ($notification->pivot->is_system ?? null);

            $isPush = $notification->notification_user_is_push
                ?? $notification->client_notifications_is_push
                ?? ($notification->pivot->is_push ?? null);

            $notificationTypes = [];
            if ($isSystem) {
                $notificationTypes[] = get_label('system', 'System');
            }
            if ($isPush) {
                $notificationTypes[] = get_label('push_in_app', 'Push in App');
            }

            $notificationTypes = implode(', ', $notificationTypes);

            return [
                'id' => $notification->id,
                'title' => $notification->title . '<br><span class="text-muted">' . $notification->created_at->diffForHumans() . ' (' . format_date($notification->created_at, true) . ')' . '</span>',
                'users' => $notification->users,
                'clients' => $notification->clients,
                'type' => $notification->type,
                'type_id' => $notification->type_id,
                'message' => $notification->message,
                'status' => $statusBadge,
                'type' => ucfirst(str_replace('_', ' ', $notification->type)),
                'notification_types' => $notificationTypes,
                'read_at' => format_date($readAt, true),
                'created_at' => format_date($notification->created_at, true),
                'updated_at' => format_date($notification->updated_at, true),
                'actions' => $actionsHtml
            ];
        });


        foreach ($notifications->items() as $notification => $collection) {
            foreach ($collection['clients'] as $i => $client) {
                $collection['clients'][$i] = "<a href='" . url("/clients/profile/{$client->id}") . "'><li class='avatar avatar-sm pull-up' title='{$client['first_name']} {$client['last_name']}'>
                                <img src='" . ($client['photo'] ? asset('storage/' . $client['photo']) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' />
                            </li></a>";
            };
        }

        foreach ($notifications->items() as $notification => $collection) {
            foreach ($collection['users'] as $i => $user) {
                $collection['users'][$i] = "<a href='" . url("/users/profile/{$user->id}") . "'><li class='avatar avatar-sm pull-up' title='{$user['first_name']} {$user['last_name']}'>
                                <img src='" . ($user['photo'] ? asset('storage/' . $user['photo']) : asset('storage/photos/no-image.jpg')) . "' class='rounded-circle' />
                            </li></a>";
            };
        }

        return response()->json([
            "rows" => $notifications->items(),
            "total" => $total,
        ]);
    }


    /**
     * List or search notifications.
     * 
     * This endpoint retrieves a list of notifications based on various filters. The user must be authenticated to perform this action. The request allows filtering by status, type, user, client, and other parameters.
     * 
     * @authenticated
     * 
     * @group Notification Management
     * 
     * @urlParam id int optional The ID of the meeting to retrieve. Example: 1
     *
     * @queryParam search string optional The search term to filter notifications by title, message and id. Example: Alert
     * @queryParam sort string optional The field to sort by. Defaults to "id". Sortable fields include: id, title, message, type, status, created_at, and updated_at. Example: title
     * @queryParam order string optional The sort order, either "ASC" or "DESC". Defaults to "DESC". Example: ASC
     * @queryParam status string optional The status of the notification to filter by. Can be "read" or "unread". Example: unread
     * @queryParam type string optional The type of notifications to filter by. Example: project
     * @queryParam user_id int optional The user ID to filter notifications by. Example: 1
     * @queryParam client_id int optional The client ID to filter notifications by. Example: 5
     * @queryParam notification_type string optional The notification type to filter by. Can be "system" or "push". Example: system
     * @queryParam limit int optional The number of notifications per page for pagination. Example: 10
     * @queryParam offset int optional The offset for pagination, indicating the starting point of results. Example: 0
     * 
     * @response 200 {
     *   "error": false,
     *   "message": "Notifications retrieved successfully",
     *   "total": 1,
     *   "data": [
     *     {
     *       "id": 116,
     *       "title": "Task Status Updated",
     *       "users": [
     *         {
     *           "id": 183,
     *           "first_name": "Girish",
     *           "last_name": "Thacker",
     *           "photo": "https://test-taskify.infinitietech.com/storage/photos/no-image.jpg"
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
     *       "type": "Task",
     *       "type_id": 268,
     *       "message": "Madhavan Vaidya has updated the status of task sdff, ID:#268, from Default to Test From Pro.",
     *       "status": "Unread",
     *       "read_at": null,
     *       "created_at": "23-07-2024 17:50:09",
     *       "updated_at": "23-07-2024 19:08:16"
     *     }
     *   ]
     * }
     * 
     * @response 200 {
     *   "error": true,
     *   "message": "Notification not found",
     *   "total": 0,
     *   "data": []
     * }
     * 
     * @response 200 {
     *   "error": true,
     *   "message": "Notifications not found",
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
        $type = $request->input('type', '');
        $notificationType = $request->input('notification_type', '');
        $limit = $request->input('limit', 10); // default limit
        $offset = $request->input('offset', 0); // default offset

        if ($id) {
            $notification = Notification::find($id);
            if (!$notification) {
                return formatApiResponse(
                    false,
                    'Notification not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            } else {
                return formatApiResponse(
                    false,
                    'Notification retrieved successfully',
                    [
                        'total' => 1,
                        'data' => [formatNotification($notification)]
                    ]
                );
            }
        } else {
            $pivotTable = getGuardName() == 'client' ? 'client_notifications' : 'notification_user';
            if ($user_id && isAdminOrHasAllDataAccess()) {
                $pivotTable = 'notification_user';
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
                $notificationsQuery = $user->notifications();
            } elseif ($client_id && isAdminOrHasAllDataAccess()) {
                $pivotTable = 'client_notifications';
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
                $notificationsQuery = $client->notifications();
            } else {
                $notificationsQuery = isAdminOrHasAllDataAccess() ? $this->workspace->notifications() : $this->user->notifications();
            }

            if ($search) {
                $notificationsQuery->where(function ($query) use ($search) {
                    $query->where('id', 'like', '%' . $search . '%')
                        ->orWhere('title', 'like', '%' . $search . '%')
                        ->orWhere('message', 'like', '%' . $search . '%');
                });
            }

            if ($status === "read") {
                $notificationsQuery->where(function ($query) use ($pivotTable) {
                    $query->whereNotNull("{$pivotTable}.read_at");
                });
            } elseif ($status === "unread") {
                $notificationsQuery->where(function ($query) use ($pivotTable) {
                    $query->whereNull("{$pivotTable}.read_at");
                });
            }

            if ($notificationType) {
                if ($notificationType === 'system') {
                    $notificationsQuery->where("{$pivotTable}.is_system", 1);
                } elseif ($notificationType === 'push') {
                    $notificationsQuery->where("{$pivotTable}.is_push", 1);
                }
            }
            if (!empty($type)) {
                $notificationsQuery->where('type', $type);
            }
            if ($sort === "status") {
                $notificationsQuery->orderBy(function ($query) use ($pivotTable) {
                    return $query->selectRaw("CASE WHEN {$pivotTable}.read_at IS NULL THEN 0 ELSE 1 END");
                }, $order);
            } else {
                $notificationsQuery->orderBy($sort, $order);
            }

            $total = $notificationsQuery->count();

            $notifications = $notificationsQuery->skip($offset)
                ->take($limit)
                ->get();

            if ($notifications->isEmpty()) {
                return formatApiResponse(
                    false,
                    'Notifications not found',
                    [
                        'total' => 0,
                        'data' => []
                    ]
                );
            }

            $data = $notifications->map(function ($notification) {
                // Define formatNotification function to format notification data
                return formatNotification($notification);
            });

            return formatApiResponse(
                false,
                'Notifications retrieved successfully',
                [
                    'total' => $total,
                    'data' => $data
                ]
            );
        }
    }
    /**
     * Remove the specified notification.
     *
     * This endpoint deletes a notification based on the provided ID. The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Notification Management
     *
     * @urlParam id int required The ID of the notification to be deleted. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Notification deleted successfully.",
     *   "data": []
     * }
     *
     * @response 200 {
     *   "error": true,
     *   "message": "Notification not found.",
     *   "data": []
     * }
     *
     * @response 500 {
     *   "error": true,
     *   "message": "An error occurred while deleting the notification."
     * }
     */

    public function destroy($id)
    {
        try {
            // Find the notification
            $notification = Notification::find($id);
            if ($notification) {
                // Detach the notification from all users
                $notification->users()->detach();

                // Detach the notification from all clients
                $notification->clients()->detach();

                // If the notification is no longer associated with any users or clients, delete it
                if ($notification->users()->count() === 0 && $notification->clients()->count() === 0) {
                    $notification->delete();
                }

                return formatApiResponse(
                    false,
                    'Notification deleted successfully.',
                    [
                        'data' => []
                    ]
                );
            } else {
                return formatApiResponse(
                    true,
                    'Notification not found.',
                    []
                );
            }
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'An error occurred while deleting the notification.'], 500);
        }
    }



    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:notifications,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];

        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $notification = Notification::findOrFail($id);

            // Detach the notification from all users
            $notification->users()->detach();

            // Detach the notification from all clients
            $notification->clients()->detach();

            // Check if the notification is still associated with any users or clients
            if ($notification->users()->count() === 0 && $notification->clients()->count() === 0) {
                // If not associated with any users or clients, delete the notification
                $notification->delete();
            }
        }

        return response()->json(['error' => false, 'message' => 'Notification(s) deleted successfully.']);
    }


    public function update_status(Request $request)
    {
        $notificationId = $request->input('id');
        $needConfirm = $request->input('needConfirm') || false;
        // Find the notification
        $notification =  $this->user->notifications()->findOrFail($notificationId);
        $readAt = isset($notification->pivot->read_at) ? $notification->pivot->read_at : null;
        if ($needConfirm) {
            // Toggle the status
            if (is_null($readAt)) {
                // If the notification is currently unread, mark it as read
                $this->user->notifications()->updateExistingPivot($notification->id, ['read_at' => now()]);
                $message = 'Notification marked as read successfully';
            } else {
                // If the notification is currently read, mark it as unread
                $this->user->notifications()->updateExistingPivot($notification->id, ['read_at' => null]);
                $message = 'Notification marked as unread successfully';
            }

            // Return a response indicating success
            return response()->json(['error' => false, 'message' => $message]);
        } else {
            if (is_null($readAt)) {
                $this->user->notifications()->updateExistingPivot($notification->id, ['read_at' => now()]);
            }
            return response()->json(['error' => false, 'notification' => $notification]);
        }
    }

    public function getUnreadNotifications()
    {
        $unreadNotificationsCount = $this->user->notifications()
            ->wherePivot('read_at', null)
            ->wherePivot('is_system', 1)
            ->count();
        $unreadNotifications = $this->user->notifications()
            ->wherePivot('read_at', null)
            ->wherePivot('is_system', 1)
            ->getQuery()
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();
        $unreadNotificationsHtml = view('partials.unread_notifications')
            ->with('unreadNotificationsCount', $unreadNotificationsCount)
            ->with('unreadNotifications', $unreadNotifications)
            ->render();

        // Return JSON response with count and HTML
        return response()->json([
            'count' => $unreadNotificationsCount,
            'html' => $unreadNotificationsHtml
        ]);
    }

    /**
     * Mark notification(s) as read.
     *
     * This endpoint marks a specific notification as read if a notification ID is provided.
     * If no ID is provided, it will mark all unread notifications as read for the authenticated user.
     * The user must be authenticated to perform this action.
     *
     * @authenticated
     *
     * @group Notification Management
     *
     * @urlParam id int optional The ID of the notification to mark as read. Example: 1
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Notification marked as read successfully."
     * }
     * 
     * @response 200 {
     *   "error": false,
     *   "message": "All notifications marked as read successfully."     
     * }
     *
     * @response 404 {
     *   "error": true,
     *   "message": "Notification not found."
     * }
     * 
     * @response 500 {
     *   "error": true,
     *   "message": "Failed to mark notifications as read."
     * }
     */

    public function markAsReadAPI($id = null)
    {
        try {
            if ($id) {
                // Mark specific notification as read
                $notification = $this->user->notifications()->find($id);

                if (!$notification) {
                    return formatApiResponse(
                        true,
                        'Notification not found.',
                        [
                            'data' => []
                        ]
                    );
                }

                // Check if notification is already marked as read
                if ($notification->pivot->read_at) {
                    return formatApiResponse(
                        true,
                        'Notification is already marked as read.',
                        [
                            'data' => []
                        ]
                    );
                }

                $this->user->notifications()->updateExistingPivot($notification->id, ['read_at' => now()]);
                return formatApiResponse(
                    false,
                    'Notification marked as read successfully.',
                    [
                        'data' => []
                    ]
                );
            } else {
                // Mark all unread notifications as read
                $notifications = $this->user->notifications()->whereNull('read_at')->get();

                if ($notifications->isEmpty()) {
                    return formatApiResponse(
                        true,
                        'No unread notifications found.',
                        [
                            'data' => []
                        ]
                    );
                }

                foreach ($notifications as $notification) {
                    $this->user->notifications()->updateExistingPivot($notification->id, ['read_at' => now()]);
                }

                return formatApiResponse(
                    false,
                    'All notifications marked as read successfully.',
                    [
                        'data' => []
                    ]
                );
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to mark notifications as read.'
            ], 500);
        }
    }
}
