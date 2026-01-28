<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'title',
        'template_key',
        'category',
        'difficulty',
        'is_active',
        'questions_count',
        'source',
        'estimated_duration_minutes',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'questions_count' => 'integer',
        'estimated_duration_minutes' => 'integer',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function bestScores(): HasMany
    {
        return $this->hasMany(QuizBestScore::class);
    }
}
