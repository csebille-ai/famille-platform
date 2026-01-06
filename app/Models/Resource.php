<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resource extends Model
{
    protected $fillable = [
        'title',
        'section',
        'folder',
        'concerned_user_id',
        'category',
        'content',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'created_by',
    ];

    public function concernedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concerned_user_id');
    }
}
