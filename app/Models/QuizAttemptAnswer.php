<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptAnswer extends Model
{
    protected $fillable = [
        'attempt_id',
        'question_id',
        'choice_id',
        'is_correct',
        'response_time_ms',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'response_time_ms' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }

    public function choice(): BelongsTo
    {
        return $this->belongsTo(QuizChoice::class, 'choice_id');
    }
}
