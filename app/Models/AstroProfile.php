<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AstroProfile extends Model
{
    protected $fillable = [
        'user_id',
        'western_sign',
        'western_element',
        'chinese_animal',
        'chinese_element',
        'chinese_yin_yang',
        'life_path',
        'ascendant_sign',
        'moon_sign',
        'moon_lon',
        'moon_deg_in_sign',
        'astro_hash',
        'astro_computed_at',
        'natal',
        'archetype',
        'talents',
        'weakness',
        'signature',
        'computed_at',
    ];

    protected $casts = [
        'natal' => 'array',
        'talents' => 'array',
        'computed_at' => 'datetime',
        'astro_computed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
