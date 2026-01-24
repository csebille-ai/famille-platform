<?php

namespace App\Services\Games;

use Chess\Exception\UnknownNotationException;
use Chess\Variant\Classical\FenToBoardFactory;

class ChessRules
{
    public const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    /**
     * Apply a LAN/UCI move (e.g. e2e4, g1f3, e7e8q, e2-e4, e7-e8=q) on a given FEN.
     *
     * @return array{new_fen:string,san:string,new_turn:'w'|'b',is_finished:bool,finish_reason:string|null}
     */
    public function applyUci(string $fen, string $uci): array
    {
        $fen = trim($fen);
        $uci = strtolower(trim($uci));

        if ($fen === '') {
            $fen = self::START_FEN;
        }

        // Normalize to dashed LAN format understood by php-chess' playLan().
        // Accept both UCI (e2e4, e7e8q) and dashed LAN (e2-e4, e7-e8=q).
        $lan = null;
        if (preg_match('/^([a-h][1-8])([a-h][1-8])([qrbn])?$/', $uci, $m)) {
            $lan = $m[1] . '-' . $m[2] . ($m[3] ?? '');
        } elseif (preg_match('/^([a-h][1-8])-([a-h][1-8])(?:=)?([qrbn])?$/', $uci, $m)) {
            $lan = $m[1] . '-' . $m[2] . ($m[3] ?? '');
        }
        if (!$lan) {
            throw new UnknownNotationException();
        }

        $board = FenToBoardFactory::create($fen);
        $turn = $board->turn;

        if ($turn !== 'w' && $turn !== 'b') {
            throw new UnknownNotationException();
        }

        $ok = $board->playLan($turn, $lan);
        if (!$ok) {
            throw new UnknownNotationException();
        }

        $newFen = $board->toFen();
        $newTurn = $board->turn;

        $san = '';
        $last = $board->history[count($board->history) - 1] ?? null;
        if (is_array($last) && isset($last['pgn']) && is_string($last['pgn'])) {
            $san = trim($last['pgn']);
        }
        if ($san === '') {
            // Fallback: keep something readable.
            $san = $lan;
        }

        $isFinished = false;
        $finishReason = null;
        try {
            if ($board->isMate()) {
                $isFinished = true;
                $finishReason = 'mate';
            } elseif ($board->isStalemate()) {
                $isFinished = true;
                $finishReason = 'stalemate';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'new_fen' => $newFen,
            'san' => $san,
            'new_turn' => $newTurn,
            'is_finished' => $isFinished,
            'finish_reason' => $finishReason,
        ];
    }
}
