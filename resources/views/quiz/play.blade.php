<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header -->
        <div class="mb-6 rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-[color:var(--fam-text)]">{{ $quiz->title }}</h1>
                    <div class="mt-1 text-sm text-[color:var(--fam-muted)]">
                        {{ count($questions) }} questions • {{ count($questions) * 10 }} points max
                    </div>
                </div>
                <div id="timer" class="text-right">
                    <div class="text-2xl font-bold text-[color:var(--fam-primary)]">00:00</div>
                    <div class="text-xs text-[color:var(--fam-muted)]">Temps écoulé</div>
                </div>
            </div>
        </div>

        <!-- Quiz Form -->
        <form id="quizForm" method="POST" action="{{ route('quiz.submit', ['quiz' => $quiz, 'attempt' => $attempt]) }}">
            @csrf
            
            <div class="space-y-4">
                @foreach($questions as $index => $question)
                    <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm overflow-hidden">
                        <!-- Question Header -->
                        <div class="px-6 py-4 bg-[color:var(--fam-surface-alt)] border-b border-[color:var(--fam-border-soft)]">
                            <div class="flex items-start gap-3">
                                <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[color:var(--fam-primary)] text-white text-sm font-bold">
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
                            </div>
                        </div>

                        <!-- Choices -->
                        <div class="p-4">
                            <div class="space-y-2">
                                @foreach($question->choices as $choice)
                                    <label class="quiz-choice-label flex items-start gap-3 p-4 rounded-xl border-2 border-[color:var(--fam-border-soft)] hover:border-[color:var(--fam-primary)]/40 hover:bg-[color:var(--fam-surface-alt)] cursor-pointer transition-all duration-200">
                                        <input
                                            type="radio"
                                            name="answers[{{ $index }}][choice_id]"
                                            value="{{ $choice->id }}"
                                            required
                                            class="mt-1 w-4 h-4 text-[color:var(--fam-primary)] focus:ring-[color:var(--fam-primary)]/25"
                                            onchange="markQuestionAnswered({{ $index }})"
                                        />
                                        <input type="hidden" name="answers[{{ $index }}][question_id]" value="{{ $question->id }}">
                                        <span class="flex-1 text-[color:var(--fam-text)]">{{ $choice->choice_text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Submit Button -->
            <div class="mt-6 sticky bottom-4 flex justify-center">
                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl bg-[color:var(--fam-primary)] text-white font-bold hover:bg-[color:var(--fam-primary-dark)] active:scale-95 transition-all duration-200 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    id="submitBtn"
                    disabled
                >
                    <i class="ph ph-check-circle text-2xl" aria-hidden="true"></i>
                    <span>Valider mes réponses</span>
                    <span id="answerProgress" class="px-2 py-0.5 rounded-full bg-white/20 text-sm">
                        0/{{ count($questions) }}
                    </span>
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        // Timer
        let startTime = Date.now();
        let timerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const seconds = (elapsed % 60).toString().padStart(2, '0');
            document.getElementById('timer').querySelector('.text-2xl').textContent = `${minutes}:${seconds}`;
        }, 1000);

        // Track answered questions
        let answeredCount = 0;
        const totalQuestions = {{ count($questions) }};
        const answeredQuestions = new Set();

        function markQuestionAnswered(questionIndex) {
            if (!answeredQuestions.has(questionIndex)) {
                answeredQuestions.add(questionIndex);
                answeredCount++;
                updateProgress();
            }
        }

        function updateProgress() {
            const progress = document.getElementById('answerProgress');
            const submitBtn = document.getElementById('submitBtn');
            
            progress.textContent = `${answeredCount}/${totalQuestions}`;
            
            if (answeredCount === totalQuestions) {
                submitBtn.disabled = false;
                submitBtn.classList.add('animate-pulse');
            }
        }

        // Add response time tracking
        const questionTimestamps = {};
        document.querySelectorAll('input[type="radio"]').forEach(input => {
            const questionIndex = input.name.match(/\d+/)[0];
            if (!questionTimestamps[questionIndex]) {
                questionTimestamps[questionIndex] = Date.now();
            }
            
            input.addEventListener('change', function() {
                const responseTime = Date.now() - questionTimestamps[questionIndex];
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = `answers[${questionIndex}][response_time_ms]`;
                hiddenInput.value = responseTime;
                this.parentElement.appendChild(hiddenInput);
            });
        });

        // Confirm before leaving
        let isSubmitting = false;
        window.addEventListener('beforeunload', (e) => {
            if (isSubmitting) return;
            e.preventDefault();
            e.returnValue = '';
        });

        // Remove warning on submit and disable button
        document.getElementById('quizForm').addEventListener('submit', (e) => {
            const btn = document.getElementById('submitBtn');
            if (btn.dataset.submitted === 'true') {
                e.preventDefault();
                return false;
            }
            btn.dataset.submitted = 'true';
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner ph-spin text-2xl" aria-hidden="true"></i> <span>Envoi en cours...</span>';
            isSubmitting = true;
            clearInterval(timerInterval);
        });

        // Selected choice styling
        document.querySelectorAll('.quiz-choice-label').forEach(label => {
            const input = label.querySelector('input[type="radio"]');
            input.addEventListener('change', function() {
                // Remove selected class from siblings
                const parent = this.closest('.space-y-2');
                parent.querySelectorAll('.quiz-choice-label').forEach(l => {
                    l.classList.remove('border-[color:var(--fam-primary)]', 'bg-[color:var(--fam-primary-50)]');
                });
                
                // Add selected class
                if (this.checked) {
                    label.classList.add('border-[color:var(--fam-primary)]', 'bg-[color:var(--fam-primary-50)]');
                }
            });
        });
    </script>
    @endpush

    <style>
        .quiz-choice-label:has(input:checked) {
            border-color: var(--fam-primary) !important;
            background-color: var(--fam-primary-50) !important;
        }
    </style>
</x-app-layout>
