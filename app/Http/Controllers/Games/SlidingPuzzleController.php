<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\SlidingAttempt;
use App\Models\SlidingPuzzle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlidingPuzzleController extends Controller
{
    public function index(Request $request)
    {
        $puzzles = SlidingPuzzle::query()
            ->where('is_active', true)
            ->orderBy('grid_size')
            ->orderBy('title')
            ->get();

        return view('games.sliding-puzzles.index', [
            'puzzles' => $puzzles,
        ]);
    }

    public function show(Request $request, SlidingPuzzle $puzzle)
    {
        if (!(bool) $puzzle->is_active) {
            abort(404);
        }

        return view('games.sliding-puzzles.show', [
            'puzzle' => $puzzle,
            'imageUrl' => $puzzle->imageUrl(),
        ]);
    }

    public function start(Request $request, SlidingPuzzle $puzzle)
    {
        if (!(bool) $puzzle->is_active) {
            return response()->json(['message' => 'Puzzle indisponible.'], 404);
        }

        $userId = (int) $request->user()->id;

        $attempt = SlidingAttempt::query()->create([
            'sliding_puzzle_id' => $puzzle->id,
            'user_id' => $userId,
            'grid_size' => (int) $puzzle->grid_size,
            'moves_count' => 0,
            'duration_ms' => 0,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        return response()->json([
            'ok' => true,
            'attempt_id' => (int) $attempt->id,
            'started_at' => $attempt->started_at?->toIso8601String(),
        ]);
    }

    public function finish(Request $request, SlidingAttempt $attempt)
    {
        $userId = (int) $request->user()->id;
        if ((int) $attempt->user_id !== $userId) {
            return response()->json(['message' => 'Accès interdit.'], 403);
        }

        $validated = $request->validate([
            'moves_count' => ['required', 'integer', 'min:0', 'max:5000'],
            'duration_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
        ]);

        return DB::transaction(function () use ($attempt, $validated) {
            /** @var \App\Models\SlidingAttempt $locked */
            $locked = SlidingAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->finished_at !== null) {
                return response()->json(['message' => 'Tentative déjà terminée.'], 409);
            }

            $locked->moves_count = (int) $validated['moves_count'];
            $locked->duration_ms = (int) $validated['duration_ms'];
            $locked->finished_at = now();
            $locked->save();

            $puzzleId = (int) $locked->sliding_puzzle_id;

            return response()->json([
                'ok' => true,
                'attempt_id' => (int) $locked->id,
                'redirect_url' => route('games.sliding-puzzles.attempt', [
                    'puzzle' => $puzzleId,
                    'attempt' => (int) $locked->id,
                ]),
            ]);
        });
    }

    public function result(Request $request, SlidingPuzzle $puzzle, SlidingAttempt $attempt)
    {
        if ((int) $attempt->sliding_puzzle_id !== (int) $puzzle->id) {
            abort(404);
        }

        $userId = (int) $request->user()->id;
        if ((int) $attempt->user_id !== $userId) {
            abort(403);
        }

        if ($attempt->finished_at === null) {
            return redirect()->route('games.sliding-puzzles.show', $puzzle);
        }

        $myRank = null;
        $betterCount = SlidingAttempt::query()
            ->where('sliding_puzzle_id', $puzzle->id)
            ->where('grid_size', $attempt->grid_size)
            ->whereNotNull('finished_at')
            ->where(function ($q) use ($attempt) {
                $q->where('duration_ms', '<', $attempt->duration_ms)
                    ->orWhere(function ($q2) use ($attempt) {
                        $q2->where('duration_ms', $attempt->duration_ms)
                            ->where('moves_count', '<', $attempt->moves_count);
                    });
            })
            ->count();

        $myRank = $betterCount + 1;

        return view('games.sliding-puzzles.result', [
            'puzzle' => $puzzle,
            'attempt' => $attempt,
            'imageUrl' => $puzzle->imageUrl(),
            'myRank' => $myRank,
        ]);
    }

    public function leaderboard(Request $request, SlidingPuzzle $puzzle)
    {
        if (!(bool) $puzzle->is_active) {
            abort(404);
        }

        $gridSize = (int) $request->query('grid', $puzzle->grid_size);
        if (!in_array($gridSize, [3, 4], true)) {
            $gridSize = (int) $puzzle->grid_size;
        }

        $rows = SlidingAttempt::query()
            ->with('user:id,name')
            ->where('sliding_puzzle_id', $puzzle->id)
            ->where('grid_size', $gridSize)
            ->whereNotNull('finished_at')
            ->orderBy('duration_ms')
            ->orderBy('moves_count')
            ->orderBy('finished_at')
            ->limit(300)
            ->get();

        $bestPerUser = $rows
            ->unique('user_id')
            ->values();

        $bestPerUser = $bestPerUser->take(50)->values();

        $myBest = SlidingAttempt::query()
            ->where('sliding_puzzle_id', $puzzle->id)
            ->where('grid_size', $gridSize)
            ->where('user_id', $request->user()->id)
            ->whereNotNull('finished_at')
            ->orderBy('duration_ms')
            ->orderBy('moves_count')
            ->orderBy('finished_at')
            ->first();

        return view('games.sliding-puzzles.leaderboard', [
            'puzzle' => $puzzle,
            'gridSize' => $gridSize,
            'bestPerUser' => $bestPerUser,
            'myBest' => $myBest,
        ]);
    }
}
