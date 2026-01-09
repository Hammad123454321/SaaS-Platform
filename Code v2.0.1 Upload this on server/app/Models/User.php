<?php

namespace App\Models;

use Carbon\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'country_code',
        'country_iso_code',
        'address',
        'city',
        'state',
        'country',
        'zip',
        'photo',
        'dob',
        'doj',
        'status',
        'email_verified_at',
        'default_workspace_id',
        'fcm_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function scopeFilter($query, array $filters)
    {
        if ($filters['search'] ?? false) {
            $query->where('first_name', 'like', '%' . request('search') . '%')
                ->orWhere('last_name', 'like', '%' . request('search') . '%')
                ->orWhere('role', 'like', '%' . request('search') . '%');
        }
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class)->where('projects.workspace_id', getWorkspaceId());
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'task_user')
            ->where('tasks.workspace_id', getWorkspaceId());
    }

    public function status_tasks($status_id)
    {
        return $this->belongsToMany(Task::class, 'task_user')
            ->where('tasks.workspace_id', getWorkspaceId())->where('tasks.status_id', $status_id);
    }

    public function status_projects($status_id)
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->where('projects.workspace_id', getWorkspaceId())->where('projects.status_id', $status_id);
    }

    public function project_tasks($project_id)
    {
        return $this->belongsToMany(Task::class, 'task_user')
            ->where('tasks.workspace_id', getWorkspaceId())->where('tasks.project_id', $project_id)->get();
    }

    public function meetings($status = null)
    {
        $meetings = $this->belongsToMany(Meeting::class)->where('workspace_id', '=', getWorkspaceId());

        if ($status !== null && $status == 'ongoing') {
            $meetings->where('start_date_time', '<=', Carbon::now(config('app.timezone')))
                ->where('end_date_time', '>=', Carbon::now(config('app.timezone')));
        }

        return $meetings;
    }
    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class);
    }
    public function role()
    {
        return $this->belongsTo(Role::class);
    }


    public function todos($status = null, $search = '')
    {
        $query = $this->morphMany(Todo::class, 'creator')->where('workspace_id', getWorkspaceId());

        if ($status !== null) {
            $query->where('is_completed', $status);
        }
        if ($search !== '') {
            $query->where('title', 'like', '%' . $search . '%');
        }

        return $query;
    }

    public function payslips()
    {
        $workspaceId = getWorkspaceId();

        return $this->hasMany(Payslip::class, 'user_id')
            ->where('workspace_id', $workspaceId)
            ->orWhere(function ($query) use ($workspaceId) {
                $query->where('created_by', 'u_' . $this->getKey())
                    ->where('workspace_id', $workspaceId);
            });
    }


    public function contracts()
    {
        return Contract::where(function ($query) {
            $query->where('created_by', 'u_' . $this->getKey());
        })
            ->where('workspace_id', getWorkspaceId())
            ->get();
    }

    public function profile()
    {
        return $this->morphOne(Profile::class, 'profileable');
    }

    public function getresult()
    {
        return str($this->first_name . " " . $this->last_name);
    }

    public function leave_requests()
    {
        return $this->hasMany(LeaveRequest::class)->where('workspace_id', getWorkspaceId());
    }

    public function leaveEditors()
    {
        return $this->hasMany(LeaveEditor::class, 'user_id');
    }
    public function notes($search = '', $orderBy = 'id', $direction = 'desc', $limit = null, $offset = 0)
    {
        $query = Note::where(function ($query) {
            $query->where('creator_id', 'u_' . $this->getKey())
                ->where('workspace_id', getWorkspaceId());
        });

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy($orderBy, $direction);

        // Apply limit and offset if limit is provided
        if ($limit !== null) {
            $notes = $query->limit($limit)
                ->offset($offset)
                ->get();
        } else {
            $notes = $query->get();
        }

        return $notes;
    }


    public function timesheets()
    {
        return $this->hasMany(TimeTracker::class, 'user_id', 'id')
            ->where('workspace_id', getWorkspaceId());
    }

    public function estimates_invoices($status = '', $type = '')
    {
        return EstimatesInvoice::where(function ($query) {
            $query->where('created_by', 'u_' . $this->getKey());
        })
            ->where('workspace_id', getWorkspaceId()) // Apply workspace_id filter
            ->when($status != '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($type != '', function ($query) use ($type) {
                $query->where('type', $type);
            });
    }

    public function expenses()
    {
        $userId = $this->getKey(); // Get the current user's ID
        $workspaceId = getWorkspaceId(); // Retrieve the current workspace ID

        return Expense::where(function ($query) use ($userId, $workspaceId) {
            $query->where('user_id', $userId)
                ->where('workspace_id', $workspaceId);
        })
            ->orWhere(function ($query) use ($userId, $workspaceId) {
                $query->where('created_by', 'u_' . $userId)
                    ->where('workspace_id', $workspaceId);
            })
            ->get();
    }

    public function payments()
    {
        $userId = $this->getKey(); // Get the current user's ID
        $workspaceId = getWorkspaceId(); // Retrieve the current workspace ID

        return Payment::where(function ($query) use ($userId, $workspaceId) {
            $query->where('user_id', $userId)
                ->where('workspace_id', $workspaceId);
        })
            ->orWhere(function ($query) use ($userId, $workspaceId) {
                $query->where('created_by', 'u_' . $userId)
                    ->where('workspace_id', $workspaceId);
            })
            ->get();
    }

    public function can($ability, $arguments = [])
    {
        $isAdmin = $this->hasRole('admin'); // Check if the user has the 'admin' role

        // Check if the user is an admin or has the specific permission
        if ($isAdmin || $this->hasPermissionTo($ability)) {
            return true;
        }

        // For other cases, use the original can() method
        return parent::can($ability, $arguments);
    }


    public function getlink()
    {
        return str('/users/profile/show/' . $this->id);
    }
    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'notification_user')->where('notifications.workspace_id', getWorkspaceId())->withPivot('read_at', 'is_system', 'is_push');
    }

    public function visibleLeaveRequests()
    {
        return $this->belongsToMany(LeaveRequest::class, 'leave_request_visibility', 'user_id', 'leave_request_id');
    }

    public function leaveRequests()
    {
        $workspaceId = getWorkspaceId(); // Retrieve the current workspace ID

        return $this->hasMany(LeaveRequest::class)
            ->where('workspace_id', $workspaceId);
    }

    public function defaultWorkspace()
    {
        return $this->belongsTo(Workspace::class, 'default_workspace_id');
    }
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoriteProjects()
    {
        return $this->favorites()->where('favoritable_type', Project::class);
    }
    public function favoriteTasks()
    {
        return $this->favorites()->where('favoritable_type', Task::class);
    }
    public function pinned()
    {
        return $this->hasMany(Pinned::class);
    }

    public function pinnedProjects()
    {
        return $this->pinned()->where('pinnable_type', Project::class);
    }
    public function pinnedTasks()
    {
        return $this->pinned()->where('pinnable_type', Task::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }
    public function scheduledEmails()
    {
        return $this->hasMany(ScheduledEmail::class, 'user_id')->where('workspace_id', getWorkspaceId());
    }
}
