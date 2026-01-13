<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    protected $fillable = ['cloud_node_id', 'title', 'category', 'video_path', 'storage_disk', 'poster_path', 'description', 'created_by', 'duration_seconds', 'focal_x', 'focal_y'];

    protected $casts = [
        'duration_seconds' => 'integer',
        'focal_x' => 'float',
        'focal_y' => 'float',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
