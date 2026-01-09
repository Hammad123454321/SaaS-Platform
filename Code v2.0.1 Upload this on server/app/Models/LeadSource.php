<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class LeadSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
    ];
    protected static function booted(): void
    {
        static::addGlobalScope('defaultOrWorkspace', function (Builder $builder) {
            $workspaceId = getWorkspaceId(); // 🔁 Replace with your actual helper or logic

            $builder->where(function ($query) use ($workspaceId) {
                $query->where(function ($q) {
                    $q->where('is_default', true)->whereNull('workspace_id');
                })->orWhere('workspace_id', $workspaceId);
            });
        });
    }
    public function leads()
    {
        return $this->hasMany(Lead::class, 'source_id');
    }
}
