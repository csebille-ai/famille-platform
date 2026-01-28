<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Result Header -->
        <div class="mb-6 rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm overflow-hidden">
            <div class="px-6 py-8 text-center">
                @php
                    $maxScore = $quiz->questions_count * 10;
                    $percentage = $maxScore > 0 ? round(($attempt->score / $maxScore) * 100) : 0;
                    $correctCount = $attempt->answers->where('is_correct', true)->count();
                @endphp

                <!-- Score Display -->
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] mb-4">
                    @if($percentage >= 80)
                        <i class="ph-fill ph-trophy text-5xl" aria-hidden="true"></i>
                    @elseif($percentage >= 60)
                        <i class="ph-fill ph-smiley text-5xl" aria-hidden="true"></i>
                    @else
                        <i class="ph-fill ph-meh text-5xl" aria-hidden="true"></i>
                    @endif
                </div>

                <h1 class="text-3xl font-bold text-[color:var(--fam-text)]">
                    @if($percentage >= 80)
                        Excellent !
                    @elseif($percentage >= 60)
                        Bien joué !
                    @else
                        Pas mal !
                    @endif
                </h1>

                <div class="mt-4 text-5xl font-bold text-[color:var(--fam-primary)]">
                    {{ $attempt->score }} / {{ $maxScore }}
                </div>

                <div class="mt-2 text-lg text-[color:var(--fam-muted)]">
                    {{ $correctCount }} / {{ $quiz->questions_count }} réponses correctes ({{ $percentage }}%)
                </div>

                <!-- Stats -->
                <div class="mt-6 flex items-center justify-center gap-6 text-sm">
                    <div class="flex items-center gap-2">
                        <i class="ph ph-timer text-xl text-[color:var(--fam-muted)]" aria-hidden="true"></i>
                        <span class="text-[color:var(--fam-text)]">
                            @php
                                $minutes = floor($attempt->duration_seconds / 60);
                                $seconds = $attempt->duration_seconds % 60;
                            @endphp
                            {{ $minutes }}:{{ str_pad($seconds, 2, '0', STR_PAD_LEFT) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ph ph-calendar text-xl text-[color:var(--fam-muted)]" aria-hidden="true"></i>
                        <span class="text-[color:var(--fam-text)]">
                            {{ $attempt->finished_at->translatedFormat('d M Y à H:i') }}
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-8 flex items-center justify-center gap-3">
                    <a href="{{ route('quiz.play', $quiz) }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-[color:var(--fam-primary)] text-white font-bold hover:bg-[color:var(--fam-primary-dark)] transition-colors">
                        <i class="ph ph-arrow-clockwise text-xl" aria-hidden="true"></i>
                        Rejouer
                    </a>
                    <a href="{{ route('quiz.show', $quiz) }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-[color:var(--fam-surface-alt)] text-[color:var(--fam-text)] font-semibold hover:bg-[color:var(--fam-surface-2)] transition-colors border border-[color:var(--fam-border)]">
                        <i class="ph ph-arrow-left text-xl" aria-hidden="true"></i>
                        Retour au quiz
                    </a>
                </div>
            </div>
        </div>

        <!-- Detailed Correction -->
        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-[color:var(--fam-surface-alt)] border-b border-[color:var(--fam-border-soft)]">
                <h2 class="text-lg font-bold text-[color:var(--fam-text)]">
                    <i class="ph ph-list-checks text-xl" aria-hidden="true"></i>
                    Correction détaillée
                </h2>
            </div>

            <div class="divide-y divide-[color:var(--fam-border-soft)]">
                @foreach($attempt->answers as $index => $answer)
                    @php
                        $question = $answer->question;
                        $allChoices = $question->choices;
                        $correctChoice = $allChoices->where('is_correct', true)->first();
                        $userChoice = $answer->choice;
                        $isCorrect = $answer->is_correct;
                    @endphp

                    <div class="px-6 py-5">
                        <!-- Question -->
                        <div class="flex items-start gap-3 mb-4">
                            <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg {{ $isCorrect ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} text-sm font-bold">
                                {{ $index + 1 }}
                            </span>
                            <div class="flex-1">
                                <h3 class="font-semibold text-[color:var(--fam-text)]">
                                    {{ $question->question_text }}
                                </h3>
                                <div class="mt-1 text-xs text-[color:var(--fam-muted)]">
                                    {{ $question->points }} points
                                </div>
                            </div>
                            @if($isCorrect)
                                <i class="ph-fill ph-check-circle text-2xl text-green-500" aria-hidden="true"></i>
                            @else
                                <i class="ph-fill ph-x-circle text-2xl text-red-500" aria-hidden="true"></i>
                            @endif
                        </div>

                        <!-- Choices -->
                        <div class="ml-11 space-y-2">
                            @foreach($allChoices as $choice)
                                @php
                                    $isUserChoice = $userChoice && $userChoice->id === $choice->id;
                                    $isCorrectAnswer = $choice->is_correct;
                                @endphp

                                <div class="p-3 rounded-lg border-2 {{ 
                                    $isCorrectAnswer ? 'border-green-500 bg-green-50' : 
                                    ($isUserChoice && !$isCorrect ? 'border-red-500 bg-red-50' : 'border-[color:var(--fam-border-soft)]') 
                                }}">
                                    <div class="flex items-center gap-2">
                                        @if($isCorrectAnswer)
                                            <i class="ph-fill ph-check-circle text-green-600" aria-hidden="true"></i>
                                        @elseif($isUserChoice)
                                            <i class="ph-fill ph-x-circle text-red-600" aria-hidden="true"></i>
                                        @else
                                            <div class="w-5 h-5 rounded-full border-2 border-[color:var(--fam-border)]"></div>
                                        @endif
                                        
                                        <span class="text-[color:var(--fam-text)] {{ $isCorrectAnswer ? 'font-semibold' : '' }}">
                                            {{ $choice->choice_text }}
                                        </span>

                                        @if($isCorrectAnswer)
                                            <span class="ml-auto text-xs font-semibold text-green-700">Bonne réponse</span>
                                        @elseif($isUserChoice)
                                            <span class="ml-auto text-xs font-semibold text-red-700">Ta réponse</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Response time -->
                        @if($answer->response_time_ms)
                            <div class="mt-3 ml-11 text-xs text-[color:var(--fam-muted)]">
                                <i class="ph ph-timer" aria-hidden="true"></i>
                                Temps de réponse: {{ number_format($answer->response_time_ms / 1000, 1) }}s
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Back to quiz list -->
        <div class="mt-6 text-center">
            <a href="{{ route('quiz.index') }}" class="fam-link-subtle inline-flex items-center gap-1.5 text-sm font-semibold">
                <i class="ph ph-arrow-left" aria-hidden="true"></i>
                Retour à la bibliothèque de quiz
            </a>
        </div>
    </div>
</x-app-layout>
