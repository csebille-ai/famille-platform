<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Resource extends Model
{
    protected $fillable = [
        'title',
        'section',
        'folder',
        'concerned_user_id',
        'category',
        'content',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'created_by',
    ];

    public function concernedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concerned_user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResourceFile::class);
    }

    /**
     * Returns files for display. Falls back to legacy attachment_* fields.
     */
    public function displayFiles(): Collection
    {
        $files = $this->relationLoaded('files')
            ? $this->files
            : $this->files()->get();

        if ($files->isNotEmpty()) {
            return $files;
        }

        if ($this->attachment_path) {
            return collect([
                new ResourceFile([
                    'resource_id' => $this->id,
                    'path' => $this->attachment_path,
                    'name' => $this->attachment_name ?: 'resource',
                    'mime' => $this->attachment_mime,
                    'size' => $this->attachment_size,
                ]),
            ]);
        }

        return collect();
    }
}
