<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function initials(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';

        $a = $first !== '' ? mb_substr($first, 0, 1, 'UTF-8') : '';
        $b = $last !== '' ? mb_substr($last, 0, 1, 'UTF-8') : '';

        $initials = mb_strtoupper($a . $b, 'UTF-8');
        return $initials !== '' ? $initials : '?';
    }

    /**
     * Deterministic Tailwind color classes for UI badges.
     * Uses Tailwind palette only (no custom colors).
     *
     * @return array{solid:string, soft:string}
     */
    public function uiColor(): array
    {
        $palette = [
            ['solid' => 'bg-indigo-600 text-white', 'soft' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
            ['solid' => 'bg-emerald-600 text-white', 'soft' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            ['solid' => 'bg-sky-600 text-white', 'soft' => 'bg-sky-50 text-sky-700 border-sky-200'],
            ['solid' => 'bg-violet-600 text-white', 'soft' => 'bg-violet-50 text-violet-700 border-violet-200'],
            ['solid' => 'bg-rose-600 text-white', 'soft' => 'bg-rose-50 text-rose-700 border-rose-200'],
            ['solid' => 'bg-amber-600 text-white', 'soft' => 'bg-amber-50 text-amber-900 border-amber-200'],
            ['solid' => 'bg-teal-600 text-white', 'soft' => 'bg-teal-50 text-teal-700 border-teal-200'],
        ];

        $id = (int) ($this->id ?? 0);
        $index = $id > 0 ? ($id % count($palette)) : 0;

        return $palette[$index];
    }
}
