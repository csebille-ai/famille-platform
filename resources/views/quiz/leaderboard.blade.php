<x-app-layout pageBgClass="fam-page-bg">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-[color:var(--fam-text)]">
                        <i class="ph-fill ph-trophy text-amber-500" aria-hidden="true"></i>
                        Classement global
                    </h1>
                    <p class="mt-1 text-sm text-[color:var(--fam-muted)]">
                        Somme des meilleurs scores par quiz
                    </p>
                </div>
                <a href="{{ route('quiz.index') }}" class="fam-link-subtle inline-flex items-center gap-1.5 text-sm font-semibold">
                    <i class="ph ph-arrow-left" aria-hidden="true"></i>
                    Retour aux quiz
                </a>
            </div>
        </div>

        <!-- User Position (if not in top 50) -->
        @if($userTotal && $userRank && $userRank > 50)
            <div class="mb-6 rounded-2xl bg-gradient-to-r from-[color:var(--fam-primary-100)] to-[color:var(--fam-primary-50)] border border-[color:var(--fam-primary)]/30 shadow-sm overflow-hidden">
                <div class="px-6 py-4">
                    <div class="flex items-center gap-4">
                        <div class="shrink-0 text-center">
                            <div class="text-2xl font-bold text-[color:var(--fam-primary)]">#{{ $userRank }}</div>
                            <div class="text-xs text-[color:var(--fam-muted)]">Ta position</div>
                        </div>
                        <div class="flex-1">
                            <div class="font-bold text-[color:var(--fam-text)]">{{ auth()->user()->name }}</div>
                            <div class="text-sm text-[color:var(--fam-muted)]">
                                {{ $userTotal->quizzes_completed }} quiz complété(s)
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-2xl font-bold text-[color:var(--fam-primary)]">{{ $userTotal->total_score }}</div>
                            <div class="text-xs text-[color:var(--fam-muted)]">points</div>
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
                    <p class="text-[color:var(--fam-muted)]">Aucun score enregistré pour le moment.</p>
                    <p class="mt-2 text-sm text-[color:var(--fam-muted)]">Sois le premier à jouer !</p>
                </div>
            @else
                <div class="divide-y divide-[color:var(--fam-border-soft)]">
                    @foreach($leaderboard as $index => $entry)
                        @php
                            $isCurrentUser = auth()->check() && $entry->user_id === auth()->id();
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
                                <div class="mt-0.5 text-sm text-[color:var(--fam-muted)]">
                                    {{ $entry->quizzes_completed }} quiz complété{{ $entry->quizzes_completed > 1 ? 's' : '' }}
                                </div>
                            </div>

                            <!-- Score -->
                            <div class="shrink-0 text-right">
                                <div class="text-2xl font-bold text-[color:var(--fam-primary)]">
                                    {{ number_format($entry->total_score, 0, ',', ' ') }}
                                </div>
                                <div class="text-xs text-[color:var(--fam-muted)]">points</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Info -->
        <div class="mt-6 p-4 rounded-xl bg-[color:var(--fam-surface-alt)] border border-[color:var(--fam-border-soft)]">
            <div class="flex items-start gap-3">
                <i class="ph ph-info text-xl text-[color:var(--fam-primary)]" aria-hidden="true"></i>
                <div class="flex-1 text-sm text-[color:var(--fam-muted)]">
                    <strong>Comment fonctionne le classement global ?</strong>
                    <br>
                    Ton score global est la somme de tes meilleurs scores sur chaque quiz. Seul le meilleur score de chaque quiz compte, donc tu peux rejouer autant que tu veux pour améliorer ton classement !
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
