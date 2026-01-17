<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class Event extends Model
{
    protected $fillable = [
        'created_by_user_id',
        'title',
        'description',
        'location',
        'start_at',
        'end_at',
        'all_day',
        'timezone',
        'visibility',
        'is_important',
        'category',
        'color_tag',
        'notify',
        'reminder_minutes',
        'reminder_at',
        'reminded_at',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'all_day' => 'boolean',
        'is_important' => 'boolean',
        'notify' => 'boolean',
        'reminder_minutes' => 'integer',
        'reminder_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isPrivate(): bool
    {
        return ($this->visibility ?? 'family') === 'private';
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $isAdmin = Gate::forUser($user)->allows('manage-users');
        if ($isAdmin) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', '=', 'family')
                ->orWhere(function (Builder $q2) use ($user) {
                    $q2->where('visibility', '=', 'private')
                        ->where('created_by_user_id', '=', $user->id);
                });
        });
    }
}
