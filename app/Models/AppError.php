<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppError extends Model
{
    public $timestamps = false;

    protected $table = 'app_errors';

    protected $fillable = [
        'created_at',
        'level',
        'message',
        'route',
        'user_id',
        'stacktrace',
        'context',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'context' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
