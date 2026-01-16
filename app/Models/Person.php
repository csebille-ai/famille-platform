<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Person extends Model
{
    /** @use HasFactory<\Database\Factories\PersonFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'birth_date',
        'avatar_path',
        'is_child',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_child' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Guardians (users) for a child person.
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardianships', 'child_person_id', 'guardian_user_id')
            ->withPivot(['can_edit', 'notify'])
            ->withTimestamps();
    }

    public function displayName(): string
    {
        $first = trim((string) $this->first_name);
        $last = trim((string) $this->last_name);
        $full = trim($first . ' ' . $last);
        return $full !== '' ? $full : 'Quelqu’un';
    }

    public function initials(): string
    {
        $first = trim((string) $this->first_name);
        $last = trim((string) $this->last_name);

        $a = $first !== '' ? mb_substr($first, 0, 1, 'UTF-8') : '';
        $b = $last !== '' ? mb_substr($last, 0, 1, 'UTF-8') : '';

        $initials = mb_strtoupper($a . $b, 'UTF-8');
        return $initials !== '' ? $initials : '?';
    }
}
