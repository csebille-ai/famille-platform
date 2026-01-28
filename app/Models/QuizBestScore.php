<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizBestScore extends Model
{
    protected $fillable = [
        'quiz_id',
        'user_id',
        'best_score',
        'best_attempt_id',
    ];

    protected $casts = [
        'best_score' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bestAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'best_attempt_id');
    }
}
