<?php

namespace App\Services\Games;

use Chess\Exception\UnknownNotationException;
use Chess\Variant\Classical\FenToBoardFactory;

class ChessRules
{
    public const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    // Helps verify which move-normalization logic is deployed.
    public const MOVE_PARSER_VERSION = 'uci+lan->playLan(dashed,compact)+san@2026-01-24';

    /**
     * Lightweight sanity check for production debugging.
     *
     * @return array<string,mixed>
     */
    public function selfTest(): array
    {
        $out = [
            'php' => PHP_VERSION,
            'mbstring' => extension_loaded('mbstring'),
        ];

        try {
            $board = FenToBoardFactory::create(self::START_FEN);
            $turn = (string) ($board->turn ?? '');
            $out['board_turn'] = $turn;
            $out['playLan_d2d4'] = $board->playLan($turn, 'd2d4');
            $board2 = FenToBoardFactory::create(self::START_FEN);
            $turn2 = (string) ($board2->turn ?? '');
            $out['play_d4'] = $board2->play($turn2, 'd4');
        } catch (\Throwable $e) {
            $out['error'] = get_class($e);
            $out['error_msg'] = substr((string) $e->getMessage(), 0, 160);
        }

        return $out;
    }

    /**
     * Apply a LAN/UCI move (e.g. e2e4, g1f3, e7e8q, e2-e4, e7-e8=q) on a given FEN.
     *
     * @return array{new_fen:string,san:string,new_turn:'w'|'b',is_finished:bool,finish_reason:string|null}
     */
    public function applyUci(string $fen, string $uci, ?string $sanInput = null): array
    {
        $fen = trim($fen);
        $uci = strtolower(trim($uci));
        $sanInput = is_string($sanInput) ? trim($sanInput) : null;

        if ($fen === '') {
            $fen = self::START_FEN;
        }

        // Normalize to dashed LAN format understood by php-chess' playLan().
        // Accept both UCI (e2e4, e7e8q) and dashed LAN (e2-e4, e7-e8=q).
        $lan = null;
        $compact = null;
        if (preg_match('/^([a-h][1-8])([a-h][1-8])([qrbn])?$/', $uci, $m)) {
            $lan = $m[1] . '-' . $m[2] . ($m[3] ?? '');
            $compact = $m[1] . $m[2] . ($m[3] ?? '');
        } elseif (preg_match('/^([a-h][1-8])-([a-h][1-8])(?:=)?([qrbn])?$/', $uci, $m)) {
            $lan = $m[1] . '-' . $m[2] . ($m[3] ?? '');
            $compact = $m[1] . $m[2] . ($m[3] ?? '');
        }
        if (!$lan) {
            throw new UnknownNotationException();
        }

        $board = FenToBoardFactory::create($fen);
        $turn = $board->turn;

        if ($turn !== 'w' && $turn !== 'b') {
            throw new UnknownNotationException();
        }

        // Compatibility: some versions accept compact LAN/UCI, others prefer dashed LAN.
        $ok = $board->playLan($turn, $lan);
        if (!$ok && $compact) {
            $ok = $board->playLan($turn, $compact);
        }
        // Production safety net: accept SAN from chess.js as a fallback.
        if (!$ok && $sanInput) {
            $ok = $board->play($turn, $sanInput);
        }
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
