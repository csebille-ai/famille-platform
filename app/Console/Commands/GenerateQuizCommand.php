<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GenerateQuizCommand extends Command
{
    protected $signature = 'quiz:generate 
                            {template : Template key from config/quiz.php}
                            {--title= : Quiz title (optional)}
                            {--count=200 : Number of questions to generate}
                            {--rebuild : Delete and rebuild quiz}';

    protected $description = 'Generate quiz questions from Wikidata using SPARQL templates';

    private array $config;
    private array $template;
    private array $distractorPool = [];

    public function handle(): int
    {
        $templateKey = $this->argument('template');
        $this->config = config('quiz');
        
        // Load template
        if (!isset($this->config['templates'][$templateKey])) {
            $this->error("Template '{$templateKey}' not found in config/quiz.php");
            return 1;
        }
        
        $this->template = $this->config['templates'][$templateKey];
        $title = $this->option('title') ?? $this->template['name'];
        $count = (int) $this->option('count');
        
        $this->info("Generating quiz: {$title}");
        $this->info("Template: {$templateKey}");
        $this->info("Target questions: {$count}");
        
        // Step 1: Fetch distractors pool
        $this->info("\n[1/4] Fetching distractor pool from Wikidata...");
        if (!$this->loadDistractorPool()) {
            $this->error("Failed to load distractor pool");
            return 1;
        }
        $this->info("✓ Loaded " . count($this->distractorPool) . " potential distractors");
        
        // Step 2: Fetch question candidates
        $this->info("\n[2/4] Fetching question candidates from Wikidata...");
        $candidates = $this->fetchQuestionCandidates();
        if (empty($candidates)) {
            $this->error("No candidates fetched");
            return 1;
        }
        $this->info("✓ Fetched " . count($candidates) . " candidates");
        
        // Step 3: Filter and limit
        $this->info("\n[3/4] Filtering and validating candidates...");
        $filtered = $this->filterCandidates($candidates);
        $selected = array_slice($filtered, 0, $count);
        $this->info("✓ Selected " . count($selected) . " valid questions");
        
        if (empty($selected)) {
            $this->error("No valid questions after filtering");
            return 1;
        }
        
        // Step 4: Generate quiz in database
        $this->info("\n[4/4] Saving to database...");
        $quizId = $this->saveQuizToDatabase($templateKey, $title, $selected);
        
        if (!$quizId) {
            $this->error("Failed to save quiz");
            return 1;
        }
        
        $this->info("\n✓ Quiz generated successfully!");
        $this->info("Quiz ID: {$quizId}");
        $this->info("Questions: " . count($selected));
        
        return 0;
    }

    private function loadDistractorPool(): bool
    {
        $query = $this->template['distractor_query'];
        $results = $this->executeSparqlQuery($query);
        
        if (!$results || !isset($results['results']['bindings'])) {
            return false;
        }
        
        foreach ($results['results']['bindings'] as $row) {
            $qid = $this->extractQid($row['qid']['value'] ?? '');
            $label = $row['label']['value'] ?? '';
            
            if ($qid && $label && $this->isValidLabel($label)) {
                $this->distractorPool[$qid] = $label;
            }
        }
        
        return !empty($this->distractorPool);
    }

    private function fetchQuestionCandidates(): array
    {
        $query = $this->template['sparql_query'];
        $results = $this->executeSparqlQuery($query);
        
        if (!$results || !isset($results['results']['bindings'])) {
            return [];
        }
        
        $candidates = [];
        
        foreach ($results['results']['bindings'] as $row) {
            $subjectQid = $this->extractQid($row['subjectQid']['value'] ?? '');
            $subjectLabel = $row['subjectLabel']['value'] ?? ($row['subjectQidLabel']['value'] ?? '');
            $answerQid = $this->extractQid($row['answerQid']['value'] ?? '');
            $answerLabel = $row['answerLabel']['value'] ?? ($row['answerQidLabel']['value'] ?? '');
            $imageUrl = $row['imageUrl']['value'] ?? null;
            
            if ($subjectQid && $subjectLabel && $answerQid && $answerLabel) {
                $candidates[] = [
                    'subject_qid' => $subjectQid,
                    'subject_label' => $subjectLabel,
                    'answer_qid' => $answerQid,
                    'answer_label' => $answerLabel,
                    'image_url' => $imageUrl,
                ];
            }
        }
        
        return $candidates;
    }

    private function filterCandidates(array $candidates): array
    {
        $filters = $this->template['filters'] ?? [];
        $minLength = $filters['min_label_length'] ?? 2;
        $requireImage = (bool) ($filters['require_image'] ?? false);
        $seen = [];
        $filtered = [];
        
        foreach ($candidates as $candidate) {
            if ($requireImage && empty($candidate['image_url'])) {
                continue;
            }

            // Check label length
            if (!$this->isValidLabel($candidate['subject_label'], $minLength)) {
                continue;
            }
            if (!$this->isValidLabel($candidate['answer_label'], $minLength)) {
                continue;
            }
            
            // Check duplicates (same subject)
            $key = $candidate['subject_qid'];
            if (isset($seen[$key])) {
                continue;
            }
            
            $seen[$key] = true;
            $filtered[] = $candidate;
        }
        
        return $filtered;
    }

    private function executeSparqlQuery(string $query): ?array
    {
        $endpoint = $this->config['wikidata']['endpoint'];
        $userAgent = $this->config['wikidata']['user_agent'];
        $timeout = (int) ($this->template['wikidata_timeout'] ?? $this->config['wikidata']['timeout']);
        $maxAttempts = $this->config['wikidata']['retry_attempts'];
        $retryDelay = $this->config['wikidata']['retry_delay'];
        $backoffMultiplier = $this->config['wikidata']['backoff_multiplier'];
        
        $attempt = 0;
        $delay = $retryDelay;
        
        while ($attempt < $maxAttempts) {
            $attempt++;
            
            try {
                $response = Http::withOptions([
                    'version' => 1.1,
                ])->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'application/sparql-results+json',
                ])
                ->timeout($timeout)
                ->asForm()
                ->post($endpoint, [
                    'query' => $query,
                    'format' => 'json',
                ]);
                
                if ($response->successful()) {
                    return $response->json();
                }

                // Retry on transient server errors
                if ($response->status() >= 500 && $attempt < $maxAttempts) {
                    $this->warn("HTTP {$response->status()} from Wikidata. Waiting {$delay}s before retry {$attempt}/{$maxAttempts}...");
                    sleep($delay);
                    $delay *= $backoffMultiplier;
                    continue;
                }
                
                // Handle 429 Too Many Requests
                if ($response->status() === 429) {
                    $this->warn("Rate limited (429). Waiting {$delay}s before retry {$attempt}/{$maxAttempts}...");
                    sleep($delay);
                    $delay *= $backoffMultiplier;
                    continue;
                }
                
                $this->error("HTTP {$response->status()}: {$response->body()}");
                return null;
                
            } catch (\Exception $e) {
                $this->error("Exception on attempt {$attempt}: " . $e->getMessage());
                
                if ($attempt < $maxAttempts) {
                    $this->warn("Retrying in {$delay}s...");
                    sleep($delay);
                    $delay *= $backoffMultiplier;
                    continue;
                }
                
                return null;
            }
        }
        
        $this->error("Failed after {$maxAttempts} attempts");
        return null;
    }

    private function saveQuizToDatabase(string $templateKey, string $title, array $questions): ?int
    {
        return DB::transaction(function () use ($templateKey, $title, $questions) {
            // Create or update quiz
            $quiz = DB::table('quizzes')->updateOrInsert(
                ['template_key' => $templateKey],
                [
                    'title' => $title,
                    'category' => $this->template['category'] ?? 'Général',
                    'difficulty' => $this->template['difficulty'] ?? 'medium',
                    'is_active' => true,
                    'questions_count' => count($questions),
                    'source' => 'Wikidata',
                    'updated_at' => now(),
                ]
            );
            
            $quizId = DB::table('quizzes')->where('template_key', $templateKey)->value('id');
            
            if (!$quizId) {
                return null;
            }
            
            // Delete old questions if rebuild
            if ($this->option('rebuild')) {
                DB::table('quiz_choices')->whereIn('question_id', function ($query) use ($quizId) {
                    $query->select('id')->from('quiz_questions')->where('quiz_id', $quizId);
                })->delete();
                DB::table('quiz_questions')->where('quiz_id', $quizId)->delete();
            }
            
            // Insert questions and choices
            $points = $this->template['points_per_question'] ?? 10;
            $order = 0;
            
            foreach ($questions as $candidate) {
                $order++;
                
                // Build question text
                $questionText = str_replace(
                    '{subject}',
                    $candidate['subject_label'],
                    $this->template['question_template']
                );
                
                // Insert question
                $questionId = DB::table('quiz_questions')->insertGetId([
                    'quiz_id' => $quizId,
                    'question_text' => $questionText,
                    'image_url' => $candidate['image_url'] ?? null,
                    'points' => $points,
                    'order' => $order,
                    'subject_qid' => $candidate['subject_qid'],
                    'correct_answer_qid' => $candidate['answer_qid'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Generate distractors
                $distractors = $this->generateDistractors(
                    $candidate['answer_qid'],
                    $this->template['distractor_count'] ?? 3
                );
                
                // Insert choices (correct + distractors)
                $choices = [
                    [
                        'question_id' => $questionId,
                        'choice_text' => $candidate['answer_label'],
                        'qid' => $candidate['answer_qid'],
                        'is_correct' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];
                
                foreach ($distractors as $distractor) {
                    $choices[] = [
                        'question_id' => $questionId,
                        'choice_text' => $distractor['label'],
                        'qid' => $distractor['qid'],
                        'is_correct' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                
                DB::table('quiz_choices')->insert($choices);
            }
            
            return $quizId;
        });
    }

    private function generateDistractors(string $correctQid, int $count): array
    {
        // Filter out correct answer
        $pool = array_filter(
            $this->distractorPool,
            fn($qid) => $qid !== $correctQid,
            ARRAY_FILTER_USE_KEY
        );
        
        // Shuffle and take N
        $keys = array_keys($pool);
        shuffle($keys);
        $selected = array_slice($keys, 0, $count);
        
        $distractors = [];
        foreach ($selected as $qid) {
            $distractors[] = [
                'qid' => $qid,
                'label' => $pool[$qid],
            ];
        }
        
        return $distractors;
    }

    private function extractQid(string $uri): ?string
    {
        if (preg_match('/Q\d+$/', $uri, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function isValidLabel(string $label, int $minLength = 2): bool
    {
        $label = trim($label);

        if (strlen($label) < $minLength || str_contains($label, '�')) {
            return false;
        }

        // Wikidata sometimes returns the entity id as a fallback label (e.g. "Q12345").
        // Also handle cases where it comes with punctuation/quotes.
        $labelStripped = trim($label, " \t\n\r\0\x0B\"'“”‘’«»()[]{}<>.,;:!?–—");
        if (preg_match('/^[QP]\\d+$/', $labelStripped) === 1) {
            return false;
        }

        return true;
    }
}
