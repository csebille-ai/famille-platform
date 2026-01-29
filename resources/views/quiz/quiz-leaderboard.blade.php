<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header -->
        <div class="mb-6">
            <a href="{{ route('quiz.show', $quiz) }}" class="fam-link-subtle inline-flex items-center gap-1.5 text-sm font-semibold mb-4">
                <i class="ph ph-arrow-left" aria-hidden="true"></i>
                Retour au quiz
            </a>

            <div class="flex items-center gap-4">
                <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)]">
                    <i class="ph ph-brain text-2xl" aria-hidden="true"></i>
                </div>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-[color:var(--fam-text)]">
                        Classement • {{ $quiz->title }}
                    </h1>
                    <p class="mt-1 text-sm text-[color:var(--fam-muted)]">
                        Meilleurs scores sur ce quiz
                    </p>
                </div>
            </div>
        </div>

        <!-- User Position (if not in top 50) -->
        @if($userBest && $userRank && $userRank > 50)
            @php
                $questionsPerAttempt = min(20, (int) $quiz->questions_count);
                $pointsPerQuestion = (int) config('quiz.templates.' . $quiz->template_key . '.points_per_question', 10);
                $maxScore = $questionsPerAttempt * $pointsPerQuestion;
                $percentage = $maxScore > 0 ? round(($userBest->best_score / $maxScore) * 100) : 0;
            @endphp
            <div class="mb-6 rounded-2xl bg-gradient-to-r from-[color:var(--fam-primary-100)] to-[color:var(--fam-primary-50)] border border-[color:var(--fam-primary)]/30 shadow-sm overflow-hidden">
                <div class="px-6 py-4">
                    <div class="flex items-center gap-4">
                        <div class="shrink-0 text-center">
                            <div class="text-2xl font-bold text-[color:var(--fam-primary)]">#{{ $userRank }}</div>
                            <div class="text-xs text-[color:var(--fam-muted)]">Ta position</div>
                        </div>
                        <div class="flex-1">
                            <div class="font-bold text-[color:var(--fam-text)]">{{ auth()->user()->name }}</div>
                            <div class="text-sm text-[color:var(--fam-muted)]">{{ $percentage }}% de réussite</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-2xl font-bold text-[color:var(--fam-primary)]">{{ $userBest->best_score }}</div>
                            <div class="text-xs text-[color:var(--fam-muted)]">/ {{ $maxScore }} points</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Leaderboard -->
        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm overflow-hidden">
            @if($leaderboard->isEmpty())
                <div class="px-6 py-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[color:var(--fam-surface-alt)] text-[color:var(--fam-muted)] mb-4">
                        <i class="ph ph-trophy text-3xl" aria-hidden="true"></i>
                    </div>
                    <p class="text-[color:var(--fam-muted)]">Aucun score enregistré pour ce quiz.</p>
                    <p class="mt-2 text-sm text-[color:var(--fam-muted)]">Sois le premier à jouer !</p>
                    <div class="mt-6">
                        <a href="{{ route('quiz.play', $quiz) }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-[color:var(--fam-primary)] text-white font-bold hover:bg-[color:var(--fam-primary-dark)] transition-colors">
                            <i class="ph ph-play-circle text-xl" aria-hidden="true"></i>
                            Commencer le quiz
                        </a>
                    </div>
                </div>
            @else
                <div class="divide-y divide-[color:var(--fam-border-soft)]">
                    @foreach($leaderboard as $index => $entry)
                        @php
                            $isCurrentUser = auth()->check() && $entry->user_id === auth()->id();
                            $questionsPerAttempt = min(20, (int) $quiz->questions_count);
                            $pointsPerQuestion = (int) config('quiz.templates.' . $quiz->template_key . '.points_per_question', 10);
                            $maxScore = $questionsPerAttempt * $pointsPerQuestion;
                            $percentage = $maxScore > 0 ? round(($entry->best_score / $maxScore) * 100) : 0;
                        @endphp

                        <div class="px-6 py-4 flex items-center gap-4 {{ $isCurrentUser ? 'bg-[color:var(--fam-primary-50)]' : '' }}">
                            <!-- Rank -->
                            <div class="shrink-0 w-12 text-center">
                                @if($index === 0)
                                    <i class="ph-fill ph-medal text-4xl text-amber-500" aria-hidden="true"></i>
                                @elseif($index === 1)
                                    <i class="ph-fill ph-medal text-4xl text-slate-400" aria-hidden="true"></i>
                                @elseif($index === 2)
                                    <i class="ph-fill ph-medal text-4xl text-amber-700" aria-hidden="true"></i>
                                @else
                                    <span class="text-lg font-bold text-[color:var(--fam-muted)]">{{ $index + 1 }}</span>
                                @endif
                            </div>

                            <!-- User Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <div class="font-bold text-[color:var(--fam-text)] truncate">
                                        {{ $entry->user->name }}
                                    </div>
                                    @if($isCurrentUser)
                                        <span class="shrink-0 px-2 py-0.5 rounded-full bg-[color:var(--fam-primary)] text-white text-xs font-bold">
                                            Toi
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <div class="text-sm text-[color:var(--fam-muted)]">{{ $percentage }}% de réussite</div>
                                    @if($entry->best_attempt_id && $entry->bestAttempt)
                                        <span class="text-xs text-[color:var(--fam-muted)]">
                                            • {{ $entry->bestAttempt->finished_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Score -->
                            <div class="shrink-0 text-right">
                                <div class="text-2xl font-bold text-[color:var(--fam-primary)]">
                                    {{ $entry->best_score }}
                                </div>
                                <div class="text-xs text-[color:var(--fam-muted)]">/ {{ $maxScore }} pts</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Actions -->
        @if(!$leaderboard->isEmpty())
            <div class="mt-6 text-center">
                @if(!$userBest)
                    <a href="{{ route('quiz.play', $quiz) }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-[color:var(--fam-primary)] text-white font-bold hover:bg-[color:var(--fam-primary-dark)] transition-colors shadow-lg">
                        <i class="ph ph-play-circle text-xl" aria-hidden="true"></i>
                        Jouer à ce quiz
                    </a>
                @else
                    <a href="{{ route('quiz.play', $quiz) }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-[color:var(--fam-primary)] text-white font-bold hover:bg-[color:var(--fam-primary-dark)] transition-colors shadow-lg">
                        <i class="ph ph-arrow-clockwise text-xl" aria-hidden="true"></i>
                        Améliorer ton score
                    </a>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
