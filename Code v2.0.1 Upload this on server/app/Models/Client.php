<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;


class Client extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasPermissions, CanResetPassword;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'country_code',
        'country_iso_code',
        'company',
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
        'acct_create_mail_sent',
        'email_verification_mail_sent',
        'internal_purpose'
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

    protected $guard = 'client';

    public function projects()
    {
        return $this->belongsToMany(Project::class)->where('projects.workspace_id', getWorkspaceId());
    }

    public function meetings()
    {
        return $this->belongsToMany(Meeting::class)->where('meetings.workspace_id', getWorkspaceId());
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class);
    }

    public function getresult()
    {
        return str($this->first_name . " " . $this->last_name);
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

    public function status_projects($status_id)
    {
        return $this->belongsToMany(Project::class, 'client_project')
            ->where('projects.workspace_id', getWorkspaceId())->where('projects.status_id', $status_id);
    }
    public function status_tasks($status_id)
    {
        return Task::whereHas('project.clients', function ($query) use ($status_id) {
            $query->where('clients.id', getAuthenticatedUser()->id)->where('tasks.workspace_id', getWorkspaceId())->where('tasks.status_id', $status_id);
        })->get();
    }

    public function tasks()
    {
        return Task::whereIn('project_id', $this->projects->pluck('id'));
    }

    public function project_tasks($project_id)
    {
        return Task::whereHas('project.clients', function ($query) use ($project_id) {
            $query->where('clients.id', getAuthenticatedUser()->id)->where('tasks.workspace_id', getWorkspaceId())->where('tasks.project_id', $project_id);
        })->get();
    }

    public function contracts()
    {
        return Contract::where(function ($query) {
            $query->where('created_by', 'c_' . $this->getKey())
                ->orWhere('client_id', $this->getKey());
        })
            ->where('workspace_id', getWorkspaceId())
            ->get();
    }
    public function notes($search = '', $orderBy = 'id', $direction = 'desc', $limit = null, $offset = 0)
    {
        $query = Note::where(function ($query) {
            $query->where('creator_id', 'c_' . $this->getKey())
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

    public function estimates_invoices($status = '', $type = '')
    {
        return EstimatesInvoice::where(function ($query) {
            $query->where('created_by', 'c_' . $this->getKey())
                ->orWhere('client_id', $this->getKey()); // Include orWhere for client_id
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
            $query->where('created_by', 'c_' . $userId)
                ->where('workspace_id', $workspaceId);
        })
            ->get();
    }

    public function payments()
    {
        $userId = $this->getKey(); // Get the current user's ID
        $workspaceId = getWorkspaceId(); // Retrieve the current workspace ID

        return Payment::where(function ($query) use ($userId, $workspaceId) {
            $query->where('created_by', 'c_' . $userId)
                ->where('workspace_id', $workspaceId);
        })
            ->get();
    }

    public function payslips()
    {
        $workspaceId = getWorkspaceId();

        return Payslip::where('created_by', 'c_' . $this->getKey())
            ->where('workspace_id', $workspaceId);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function profile()
    {
        return $this->morphOne(Profile::class, 'profileable');
    }

    public function getlink()
    {
        return str('/clients/profile/show/' . $this->id);
    }
    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'client_notifications')->where('notifications.workspace_id', getWorkspaceId())->withPivot('read_at', 'is_system', 'is_push');
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
}
