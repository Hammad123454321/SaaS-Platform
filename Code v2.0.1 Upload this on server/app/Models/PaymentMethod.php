<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;
    protected $fillable = [
        'workspace_id',
        'title'
    ];

    public function payslips()
    {
        return $this->hasMany(Payslip::class)->where('payslips.workspace_id', getWorkspaceId());
    }
    public function payments()
    {
        return $this->hasMany(Payment::class)->where('payments.workspace_id', getWorkspaceId());
    }
    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where(function ($q) use ($workspaceId) {
            $q->where('workspace_id', $workspaceId)
                ->orWhereNull('workspace_id');
        });
    }
}
