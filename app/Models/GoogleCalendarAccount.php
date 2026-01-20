<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarAccount extends Model
{
    protected $fillable = [
        'user_id',
        'google_sub',
        'access_token',
        'refresh_token',
        'expires_at',
        'scope',
        'token_type',
        'calendar_id',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConnected(): bool
    {
        return $this->revoked_at === null
            && is_string($this->access_token) && $this->access_token !== ''
            && is_string($this->refresh_token) && $this->refresh_token !== '';
    }
}
