<?php

namespace App\Console\Commands;

use App\Models\QuizAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetQuizStats extends Command
{
    protected $signature = 'quiz:reset-stats {quizId : ID du quiz} {--force : Ne pas demander confirmation}';

    protected $description = 'Supprime les tentatives/réponses et scores d\'un quiz, puis recalcule les totaux globaux.';

    public function handle(): int
    {
        $quizId = (int) $this->argument('quizId');

        if (!$this->option('force')) {
            $this->warn('ATTENTION: cette commande supprime des données (attempts/answers/best scores) pour ce quiz.');
            if (!$this->confirm("Confirmer la suppression des stats pour le quiz_id={$quizId} ?")) {
                $this->info('Annulé.');
                return self::SUCCESS;
            }
        }

        DB::transaction(function () use ($quizId) {
            $attemptIds = QuizAttempt::where('quiz_id', $quizId)->pluck('id');

            // Delete answers first (FK)
            if ($attemptIds->isNotEmpty()) {
                DB::table('quiz_attempt_answers')->whereIn('attempt_id', $attemptIds)->delete();
            }

            // Delete attempts
            DB::table('quiz_attempts')->where('quiz_id', $quizId)->delete();

            // Delete best scores for this quiz
            DB::table('quiz_best_scores')->where('quiz_id', $quizId)->delete();

            // Rebuild global totals from remaining best scores
            DB::table('quiz_user_totals')->truncate();

            $rows = DB::table('quiz_best_scores')
                ->select('user_id', DB::raw('SUM(best_score) as total_score'), DB::raw('COUNT(*) as quizzes_completed'))
                ->where('best_score', '>', 0)
                ->groupBy('user_id')
                ->get();

            $now = now();
            foreach ($rows as $row) {
                DB::table('quiz_user_totals')->insert([
                    'user_id' => $row->user_id,
                    'total_score' => (int) $row->total_score,
                    'quizzes_completed' => (int) $row->quizzes_completed,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $this->info("OK: stats reset pour quiz_id={$quizId}.");
        $this->info("Totaux globaux recalculés depuis quiz_best_scores.");

        return self::SUCCESS;
    }
}
