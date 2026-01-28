<x-app-layout pageBgClass="fam-page-bg">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-[color:var(--fam-text)]">
                Quiz
            </h2>
            <a href="{{ route('quiz.leaderboard') }}" class="fam-link-subtle inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-semibold hover:bg-[color:var(--fam-primary-100)] active:bg-[color:var(--fam-primary-200)]">
                <i class="ph ph-trophy text-base" aria-hidden="true"></i>
                Classement global
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Filters -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <!-- Search -->
            <form method="GET" class="flex-1 min-w-[200px] max-w-md">
                <input
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Rechercher un quiz..."
                    class="w-full rounded-xl border-[color:var(--fam-border)] focus:border-[color:var(--fam-primary)] focus:ring focus:ring-[color:var(--fam-primary)]/25 text-sm"
                />
            </form>

            <!-- Category filter -->
            <select
                name="category"
                onchange="this.form.submit()"
                class="rounded-xl border-[color:var(--fam-border)] focus:border-[color:var(--fam-primary)] focus:ring focus:ring-[color:var(--fam-primary)]/25 text-sm"
            >
                <option value="">Toutes catégories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>
                        {{ $cat }}
                    </option>
                @endforeach
            </select>

            <!-- Difficulty filter -->
            <select
                name="difficulty"
                onchange="this.form.submit()"
                class="rounded-xl border-[color:var(--fam-border)] focus:border-[color:var(--fam-primary)] focus:ring focus:ring-[color:var(--fam-primary)]/25 text-sm"
            >
                <option value="">Toutes difficultés</option>
                @foreach($difficulties as $diff)
                    <option value="{{ $diff }}" {{ request('difficulty') === $diff ? 'selected' : '' }}>
                        @if($diff === 'easy') Facile
                        @elseif($diff === 'medium') Moyen
                        @elseif($diff === 'hard') Difficile
                        @else {{ ucfirst($diff) }}
                        @endif
                    </option>
                @endforeach
            </select>

            <!-- Sort -->
            <select
                name="sort"
                onchange="this.form.submit()"
                class="rounded-xl border-[color:var(--fam-border)] focus:border-[color:var(--fam-primary)] focus:ring focus:ring-[color:var(--fam-primary)]/25 text-sm"
            >
                <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Plus récents</option>
                <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>Populaires</option>
                <option value="title" {{ request('sort') === 'title' ? 'selected' : '' }}>Alphabétique</option>
            </select>
        </div>

        <!-- Quiz Grid -->
        @if($quizzes->isEmpty())
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[color:var(--fam-surface-alt)] text-[color:var(--fam-muted)] mb-4">
                    <i class="ph ph-question text-3xl" aria-hidden="true"></i>
                </div>
                <p class="text-[color:var(--fam-muted)]">Aucun quiz disponible pour le moment.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($quizzes as $quiz)
                    @php
                        $difficultyColors = [
                            'easy' => ['bg' => 'bg-green-50', 'text' => 'text-green-800', 'border' => 'border-green-200'],
                            'medium' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-800', 'border' => 'border-amber-200'],
                            'hard' => ['bg' => 'bg-red-50', 'text' => 'text-red-800', 'border' => 'border-red-200'],
                        ];
                        $diffColors = $difficultyColors[$quiz->difficulty] ?? $difficultyColors['medium'];
                        
                        $userBestScore = $userBestScores[$quiz->id] ?? null;
                        $maxScore = $quiz->questions_count * 10; // assuming 10 points per question
                    @endphp

                    <a href="{{ route('quiz.show', $quiz) }}" class="group block rounded-2xl bg-white border border-[color:var(--fam-border)] hover:border-[color:var(--fam-primary)]/40 hover:shadow-md transition-all duration-200 overflow-hidden">
                        <!-- Header -->
                        <div class="px-4 py-3 border-b border-[color:var(--fam-border-soft)]">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)]">
                                    <i class="ph ph-brain text-2xl" aria-hidden="true"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold text-[color:var(--fam-text)] group-hover:text-[color:var(--fam-primary)] transition-colors truncate">
                                        {{ $quiz->title }}
                                    </h3>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-[color:var(--fam-muted)]">
                                        <span>{{ $quiz->category }}</span>
                                        <span>•</span>
                                        <span>{{ $quiz->questions_count }} questions</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <!-- Difficulty badge -->
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $diffColors['bg'] }} {{ $diffColors['text'] }} border {{ $diffColors['border'] }} text-xs font-semibold">
                                    @if($quiz->difficulty === 'easy')
                                        <i class="ph ph-circle text-xs" aria-hidden="true"></i>
                                        Facile
                                    @elseif($quiz->difficulty === 'medium')
                                        <i class="ph ph-circles-three text-xs" aria-hidden="true"></i>
                                        Moyen
                                    @else
                                        <i class="ph ph-fire text-xs" aria-hidden="true"></i>
                                        Difficile
                                    @endif
                                </span>

                                <!-- User best score -->
                                @if($userBestScore)
                                    <div class="text-right">
                                        <div class="text-xs text-[color:var(--fam-muted)]">Ton meilleur</div>
                                        <div class="text-sm font-bold text-[color:var(--fam-primary)]">
                                            {{ $userBestScore }} / {{ $maxScore }}
                                        </div>
                                    </div>
                                @else
                                    <div class="text-xs text-[color:var(--fam-muted)] italic">
                                        Pas encore joué
                                    </div>
                                @endif
                            </div>

                            <!-- Source -->
                            <div class="mt-3 pt-3 border-t border-[color:var(--fam-border-soft)] flex items-center gap-1.5 text-xs text-[color:var(--fam-muted)]">
                                <i class="ph ph-globe text-sm" aria-hidden="true"></i>
                                Source: {{ $quiz->source }}
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $quizzes->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
