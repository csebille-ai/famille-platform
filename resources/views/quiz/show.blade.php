<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Back button -->
        <div class="mb-6">
            <a href="{{ route('quiz.index') }}" class="fam-link-subtle inline-flex items-center gap-1.5 text-sm font-semibold">
                <i class="ph ph-arrow-left" aria-hidden="true"></i>
                Retour aux quiz
            </a>
        </div>

        <!-- Quiz Header -->
        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm overflow-hidden">
            <div class="px-6 py-8">
                <div class="flex items-start gap-4">
                    <div class="shrink-0 flex items-center justify-center w-16 h-16 rounded-2xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)]">
                        <i class="ph ph-brain text-4xl" aria-hidden="true"></i>
                    </div>
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold text-[color:var(--fam-text)]">{{ $quiz->title }}</h1>
                        <div class="mt-2 flex items-center gap-3 text-sm text-[color:var(--fam-muted)]">
                            <span>{{ $quiz->category }}</span>
                            <span>•</span>
                            <span>{{ $quiz->questions_count }} questions</span>
                            @if($quiz->estimated_duration_minutes)
                                <span>•</span>
                                <span>~{{ $quiz->estimated_duration_minutes }} min</span>
                            @endif
                        </div>

                        @if($quiz->description)
                            <p class="mt-3 text-[color:var(--fam-text)]">{{ $quiz->description }}</p>
                        @endif
                    </div>
                </div>

                <!-- Difficulty & Source -->
                <div class="mt-6 flex items-center gap-4">
                    @php
                        $difficultyColors = [
                            'easy' => ['bg' => 'bg-green-50', 'text' => 'text-green-800', 'border' => 'border-green-200'],
                            'medium' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-800', 'border' => 'border-amber-200'],
                            'hard' => ['bg' => 'bg-red-50', 'text' => 'text-red-800', 'border' => 'border-red-200'],
                        ];
                        $diffColors = $difficultyColors[$quiz->difficulty] ?? $difficultyColors['medium'];
                    @endphp

                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg {{ $diffColors['bg'] }} {{ $diffColors['text'] }} border {{ $diffColors['border'] }} text-sm font-semibold">
                        @if($quiz->difficulty === 'easy')
                            <i class="ph ph-circle" aria-hidden="true"></i>
                            Facile
                        @elseif($quiz->difficulty === 'medium')
                            <i class="ph ph-circles-three" aria-hidden="true"></i>
                            Moyen
                        @else
                            <i class="ph ph-fire" aria-hidden="true"></i>
                            Difficile
                        @endif
                    </span>

                    <span class="inline-flex items-center gap-1.5 text-sm text-[color:var(--fam-muted)]">
                        <i class="ph ph-globe" aria-hidden="true"></i>
                        {{ $quiz->source }}
                    </span>
                </div>

                <!-- Play Button -->
                <div class="mt-8">
                    <a href="{{ route('quiz.play', $quiz) }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-[color:var(--fam-primary)] text-white font-bold hover:bg-[color:var(--fam-primary-dark)] active:scale-95 transition-all duration-200 shadow-lg">
                        <i class="ph ph-play-circle text-2xl" aria-hidden="true"></i>
                        Commencer le quiz
                    </a>
                </div>

                <!-- User Best Score -->
                @if($userBestScore)
                    @php
                        $questionsPerAttempt = min(20, (int) $quiz->questions_count);
                        $pointsPerQuestion = (int) config('quiz.templates.'.$quiz->template_key.'.points_per_question', 10);
                        $maxScore = $questionsPerAttempt * $pointsPerQuestion;
                        $percentage = $maxScore > 0 ? round(($userBestScore->best_score / $maxScore) * 100) : 0;
                    @endphp
                    <div class="mt-6 p-4 rounded-xl bg-[color:var(--fam-surface-alt)] border border-[color:var(--fam-border-soft)]">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-[color:var(--fam-text)]">Ton meilleur score</div>
                                <div class="mt-1 text-2xl font-bold text-[color:var(--fam-primary)]">
                                    {{ $userBestScore->best_score }} / {{ $maxScore }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold text-[color:var(--fam-primary)]">{{ $percentage }}%</div>
                                <div class="text-xs text-[color:var(--fam-muted)]">Réussite</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="mt-6 rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[color:var(--fam-border-soft)] flex items-center justify-between">
                <h2 class="text-lg font-bold text-[color:var(--fam-text)]">
                    <i class="ph ph-trophy text-amber-500" aria-hidden="true"></i>
                    Top 10
                </h2>
                <a href="{{ route('quiz.quiz-leaderboard', $quiz) }}" class="fam-link-subtle text-sm font-semibold">
                    Voir tout
                </a>
            </div>

            @if($leaderboard->isEmpty())
                <div class="px-6 py-8 text-center text-[color:var(--fam-muted)]">
                    Aucun score enregistré pour le moment. Sois le premier !
                </div>
            @else
                <div class="divide-y divide-[color:var(--fam-border-soft)]">
                    @foreach($leaderboard as $index => $entry)
                        <div class="px-6 py-3 flex items-center gap-4">
                            <!-- Rank -->
                            <div class="shrink-0 w-8 text-center">
                                @if($index === 0)
                                    <i class="ph-fill ph-medal text-2xl text-amber-500" aria-hidden="true"></i>
                                @elseif($index === 1)
                                    <i class="ph-fill ph-medal text-2xl text-slate-400" aria-hidden="true"></i>
                                @elseif($index === 2)
                                    <i class="ph-fill ph-medal text-2xl text-amber-700" aria-hidden="true"></i>
                                @else
                                    <span class="text-sm font-bold text-[color:var(--fam-muted)]">{{ $index + 1 }}</span>
                                @endif
                            </div>

                            <!-- User -->
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-[color:var(--fam-text)] truncate">
                                    {{ $entry->user->name }}
                                </div>
                            </div>

                            <!-- Score -->
                            <div class="shrink-0 text-right">
                                <div class="text-lg font-bold text-[color:var(--fam-primary)]">
                                    {{ $entry->best_score }}
                                </div>
                                <div class="text-xs text-[color:var(--fam-muted)]">points</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
