<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CloudNode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'stored_path',
        'storage_disk',
        'public_url',
        'mime',
        'size',
        'focal_x',
        'focal_y',
        'uploaded_by',
    ];

    protected $casts = [
        'focal_x' => 'float',
        'focal_y' => 'float',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'image_likes', 'cloud_node_id', 'user_id')
            ->withTimestamps();
    }

    public function isFolder(): bool
    {
        return $this->type === 'folder';
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    public function getSizeHumanAttribute(): ?string
    {
        if ($this->size === null) {
            return null;
        }

        $bytes = (int) $this->size;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        $formatted = $value >= 10 ? number_format($value, 0) : number_format($value, 1);
        return $formatted . ' ' . $units[$unitIndex];
    }
}
