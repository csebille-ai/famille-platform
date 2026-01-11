@php
    $items = [
        [
            'key' => 'home',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard') || request()->is('home'),
            'label' => 'Accueil',
            'icon' => 'house',
        ],
        [
            'key' => 'media',
            'href' => route('media.index'),
            'active' => request()->routeIs('media.*') || request()->routeIs('images.*') || request()->is('media') || request()->is('media/*'),
            'label' => 'Médias',
            'icon' => 'images-square',
        ],
        [
            'key' => 'library',
            'href' => route('mediatheque.index'),
            'active' => request()->routeIs('mediatheque.*') || request()->is('mediatheque') || request()->is('mediatheque/*') || request()->routeIs('videos.*') || request()->is('videos') || request()->is('videos/*'),
            'label' => 'Médiathèque',
            'icon' => 'film-slate',
        ],
        [
            'key' => 'chat',
            'href' => route('chat.index'),
            'active' => request()->routeIs('chat.*') || request()->is('chat') || request()->is('chat/*'),
            'label' => 'Chat',
            'icon' => 'chat-circle-text',
        ],
    ];
@endphp

<div class="px-4 pb-2">
    <div class="flex items-stretch">
        @foreach($items as $item)
            <a
                href="{{ $item['href'] }}"
                class="relative flex h-12 flex-1 items-center justify-center"
                aria-label="{{ $item['label'] }}"
                aria-current="{{ $item['active'] ? 'page' : 'false' }}"
            >
                <span class="mx-auto inline-flex h-12 w-full items-center justify-center {{ $item['active'] ? 'text-[#0B1220]' : 'text-[#64748B]' }}">
                    <i class="ph ph-{{ $item['icon'] }}" aria-hidden="true"></i>
                </span>

                @if($item['active'])
                    <span class="absolute bottom-1 left-0 right-0 mx-auto h-0.5 w-8 rounded-full bg-[#0B1220]"></span>
                @endif
            </a>
        @endforeach
    </div>
</div>
