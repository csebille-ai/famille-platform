<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleEventLink extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
        'google_calendar_id',
        'google_event_id',
        'last_pushed_at',
    ];

    protected $casts = [
        'last_pushed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
