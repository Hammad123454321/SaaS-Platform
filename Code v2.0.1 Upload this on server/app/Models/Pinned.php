<?php

// App\Models\Pinned.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pinned extends Model
{
    protected $table = 'pinned';
    
    protected $fillable = [
        'user_id',
        'client_id',
        'pinnable_type',
        'pinnable_id',
    ];

    /**
     * Get the parent pinnable model (user, client, etc.)
     */
    public function pinnable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user associated with the pinned item.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the client associated with the pinned item.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
