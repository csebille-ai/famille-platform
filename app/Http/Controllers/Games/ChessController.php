<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\ChessGame;
use App\Models\ChessMove;
use App\Models\ChessTeamMember;
use App\Services\Games\ChessGameService;
use App\Services\Games\ChessRules;
use App\Services\WebPush\WebPushNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChessController extends Controller
{
    public function index(Request $request, ChessGameService $games)
    {
        $userId = (int) $request->user()->id;
        $game = $games->getOrCreateActiveGame($userId);

        $members = ChessTeamMember::query()
            ->with('user:id,name')
            ->where('chess_game_id', $game->id)
            ->orderBy('team')
            ->orderBy('id')
            ->get();

        $myTeam = ChessTeamMember::query()
            ->where('chess_game_id', $game->id)
            ->where('user_id', $userId)
            ->value('team');

        $lastMove = ChessMove::query()
            ->with('player:id,name')
            ->where('chess_game_id', $game->id)
            ->latest('id')
            ->first();

        return view('games.chess.index', [
            'game' => $game,
            'members' => $members,
            'myTeam' => $myTeam,
            'lastMove' => $lastMove,
        ]);
    }

    public function show(Request $request, ChessGame $game)
    {
        $userId = (int) $request->user()->id;
        $myTeam = ChessTeamMember::query()
            ->where('chess_game_id', $game->id)
            ->where('user_id', $userId)
            ->value('team');

        return view('games.chess.show', [
            'game' => $game,
            'myTeam' => $myTeam,
        ]);
    }

    public function state(Request $request, ChessGame $game)
    {
        $userId = (int) $request->user()->id;
        $myTeam = ChessTeamMember::query()
            ->where('chess_game_id', $game->id)
            ->where('user_id', $userId)
            ->value('team');

        $canMove = ($game->status === 'active')
            && in_array($myTeam, ['w', 'b'], true)
            && $myTeam === $game->turn;

        $members = ChessTeamMember::query()
            ->with('user:id,name')
            ->where('chess_game_id', $game->id)
            ->get();

        $moves = ChessMove::query()
            ->with('player:id,name')
            ->where('chess_game_id', $game->id)
            ->orderByDesc('move_number')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'game' => [
                'id' => $game->id,
                'status' => $game->status,
                'fen' => $game->current_fen,
                'turn' => $game->turn,
                'pgn' => $game->pgn,
                'last_move_at' => optional($game->last_move_at)->toIso8601String(),
            ],
            'my_team' => $myTeam,
            'can_move' => $canMove,
            'members' => $members->map(fn ($m) => [
                'user_id' => (int) $m->user_id,
                'name' => (string) ($m->user?->name ?? ''),
                'team' => (string) $m->team,
            ])->values(),
            'moves' => $moves->map(fn ($m) => [
                'move_number' => (int) $m->move_number,
                'uci' => (string) $m->uci,
                'san' => (string) $m->san,
                'team' => (string) $m->played_by_team,
                'played_by' => (string) ($m->player?->name ?? ''),
                'at' => optional($m->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function join(Request $request, ChessGame $game)
    {
        $team = (string) $request->input('team', 'spectator');
        if (!in_array($team, ['w', 'b', 'spectator'], true)) {
            return response()->json(['message' => 'Équipe invalide.'], 422);
        }

        $userId = (int) $request->user()->id;

        $member = ChessTeamMember::query()->updateOrCreate(
            ['chess_game_id' => $game->id, 'user_id' => $userId],
            ['team' => $team, 'joined_at' => now()]
        );

        return response()->json([
            'ok' => true,
            'team' => $member->team,
        ]);
    }

    public function move(Request $request, ChessGame $game, ChessRules $rules, ChessGameService $games, WebPushNotifier $push)
    {
        $userId = (int) $request->user()->id;
        $uci = (string) $request->input('uci', '');
        $san = (string) $request->input('san', '');
        $expectedFen = (string) $request->input('expected_fen', '');

        if ($uci === '' || $expectedFen === '') {
            return response()->json(['message' => 'Données manquantes.'], 422);
        }

        $memberTeam = ChessTeamMember::query()
            ->where('chess_game_id', $game->id)
            ->where('user_id', $userId)
            ->value('team');

        if (!in_array($memberTeam, ['w', 'b'], true)) {
            return response()->json(['message' => 'Tu dois rejoindre une équipe pour jouer.'], 403);
        }

        return DB::transaction(function () use ($game, $rules, $games, $push, $userId, $uci, $san, $expectedFen, $memberTeam) {
            /** @var \App\Models\ChessGame $locked */
            $locked = ChessGame::query()->whereKey($game->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active') {
                return response()->json(['message' => 'Partie terminée.'], 409);
            }

            if ($memberTeam !== $locked->turn) {
                return response()->json(['message' => 'Ce n\'est pas le tour de ton équipe.'], 403);
            }

            if (trim($expectedFen) !== trim((string) $locked->current_fen)) {
                return response()->json([
                    'message' => 'La partie a avancé. Rafraîchis la position.',
                    'code' => 'fen_mismatch',
                    'current_fen' => $locked->current_fen,
                ], 409);
            }

            try {
                $result = $rules->applyUci((string) $locked->current_fen, $uci, $san !== '' ? $san : null);
            } catch (\Throwable $e) {
                $fenStr = (string) $locked->current_fen;
                $uciStr = strtolower(trim($uci));
                $lanStr = null;
                $compactStr = null;
                if (preg_match('/^([a-h][1-8])([a-h][1-8])([qrbn])?$/', $uciStr, $m)) {
                    $lanStr = $m[1] . '-' . $m[2] . ($m[3] ?? '');
                    $compactStr = $m[1] . $m[2] . ($m[3] ?? '');
                } elseif (preg_match('/^([a-h][1-8])-([a-h][1-8])(?:=)?([qrbn])?$/', $uciStr, $m)) {
                    $lanStr = $m[1] . '-' . $m[2] . ($m[3] ?? '');
                    $compactStr = $m[1] . $m[2] . ($m[3] ?? '');
                }

                $isMissingChessLib = $e instanceof \Error
                    && str_contains((string) $e->getMessage(), 'Chess\\Variant\\Classical\\FenToBoardFactory');

                if ($isMissingChessLib) {
                    return response()->json([
                        'message' => 'Moteur d’échecs indisponible sur le serveur (dépendance manquante).',
                        'code' => 'chess_engine_missing_dependency',
                        'parser' => defined('App\\Services\\Games\\ChessRules::MOVE_PARSER_VERSION')
                            ? \App\Services\Games\ChessRules::MOVE_PARSER_VERSION
                            : 'unknown',
                        'selftest' => $rules->selfTest(),
                    ], 500);
                }

                return response()->json([
                    'message' => 'Coup illégal.',
                    'code' => 'chess_move_illegal',
                    'parser' => defined('App\\Services\\Games\\ChessRules::MOVE_PARSER_VERSION')
                        ? \App\Services\Games\ChessRules::MOVE_PARSER_VERSION
                        : 'unknown',
                    'received_uci' => $uciStr,
                    'received_san' => $san,
                    'computed_lan' => $lanStr,
                    'computed_compact' => $compactStr,
                    'fen' => $fenStr,
                    'turn' => (string) $locked->turn,
                    'exception' => get_class($e),
                    'exception_msg' => substr((string) $e->getMessage(), 0, 160),
                    'selftest' => $rules->selfTest(),
                ], 422);
            }

            $nextTeam = (string) $result['new_turn'];

            $moveNumber = (int) (ChessMove::query()->where('chess_game_id', $locked->id)->lockForUpdate()->max('move_number') ?? 0) + 1;

            ChessMove::query()->create([
                'chess_game_id' => $locked->id,
                'move_number' => $moveNumber,
                'uci' => strtolower(trim($uci)),
                'san' => (string) $result['san'],
                'fen_before' => (string) $locked->current_fen,
                'fen_after' => (string) $result['new_fen'],
                'played_by_user_id' => $userId,
                'played_by_team' => $memberTeam,
            ]);

            $locked->current_fen = (string) $result['new_fen'];
            $locked->turn = $nextTeam;
            $locked->last_move_at = now();
            $locked->pgn = $games->formatPgnAppend((string) ($locked->pgn ?? ''), $moveNumber, $memberTeam, (string) $result['san']);

            if (!empty($result['is_finished'])) {
                $locked->status = 'finished';
            }

            $locked->save();

            // Notify only the next team.
            $notifyIds = ChessTeamMember::query()
                ->where('chess_game_id', $locked->id)
                ->where('team', $nextTeam)
                ->pluck('user_id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn (int $v) => $v > 0)
                ->unique()
                ->values()
                ->all();

            if (!empty($notifyIds)) {
                $payload = [
                    'title' => 'Échecs',
                    'body' => 'À votre tour de jouer',
                    'url' => route('games.chess.show', $locked),
                ];
                $push->notifyUsers($notifyIds, $payload, [
                    'TTL' => 3600,
                ]);
            }

            return response()->json([
                'ok' => true,
                'new_fen' => (string) $locked->current_fen,
                'san' => (string) $result['san'],
                'new_turn' => (string) $locked->turn,
                'pgn' => (string) ($locked->pgn ?? ''),
                'status' => (string) $locked->status,
                'last_move_at' => optional($locked->last_move_at)->toIso8601String(),
            ]);
        });
    }
}
