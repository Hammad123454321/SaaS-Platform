<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'from_date',
        'to_date',
        'from_time',
        'to_time',
        'reason',
        'comment',
        'status',
        'visible_to_all',
        'action_by'
    ];

    public function visibleToUsers()
    {
        return $this->belongsToMany(User::class, 'leave_request_visibility', 'leave_request_id', 'user_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function notificationsForLeaveRequest()
    {
        return $this->hasMany(Notification::class, 'type_id')->where('type', 'leave_request');
    }
}
