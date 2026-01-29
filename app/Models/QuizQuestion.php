<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    protected $fillable = [
        'quiz_id',
        'question_text',
        'image_url',
        'points',
        'order',
        'subject_qid',
        'correct_answer_qid',
    ];

    protected $casts = [
        'points' => 'integer',
        'order' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function choices(): HasMany
    {
        return $this->hasMany(QuizChoice::class, 'question_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class, 'question_id');
    }

    public function correctChoice(): ?QuizChoice
    {
        return $this->choices()->where('is_correct', true)->first();
    }
}
