<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Priority extends Model
{
    use HasFactory;


    protected $fillable = [
        'title',
        'color',
        'slug'
    ];

    public function projects($considerWorkspace = true)
    {
        $query = $this->hasMany(Project::class);

        if ($considerWorkspace) {
            $query->where('projects.workspace_id', getWorkspaceId());
        }

        return $query;
    }

    public function tasks($considerWorkspace = true)
    {
        $query = $this->hasMany(Task::class);

        if ($considerWorkspace) {
            $query->where('tasks.workspace_id', getWorkspaceId());
        }

        return $query;
    }
}
