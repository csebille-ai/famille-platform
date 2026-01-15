@props([
    'fixed' => true,
])

@php
    $hasTarotDraft = (bool) session()->has('tarot.draft');
    $hasNewActu = (bool) session()->get('news.has_new', false);

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
                || request()->routeIs('mediatheque.*')
                || request()->is('mediatheque')
                || request()->is('mediatheque/*')
                || request()->routeIs('videos.*')
                || request()->is('videos')
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
            'key' => 'tarot',
            'href' => route('tarot.index'),
            'active' => request()->routeIs('tarot.*') || request()->is('tarot') || request()->is('tarot/*'),
            'label' => 'Tarot',
            'icon' => null,
            'badge' => $hasTarotDraft,
        ],
        [
            'key' => 'actu',
            'href' => route('actu.index'),
            'active' => request()->routeIs('actu.*') || request()->is('actu') || request()->is('actu/*'),
            'label' => 'Actu',
            'icon' => 'newspaper-clipping',
            'badge' => $hasNewActu,
        ],
    ];
@endphp

<nav
    class="sm:hidden {{ $fixed ? 'fixed inset-x-0 bottom-0 z-40' : 'w-full' }} border-t border-[#EEF0F4] bg-white/95 backdrop-blur pb-[env(safe-area-inset-bottom)]"
    style="--mobile-bottom-nav-h: 4rem;"
    aria-label="Navigation principale"
>
    <div class="px-2">
        <div class="flex items-stretch">
            @foreach($items as $item)
                <a
                    href="{{ $item['href'] }}"
                    class="relative flex h-16 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-2 text-[11px] font-semibold {{ $item['active'] ? 'text-[#0B1220]' : 'text-[#64748B]' }}"
                    aria-label="{{ $item['label'] }}"
                    aria-current="{{ $item['active'] ? 'page' : 'false' }}"
                >
                    <span class="relative inline-flex h-6 w-6 items-center justify-center">
                        @if(($item['key'] ?? '') === 'tarot')
                            <img src="{{ asset('images/crystal.png') }}" alt="" class="h-6 w-6 object-contain" aria-hidden="true" />
                        @else
                            <i class="ph ph-{{ $item['icon'] }} text-[20px]" aria-hidden="true"></i>
                        @endif

                        @if(!empty($item['badge']))
                            <span class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-[#EF4444] ring-2 ring-white" aria-hidden="true"></span>
                        @endif
                    </span>

                    <span class="leading-none">{{ $item['label'] }}</span>

                    @if($item['active'])
                        <span class="absolute bottom-1 left-0 right-0 mx-auto h-0.5 w-10 rounded-full bg-[#0B1220]" aria-hidden="true"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</nav>
