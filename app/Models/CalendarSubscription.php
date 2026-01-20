<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'is_enabled',
        'revoked_at',
        'last_used_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function httpsUrl(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        return $base . '/calendar/family/' . $this->token . '.ics';
    }

    public function webcalUrl(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: '';
        $host = (string) $host;
        if ($host === '') {
            // Fallback: keep a working URL even if app.url is misconfigured.
            $https = $this->httpsUrl();
            return preg_replace('/^https?:\/\//i', 'webcal://', $https) ?: $https;
        }

        return 'webcal://' . $host . '/calendar/family/' . $this->token . '.ics';
    }
}
