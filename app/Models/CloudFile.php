<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudFile extends Model
{
    protected $fillable = [
        'folder_path',
        'original_name',
        'stored_path',
        'mime',
        'size',
        'uploaded_by',
    ];

    public function getSizeHumanAttribute(): string
    {
        $bytes = (int) ($this->size ?? 0);
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
