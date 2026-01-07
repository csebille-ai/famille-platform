<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title',
        'type',
        'starts_on',
    ];

    protected $casts = [
        'starts_on' => 'date',
    ];
}
