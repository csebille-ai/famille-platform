<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizBestScore;
use App\Models\QuizUserTotal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    /**
     * Display quiz library (list of all quizzes)
     */
    public function index(Request $request)
    {
        $query = Quiz::where('is_active', true);

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by difficulty
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // Search
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Sort
        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'popular' => $query->withCount('attempts')->orderBy('attempts_count', 'desc'),
            'title' => $query->orderBy('title'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $quizzes = $query->paginate(12);

        // Get user's progress for each quiz
        $user = auth()->user();
        if ($user) {
            $userBestScores = QuizBestScore::where('user_id', $user->id)
                ->pluck('best_score', 'quiz_id');
        } else {
            $userBestScores = collect();
        }

        return view('quiz.index', [
            'quizzes' => $quizzes,
            'userBestScores' => $userBestScores,
            'categories' => config('quiz.categories', []),
            'difficulties' => config('quiz.difficulties', []),
        ]);
    }

    /**
     * Display quiz detail page
     */
    public function show(Quiz $quiz)
    {
        $quiz->load('questions');

        $user = auth()->user();
        
        // User's best score for this quiz
        $userBestScore = null;
        if ($user) {
            $userBestScore = QuizBestScore::where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->first();
        }

        // Top 10 leaderboard for this quiz
        $leaderboard = QuizBestScore::where('quiz_id', $quiz->id)
            ->with('user')
            ->orderBy('best_score', 'desc')
            ->limit(10)
            ->get();

        return view('quiz.show', [
            'quiz' => $quiz,
            'userBestScore' => $userBestScore,
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * Start a new attempt and display the play page
     */
    public function play(Quiz $quiz)
    {
        $user = auth()->user();

        $questionsPerAttempt = min(20, (int) $quiz->questions_count);

        // Avoid repeating questions the user saw recently on this quiz.
        // (Based on finished attempts: we know the exact question_ids via stored answers.)
        $avoidLastAttempts = (int) config('quiz.avoid_repeat_last_attempts', 5);
        $recentQuestionIds = collect();
        if ($avoidLastAttempts > 0) {
            $recentAttemptIds = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->where('status', 'finished')
                ->orderByDesc('finished_at')
                ->limit($avoidLastAttempts)
                ->pluck('id');

            if ($recentAttemptIds->isNotEmpty()) {
                $recentQuestionIds = QuizAttemptAnswer::query()
                    ->whereIn('attempt_id', $recentAttemptIds)
                    ->pluck('question_id')
                    ->unique()
                    ->values();
            }
        }

        // Create new attempt
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Load questions with choices (randomize order, limit to N)
        $questionsQuery = $quiz->questions()
            ->reorder()
            ->with('choices')
            ->inRandomOrder();

        if ($recentQuestionIds->isNotEmpty()) {
            $questionsQuery->whereNotIn('id', $recentQuestionIds);
        }

        $questions = $questionsQuery
            ->limit($questionsPerAttempt)
            ->get();

        // Fallback: if we excluded too much, fill remaining slots from the full pool.
        if ($questions->count() < $questionsPerAttempt) {
            $missing = $questionsPerAttempt - $questions->count();
            $alreadySelectedIds = $questions->pluck('id');

            $fill = $quiz->questions()
                ->reorder()
                ->with('choices')
                ->whereNotIn('id', $alreadySelectedIds)
                ->inRandomOrder()
                ->limit($missing)
                ->get();

            $questions = $questions->concat($fill)->values();
        }

        // Randomize choices for each question
        $questions->each(function ($question) {
            $question->setRelation('choices', $question->choices->shuffle());
        });

        return view('quiz.play', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            'questions' => $questions,
        ]);
    }

    /**
     * Submit attempt and calculate score (server-side validation)
     */
    public function submit(Request $request, Quiz $quiz, QuizAttempt $attempt)
    {
        $user = auth()->user();
        
        // Debug logging
        \Log::info('Quiz submit attempt', [
            'attempt_id' => $attempt->id,
            'attempt_user_id' => $attempt->user_id,
            'attempt_status' => $attempt->status,
            'auth_user_id' => $user->id,
        ]);

        // If already finished (double-submit), redirect to result
        if ($attempt->status == 'finished') {
            return redirect()->route('quiz.result', ['quiz' => $quiz, 'attempt' => $attempt]);
        }

        // Log received data
        \Log::info('Quiz submit - received data', [
            'answers_count' => count($request->input('answers', [])),
            'first_answer' => $request->input('answers.0'),
        ]);

        try {
            $validated = $request->validate([
                'answers' => 'required|array',
                'answers.*' => 'required|exists:quiz_choices,id',
            ]);
            \Log::info('Quiz validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Quiz validation failed', [
                'errors' => $e->errors(),
                'attempt_id' => $attempt->id,
            ]);
            throw $e;
        }

        \Log::info('Starting DB transaction');
        
        DB::transaction(function () use ($attempt, $validated) {
            \Log::info('Inside transaction');
            $totalScore = 0;
            $correctCount = 0;

            \Log::info('Processing answers', ['count' => count($validated['answers'])]);

            foreach ($validated['answers'] as $questionId => $choiceId) {
                \Log::info('Processing answer', ['question_id' => $questionId, 'choice_id' => $choiceId]);

                // Verify choice belongs to question
                $choice = \App\Models\QuizChoice::where('id', $choiceId)
                    ->where('question_id', $questionId)
                    ->first();

                if (!$choice) {
                    \Log::warning('Choice not found or mismatch', ['choice_id' => $choiceId, 'question_id' => $questionId]);
                    continue;
                }

                $isCorrect = $choice->is_correct;

                // Save answer
                QuizAttemptAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $questionId,
                    'choice_id' => $choiceId,
                    'is_correct' => $isCorrect,
                    'response_time_ms' => null,
                ]);

                // Calculate score
                if ($isCorrect) {
                    $question = \App\Models\QuizQuestion::find($questionId);
                    $totalScore += $question->points;
                    $correctCount++;
                }
            }

            \Log::info('Finished processing answers', ['total_score' => $totalScore, 'correct_count' => $correctCount]);

            // Update attempt
            \Log::info('Updating attempt');
            $attempt->update([
                'status' => 'finished',
                'finished_at' => now(),
                'duration_seconds' => $attempt->started_at->diffInSeconds(now()),
                'score' => $totalScore,
            ]);
            \Log::info('Attempt updated');

            // Update best scores (Option A)
            \Log::info('Updating best scores');
            $this->updateBestScores($attempt, $totalScore);
            \Log::info('Best scores updated');
        });

        \Log::info('Transaction committed, redirecting to result');
        
        // Return 204 with redirect header to minimize WAF interference
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()
                ->noContent()
                ->header('X-Redirect-Url', route('quiz.result', ['quiz' => $quiz, 'attempt' => $attempt]))
                ->header('Cache-Control', 'no-store');
        }
        
        // Fallback: directly show result
        return $this->result($quiz, $attempt);
    }

    /**
     * Display attempt result
     */
    public function result(Quiz $quiz, QuizAttempt $attempt)
    {
        // Verify attempt belongs to user
        if ((int) $attempt->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $attempt->load(['answers.question.choices', 'answers.choice']);

        return view('quiz.result', [
            'quiz' => $quiz,
            'attempt' => $attempt,
        ]);
    }

    /**
     * Display global leaderboard
     */
    public function leaderboard()
    {
        $limit = config('quiz.leaderboard.top_limit', 50);
        
        $leaderboard = QuizUserTotal::with('user')
            ->orderBy('total_score', 'desc')
            ->limit($limit)
            ->get();

        $user = auth()->user();
        $userRank = null;
        $userTotal = null;

        if ($user) {
            $userTotal = QuizUserTotal::where('user_id', $user->id)->first();
            if ($userTotal) {
                $userRank = QuizUserTotal::where('total_score', '>', $userTotal->total_score)->count() + 1;
            }
        }

        return view('quiz.leaderboard', [
            'leaderboard' => $leaderboard,
            'userRank' => $userRank,
            'userTotal' => $userTotal,
        ]);
    }

    /**
     * Display leaderboard for specific quiz
     */
    public function quizLeaderboard(Quiz $quiz)
    {
        $limit = config('quiz.leaderboard.top_limit', 50);
        
        $leaderboard = QuizBestScore::where('quiz_id', $quiz->id)
            ->with('user')
            ->orderBy('best_score', 'desc')
            ->limit($limit)
            ->get();

        $user = auth()->user();
        $userBest = null;
        $userRank = null;

        if ($user) {
            $userBest = QuizBestScore::where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->first();
                
            if ($userBest) {
                $userRank = QuizBestScore::where('quiz_id', $quiz->id)
                    ->where('best_score', '>', $userBest->best_score)
                    ->count() + 1;
            }
        }

        return view('quiz.quiz-leaderboard', [
            'quiz' => $quiz,
            'leaderboard' => $leaderboard,
            'userBest' => $userBest,
            'userRank' => $userRank,
        ]);
    }

    /**
     * Update best scores using Option A logic
     */
    private function updateBestScores(QuizAttempt $attempt, int $score): void
    {
        $userId = $attempt->user_id;
        $quizId = $attempt->quiz_id;

        // Get or create best score record for this quiz
        $bestScore = QuizBestScore::firstOrNew([
            'quiz_id' => $quizId,
            'user_id' => $userId,
        ]);

        $oldBest = $bestScore->best_score ?? 0;
        $isNewBest = $score > $oldBest;

        if ($isNewBest) {
            $delta = $score - $oldBest;

            // Update best score for this quiz
            $bestScore->best_score = $score;
            $bestScore->best_attempt_id = $attempt->id;
            $bestScore->save();

            // Update global total
            $userTotal = QuizUserTotal::firstOrNew(['user_id' => $userId]);
            $userTotal->total_score = ($userTotal->total_score ?? 0) + $delta;
            
            if ($oldBest === 0) {
                // First time completing this quiz
                $userTotal->quizzes_completed = ($userTotal->quizzes_completed ?? 0) + 1;
            }
            
            $userTotal->save();
        }
    }
}
