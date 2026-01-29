<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlidingAttempt extends Model
{
    protected $fillable = [
        'sliding_puzzle_id',
        'user_id',
        'grid_size',
        'moves_count',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'grid_size' => 'integer',
            'moves_count' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(SlidingPuzzle::class, 'sliding_puzzle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }
}
