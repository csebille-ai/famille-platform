<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChessMove extends Model
{
    protected $fillable = [
        'chess_game_id',
        'move_number',
        'uci',
        'san',
        'fen_before',
        'fen_after',
        'played_by_user_id',
        'played_by_team',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(ChessGame::class, 'chess_game_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'played_by_user_id');
    }
}
