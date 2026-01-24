<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChessGame extends Model
{
    protected $fillable = [
        'status',
        'current_fen',
        'turn',
        'pgn',
        'last_move_at',
        'created_by',
    ];

    protected $casts = [
        'last_move_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChessTeamMember::class);
    }

    public function moves(): HasMany
    {
        return $this->hasMany(ChessMove::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
