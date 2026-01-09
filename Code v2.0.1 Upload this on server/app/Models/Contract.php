<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;
    protected $fillable = [
        'workspace_id',
        'title',
        'value',
        'start_date',
        'end_date',
        'client_id',
        'project_id',
        'contract_type_id',
        'description',
        'created_by',
        'signed_pdf'
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
    public function contract_type()
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id');
    }
}
