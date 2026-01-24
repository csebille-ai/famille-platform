<x-app-layout pageBgClass="fam-page-bg">
    @php
        $hasTarotDraft = (bool) ($hasTarotDraft ?? session()->has('tarot.draft'));
        $hasNewActu = (bool) ($hasNewActu ?? session()->get('news.has_new', false));

        $card = function (string $href, string $title, string $subtitle, string $icon, bool $badge = false) {
            return [
                'href' => $href,
                'title' => $title,
                'subtitle' => $subtitle,
                'icon' => $icon,
                'badge' => $badge,
            ];
        };

        $fun = [
            $card(route('tarot.index'), 'Tarot', $hasTarotDraft ? 'Brouillon en cours' : 'Tirage & lectures', 'sparkle', $hasTarotDraft),
            $card(route('playlists.index'), 'Playlists', 'Musique & listes', 'music-notes', false),
        ];

        $infos = [
            $card(route('actu.index'), 'Actu locale', $hasNewActu ? 'Nouveautés récentes' : 'Météo & infos', 'newspaper-clipping', $hasNewActu),
        ];

        $account = [
            $card(route('profile.edit'), 'Profil', 'Compte & avatar', 'user-circle', false),
            $card(route('astro.show'), 'Ma fiche astro', 'Profil astral', 'planet', false),
        ];

        $showAdmin = false;
        try {
            $showAdmin = (bool) Gate::check('manage-users');
        } catch (\Throwable $e) {
            $showAdmin = false;
        }
    @endphp

    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-4">
        <div class="rounded-2xl bg-white px-3 py-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Plus</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">Raccourcis, infos et compte</div>
                </div>

                @if($hasTarotDraft || $hasNewActu)
                    <span class="ui-badge ui-badge--brand">Nouveauté</span>
                @endif
            </div>
        </div>

        <section class="space-y-2">
            <div class="px-1">
                <div class="text-xs font-extrabold uppercase tracking-wider text-[color:var(--fam-muted)]">Fun</div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($fun as $item)
                    <a href="{{ $item['href'] }}" class="relative rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm hover:shadow transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 shrink-0 rounded-2xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] flex items-center justify-center">
                                <i class="ph ph-{{ $item['icon'] }} text-[20px]" aria-hidden="true"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $item['title'] }}</div>
                                    @if($item['badge'])
                                        <span class="inline-block h-2 w-2 rounded-full bg-[color:var(--fam-primary-300)]" aria-hidden="true"></span>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)] truncate">{{ $item['subtitle'] }}</div>
                            </div>

                            <div class="text-[color:var(--fam-muted)] mt-1">
                                <i class="ph ph-caret-right" aria-hidden="true"></i>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="space-y-2">
            <div class="px-1">
                <div class="text-xs font-extrabold uppercase tracking-wider text-[color:var(--fam-muted)]">Infos</div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($infos as $item)
                    <a href="{{ $item['href'] }}" class="relative rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm hover:shadow transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 shrink-0 rounded-2xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] flex items-center justify-center">
                                <i class="ph ph-{{ $item['icon'] }} text-[20px]" aria-hidden="true"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $item['title'] }}</div>
                                    @if($item['badge'])
                                        <span class="inline-block h-2 w-2 rounded-full bg-[color:var(--fam-primary-300)]" aria-hidden="true"></span>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)] truncate">{{ $item['subtitle'] }}</div>
                            </div>

                            <div class="text-[color:var(--fam-muted)] mt-1">
                                <i class="ph ph-caret-right" aria-hidden="true"></i>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="space-y-2">
            <div class="px-1">
                <div class="text-xs font-extrabold uppercase tracking-wider text-[color:var(--fam-muted)]">Compte</div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($account as $item)
                    <a href="{{ $item['href'] }}" class="relative rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm hover:shadow transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 shrink-0 rounded-2xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] flex items-center justify-center">
                                <i class="ph ph-{{ $item['icon'] }} text-[20px]" aria-hidden="true"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $item['title'] }}</div>
                                <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)] truncate">{{ $item['subtitle'] }}</div>
                            </div>

                            <div class="text-[color:var(--fam-muted)] mt-1">
                                <i class="ph ph-caret-right" aria-hidden="true"></i>
                            </div>
                        </div>
                    </a>
                @endforeach

                @if($showAdmin)
                    <a href="{{ route('admin.users.index') }}" class="relative rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm hover:shadow transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 shrink-0 rounded-2xl bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] flex items-center justify-center">
                                <i class="ph ph-shield-chevron text-[20px]" aria-hidden="true"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">Admin</div>
                                <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)] truncate">Gestion</div>
                            </div>

                            <div class="text-[color:var(--fam-muted)] mt-1">
                                <i class="ph ph-caret-right" aria-hidden="true"></i>
                            </div>
                        </div>
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="contents">
                    @csrf
                    <button type="submit" class="w-full text-left relative rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm hover:shadow transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 shrink-0 rounded-2xl bg-[color:var(--fam-surface-alt)] text-[color:var(--fam-muted)] flex items-center justify-center border border-[color:var(--fam-border-soft)]">
                                <i class="ph ph-sign-out text-[20px]" aria-hidden="true"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">Déconnexion</div>
                                <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)] truncate">Se déconnecter</div>
                            </div>

                            <div class="text-[color:var(--fam-muted)] mt-1">
                                <i class="ph ph-caret-right" aria-hidden="true"></i>
                            </div>
                        </div>
                    </button>
                </form>
            </div>
        </section>
    </div>
</x-app-layout>
