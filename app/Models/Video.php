<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = ['title', 'category', 'video_path', 'poster_path', 'description', 'created_by'];
}
