<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RyanChandler\Comments\Models\Comment as BaseComment;

class Comment extends BaseComment
{
    public function attachments()
    {
        return $this->hasMany(CommentAttachment::class);
    }

    public function commenter()
    {
        return $this->morphTo();
    }
}
