<?php

namespace App\Http\Middleware;

use App\Models\ActivityEvent;
use App\Models\ChessGame;
use App\Models\ChessTeamMember;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            $user = $request->user();
            if (!$user) {
                return $response;
            }

            // Skip noisy routes.
            $path = '/' . ltrim((string) $request->path(), '/');
            if (str_starts_with($path, '/build/') || str_starts_with($path, '/storage/')) {
                return $response;
            }
            if (in_array($path, ['/up'], true)) {
                return $response;
            }

            $now = CarbonImmutable::now();

            // --- Games (Chess): compute lightweight session flags for UI badges ---
            try {
                $checkedAt = session()->get('games.chess.checked_at');
                $checkedAt = $checkedAt ? CarbonImmutable::parse($checkedAt) : null;

                // Throttle DB checks.
                if ($checkedAt === null || $checkedAt->diffInSeconds($now) >= 60) {
                    $active = ChessGame::query()->active()->orderByDesc('id')->first();
                    if (!$active) {
                        session()->put([
                            'games.chess.checked_at' => $now->toIso8601String(),
                            'games.chess.active' => false,
                            'games.chess.game_id' => null,
                            'games.chess.your_turn' => false,
                        ]);
                    } else {
                        $team = ChessTeamMember::query()
                            ->where('chess_game_id', $active->id)
                            ->where('user_id', (int) $user->id)
                            ->value('team');

                        $yourTurn = ($active->status === 'active')
                            && in_array($team, ['w', 'b'], true)
                            && $team === $active->turn;

                        session()->put([
                            'games.chess.checked_at' => $now->toIso8601String(),
                            'games.chess.active' => true,
                            'games.chess.game_id' => (int) $active->id,
                            'games.chess.your_turn' => $yourTurn,
                        ]);
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            // Throttle last_seen writes.
            $prev = $user->last_seen_at ? CarbonImmutable::parse($user->last_seen_at) : null;
            if ($prev === null || $prev->diffInSeconds($now) >= 90) {
                $user->forceFill(['last_seen_at' => $now])->save();
            }

            // Log page views (GET only) with optional sampling.
            if ($request->isMethod('GET')) {
                $sample = (float) (config('ops.activity.page_view_sample', env('ACTIVITY_PAGE_VIEW_SAMPLE', 1)));
                if ($sample >= 1 || (mt_rand() / mt_getrandmax()) <= max(0.0, min(1.0, $sample))) {
                    ActivityEvent::create([
                        'created_at' => $now,
                        'user_id' => $user->id,
                        'type' => 'page_view',
                        'route' => $path,
                        'metadata' => [
                            'name' => $request->route()?->getName(),
                        ],
                    ]);
                }
            }
        } catch (\Throwable) {
            // Never break request flow.
        }

        return $response;
    }
}
