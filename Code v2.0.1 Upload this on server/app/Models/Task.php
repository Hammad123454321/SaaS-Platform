<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use RyanChandler\Comments\Concerns\HasComments;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model implements HasMedia
{
    use InteractsWithMedia;
    use HasFactory;
    use HasComments;

    protected $fillable = [
        'title',
        'status_id',
        'priority_id',
        'project_id',
        'start_date',
        'due_date',
        'description',
        'note',
        'client_can_discuss',
        'user_id',
        'workspace_id',
        'created_by',
        'parent_id' ,
        'billing_type',
        'completion_percentage',
        'task_list_id'
    ];

    public function registerMediaCollections(): void
    {
        $media_storage_settings = get_settings('media_storage_settings');
        $mediaStorageType = $media_storage_settings['media_storage_type'] ?? 'local';
        if ($mediaStorageType === 's3') {
            $this->addMediaCollection('task-media')->useDisk('s3');
        } else {
            $this->addMediaCollection('task-media')->useDisk('public');
        }
    }

    // New parent-child relationship methods
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function isParentTask(): bool
    {
        return is_null($this->parent_id);
    }

    public function isSubtask(): bool
    {
        return !is_null($this->parent_id);
    }

    // Get all parent tasks for a specific project
    public function scopeParentTasks($query)
    {
        return $query->whereNull('parent_id');
    }

    // Existing relationships
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function clients()
    {
        return $this->project->client;
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function priority()
    {
        return $this->belongsTo(Priority::class);
    }

    public function getresult()
    {
        return substr($this->title, 0, 100);
    }

    public function getlink()
    {
        return str('/tasks/information/' . $this->id);
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function notificationsForTask()
    {
        return $this->hasMany(Notification::class, 'type_id')
            ->whereIn('type', ['task', 'task_comment_mention']);
    }

    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function pinned()
    {
        return $this->morphMany(Pinned::class, 'pinnable');
    }

    public function reminders()
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function recurringTask()
    {
        return $this->hasOne(RecurringTask::class);
    }

    public function statusTimelines()
    {
        return $this->morphMany(StatusTimeline::class, 'entity');
    }
    public function taskList()
    {
        return $this->belongsTo(TaskList::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TaskTimeEntry::class);
    }


    public function customFields()
    {
        return $this->morphMany(CustomFieldable::class, 'custom_fieldable');
    }

    // In your Task.php model
    public function customFieldValues()
    {
        return $this->morphMany('App\Models\CustomFieldable', 'custom_fieldable');
    }

    public function getCustomFieldValue($fieldId)
    {
        $customFieldValue = $this->customFieldValues()->where('custom_field_id', $fieldId)->first();
        return $customFieldValue ? $customFieldValue->value : null;
    }
}
