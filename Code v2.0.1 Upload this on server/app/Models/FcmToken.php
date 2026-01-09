<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FcmToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_id',
        'fcm_token',
    ];

    /**
     * Get the user associated with the FcmToken.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the client associated with the FcmToken.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
