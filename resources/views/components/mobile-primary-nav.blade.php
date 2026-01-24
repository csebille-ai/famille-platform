@props([
    'fixed' => true,
])

@php
    $hasTarotDraft = (bool) session()->has('tarot.draft');
    $hasNewActu = (bool) session()->get('news.has_new', false);

    $isPlus = request()->routeIs('plus.*')
        || request()->is('plus')
        || request()->is('plus/*')
        || request()->routeIs('tarot.*')
        || request()->routeIs('actu.*')
        || request()->routeIs('playlists.*')
        || request()->routeIs('astro.*')
        || request()->routeIs('profile.*');

    $items = [
        [
            'key' => 'home',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard') || request()->is('home'),
            'label' => 'Accueil',
            'icon' => 'house',
        ],
        [
            'key' => 'gallery',
            'href' => route('media.index'),
            'active' => request()->routeIs('media.*')
                || request()->routeIs('images.*')
                || request()->is('media')
                || request()->is('media/*')
                || request()->routeIs('videos.*')
                || request()->is('videos/*'),
            'label' => 'Galerie',
            'icon' => 'images-square',
        ],
        [
            'key' => 'chat',
            'href' => route('chat.index'),
            'active' => request()->routeIs('chat.*') || request()->is('chat') || request()->is('chat/*'),
            'label' => 'Chat',
            'icon' => 'chat-circle-text',
        ],
        [
            'key' => 'plus',
            'href' => route('plus.index'),
            'active' => $isPlus,
            'label' => 'Plus',
            'icon' => 'dots-three-outline',
            'badge' => ($hasTarotDraft || $hasNewActu),
        ],
    ];
@endphp

@php
    // Tarot icon no longer used in bottom nav (moved under Plus).
@endphp

<nav
    class="sm:hidden {{ $fixed ? 'fixed bottom-0 left-0 right-0 z-50' : 'w-full' }} bg-[color:var(--fam-surface)]/95 supports-[backdrop-filter]:bg-[color:var(--fam-surface)]/80 supports-[backdrop-filter]:backdrop-blur-xl border-t border-[color:var(--fam-border-soft)] shadow-[0_-10px_25px_rgba(15,23,42,0.08)] pb-[env(safe-area-inset-bottom)]"
    style="--mobile-bottom-nav-h: 4rem;"
    aria-label="Navigation principale"
>
    <div class="px-2">
        <div class="flex items-stretch">
            @foreach($items as $item)
                <a
                    href="{{ $item['href'] }}"
                    class="relative flex h-16 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-2 text-[11px] font-semibold {{ $item['active'] ? 'text-[color:var(--fam-primary)] bg-[color:var(--fam-primary-100)]' : 'text-[color:var(--fam-muted)]' }}"
                    aria-label="{{ $item['label'] }}"
                    aria-current="{{ $item['active'] ? 'page' : 'false' }}"
                >
                    <span class="relative inline-flex h-6 w-6 items-center justify-center">
                        <i class="ph ph-{{ $item['icon'] }} text-[22px]" aria-hidden="true"></i>

                        @if(!empty($item['badge']))
                            <span class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-[color:var(--fam-primary-300)] ring-2 ring-[color:var(--fam-surface)]" aria-hidden="true"></span>
                        @endif
                    </span>

                    <span class="leading-none">{{ $item['label'] }}</span>

                    @if($item['active'])
                        <span class="absolute bottom-1 left-0 right-0 mx-auto h-0.5 w-10 rounded-full bg-[color:var(--fam-primary)]" aria-hidden="true"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</nav>
