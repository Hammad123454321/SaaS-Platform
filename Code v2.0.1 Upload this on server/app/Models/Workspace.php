<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Workspace extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'user_id',
        'is_primary'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class);
    }

    public function getresult()
    {
        return substr($this->title, 0, 100);
    }

    public function getlink()
    {
        return str('/workspaces');
    }
    public function projects()
    {
        return $this->hasMany(Project::class);
    }
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }

    public function todos()
    {
        return $this->hasMany(Todo::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function leave_requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function contract_types()
    {
        return $this->hasMany(ContractType::class)->orWhereNull('workspace_id');
    }

    public function payment_methods()
    {
        return $this->hasMany(PaymentMethod::class)->orWhereNull('workspace_id');
    }
    public function allowances()
    {
        return $this->hasMany(Allowance::class);
    }
    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }
    public function timesheets()
    {
        return $this->hasMany(TimeTracker::class);
    }
    public function taxes()
    {
        return $this->hasMany(Tax::class);
    }
    public function units()
    {
        return $this->hasMany(Unit::class);
    }
    public function items()
    {
        return $this->hasMany(Item::class);
    }
    public function estimates_invoices($status = '', $type = '')
    {
        $query = $this->hasMany(EstimatesInvoice::class);

        if ($type != '') {
            $query->where('type', $type);
        }

        if ($status != '') {
            $query->where('status', $status);
        }

        return $query;
    }
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
    public function expense_types()
    {
        return $this->hasMany(ExpenseType::class)->orWhereNull('workspace_id');
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function notifications(): Builder
    {
        $userId = getAuthenticatedUser()->id; // Assuming you're using Laravel's authentication

        return Notification::leftJoin('notification_user', function ($join) use ($userId) {
            $join->on('notifications.id', '=', 'notification_user.notification_id')
                ->where('notification_user.user_id', $userId)
                ->where('notifications.workspace_id', $this->id);
        })
            ->leftJoin('client_notifications', function ($join) use ($userId) {
                $join->on('notifications.id', '=', 'client_notifications.notification_id')
                    ->where('client_notifications.client_id', $userId) // Assuming client_notifications have a user_id column
                    ->where('notifications.workspace_id', $this->id);
            })
            ->select(
                'notifications.*',
                'notification_user.read_at AS notification_user_read_at', // Select read_at from notification_user
                'notification_user.is_system AS notification_user_is_system', // Select is_system from notification_user
                'notification_user.is_push AS notification_user_is_push', // Select is_push from notification_user
                'client_notifications.read_at AS client_notifications_read_at', // Select read_at from client_notifications
                'client_notifications.is_system AS client_notifications_is_system', // Select is_system from client_notifications
                'client_notifications.is_push AS client_notifications_is_push' // Select is_push from client_notifications
            )
            ->distinct('notifications.id');
    }
    public function notificationsForWorkspace()
    {
        return $this->hasMany(Notification::class, 'type_id')->where('type', 'workspace');
    }
    public function activity_logs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function lead_sources()
    {
        return DB::table('lead_sources')
            ->where('workspace_id', $this->id)
            ->orWhere(function ($query) {
            $query->whereNull('workspace_id')
                ->where('is_default', 1);
            });
    }


    public function lead_stages()
    {
        return DB::table('lead_stages')
            ->where('workspace_id', $this->id)
            ->orWhere(function ($query) {
            $query->whereNull('workspace_id')
                ->where('is_default', 1);
            });
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function scheduledEmails()
    {
        return $this->hasMany(ScheduledEmail::class);
    }
    public function email_templates()
    {
        return $this->hasMany(EmailTemplate::class);
    }
}
