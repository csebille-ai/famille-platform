<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsItem extends Model
{
    protected $fillable = [
        'url_hash',
        'url',
        'title',
        'excerpt',
        'image_url',
        'source',
        'tag',
        'published_at',
        'fetched_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'fetched_at' => 'datetime',
    ];
}
