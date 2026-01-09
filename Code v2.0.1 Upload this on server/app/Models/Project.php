<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use RyanChandler\Comments\Concerns\HasComments;

class Project extends Model implements HasMedia
{
    use InteractsWithMedia;
    use HasFactory;
    use HasComments;

    protected $fillable = [
        'title',
        'status_id',
        'priority_id',
        'budget',
        'start_date',
        'end_date',
        'description',
        'note',
        'client_can_discuss',
        'user_id',
        'client_id',
        'workspace_id',
        'task_accessibility',
        'is_favorite',
        'created_by',
        'enable_tasks_time_entries'
    ];

    public function registerMediaCollections(): void
    {
        $media_storage_settings = get_settings('media_storage_settings');
        $mediaStorageType = $media_storage_settings['media_storage_type'] ?? 'local';
        if ($mediaStorageType === 's3') {
            $this->addMediaCollection('project-media')->useDisk('s3');
        } else {
            $this->addMediaCollection('project-media')->useDisk('public');
        }
    }

    public function scopeFilter($query, array $filters)
    {
        if ($filters['search_projects'] ?? false) {
            $query->where('title', 'like', '%' . request('search_projects') . '%')
                ->orWhere('status', 'like', '%' . request('search_projects') . '%');
        }
    }
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
    public function tasks()
    {
        return $this->hasMany(Task::class)->where('tasks.workspace_id', getWorkspaceId());
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class);
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
        return str('/projects/information/' . $this->id);
    }
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
    public function milestones()
    {
        return $this->hasMany(Milestone::class)->where('milestones.workspace_id', getWorkspaceId());
    }
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
    public function notificationsForProject()
    {
        return $this->hasMany(Notification::class, 'type_id')
        ->whereIn('type', ['project', 'project_comment_mention']);
    }
    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }
    public function pinned()
    {
        return $this->morphMany(Pinned::class, 'pinnable');
    }

    public function statusTimelines()
    {
        return $this->morphMany(StatusTimeline::class, 'entity');
    }

    /**
     * Get a specific custom field value
     */
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
