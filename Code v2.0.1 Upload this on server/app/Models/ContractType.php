<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractType extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'workspace_id'
    ];

    public function contracts()
    {
        return $this->hasMany(Contract::class)->where('contracts.workspace_id', getWorkspaceId());
    }

    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where(function ($q) use ($workspaceId) {
            $q->where('workspace_id', $workspaceId)
                ->orWhereNull('workspace_id');
        });
    }
}
