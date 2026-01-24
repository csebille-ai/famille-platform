<?php

namespace App\Services\Games;

use App\Models\ChessGame;
use Illuminate\Support\Facades\DB;

class ChessGameService
{
    public function getOrCreateActiveGame(int $createdByUserId): ChessGame
    {
        $active = ChessGame::query()->active()->orderByDesc('id')->first();
        if ($active) return $active;

        return DB::transaction(function () use ($createdByUserId) {
            $existing = ChessGame::query()->lockForUpdate()->active()->orderByDesc('id')->first();
            if ($existing) return $existing;

            return ChessGame::query()->create([
                'status' => 'active',
                'current_fen' => ChessRules::START_FEN,
                'turn' => 'w',
                'pgn' => null,
                'last_move_at' => null,
                'created_by' => $createdByUserId,
            ]);
        });
    }

    public function formatPgnAppend(string $existing, int $moveNumber, string $team, string $san): string
    {
        $existing = trim($existing);
        $san = trim($san);
        if ($san === '') return $existing;

        $n = (int) ceil($moveNumber / 2);

        if ($team === 'w') {
            $chunk = $n . '. ' . $san;
            return trim($existing . ($existing !== '' ? ' ' : '') . $chunk);
        }

        // black move
        return trim($existing . ($existing !== '' ? ' ' : '') . $san);
    }
}
