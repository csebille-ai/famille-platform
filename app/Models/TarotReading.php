<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarotReading extends Model
{
    protected $fillable = [
        'user_id',
        'question',
        'spread',
        'cards',
        'interpretation',
        'is_shared',
    ];

    protected $casts = [
        'cards' => 'array',
        'is_shared' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
