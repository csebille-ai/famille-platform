<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'provider',
        'key',
        'public_url',
        'mime',
        'size_bytes',
        'kind',
        'context',
        'user_id',
        'cloud_node_id',
        'video_id',
        'chat_message_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'cloud_node_id' => 'integer',
        'video_id' => 'integer',
        'chat_message_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
