<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlidingPuzzle extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image_source_type',
        'image_source_id',
        'grid_size',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'grid_size' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(SlidingAttempt::class, 'sliding_puzzle_id');
    }

    public function imageUrl(): ?string
    {
        $type = strtolower(trim((string) ($this->image_source_type ?? '')));
        $id = trim((string) ($this->image_source_id ?? ''));

        if ($type === 'external') {
            if ($id === '') return null;
            if (str_starts_with($id, '/')) {
                return url($id);
            }
            if (!preg_match('#^https?://#i', $id)) return null;
            return $id;
        }

        if ($type === 'media') {
            if ($id === '' || !ctype_digit($id)) return null;
            // Use the raw image endpoint so it can be embedded in <img>.
            return route('images.view', ['node' => (int) $id]);
        }

        if ($type === 'avatar') {
            // Supported formats:
            // - "123" (user id)
            // - "user:123"
            $userId = null;
            if (preg_match('/^(?:user:)?(\d+)$/', $id, $m)) {
                $userId = (int) $m[1];
            }
            if (!$userId) return null;

            $user = User::query()->find($userId);
            if (!$user) return null;

            if (function_exists('avatarUrl')) {
                return avatarUrl($user);
            }

            return null;
        }

        return null;
    }
}
