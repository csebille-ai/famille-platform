@php
    $items = [
        [
            'key' => 'home',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'label' => 'Accueil',
            'icon' => 'home',
        ],
        [
            'key' => 'media',
            'href' => route('media.index'),
            'active' => request()->routeIs('media.*') || request()->routeIs('images.*') || request()->routeIs('videos.*') && !request()->routeIs('videos.index'),
            'label' => 'Médias',
            'icon' => 'media',
        ],
        [
            'key' => 'library',
            'href' => route('videos.index'),
            'active' => request()->routeIs('videos.index'),
            'label' => 'Médiathèque',
            'icon' => 'doc',
        ],
        [
            'key' => 'chat',
            'href' => route('chat.index'),
            'active' => request()->routeIs('chat.*'),
            'label' => 'Chat',
            'icon' => 'chat',
        ],
    ];
@endphp

<div class="px-4 pb-2">
    <div class="flex items-stretch">
        @foreach($items as $item)
            <a
                href="{{ $item['href'] }}"
                class="relative flex-1 py-2"
                aria-label="{{ $item['label'] }}"
                aria-current="{{ $item['active'] ? 'page' : 'false' }}"
            >
                <div class="mx-auto flex h-9 w-14 items-center justify-center rounded-2xl {{ $item['active'] ? 'text-[#0B1220]' : 'text-[#64748B]' }}">
                    @if($item['icon'] === 'home')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                            <path d="M3 10.5L12 3l9 7.5" />
                            <path d="M5 10v10h14V10" />
                        </svg>
                    @elseif($item['icon'] === 'media')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="M8 13l2.5-2.5L14 14l2-2 3 3" />
                            <path d="M8.5 10.5h.01" />
                        </svg>
                    @elseif($item['icon'] === 'doc')
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                            <path d="M4 19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7l-4-4H6a2 2 0 0 0-2 2z" />
                            <path d="M8 11h8" />
                            <path d="M8 15h8" />
                            <path d="M15 3v4h4" />
                        </svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
                        </svg>
                    @endif
                </div>

                @if($item['active'])
                    <span class="absolute bottom-1 left-0 right-0 mx-auto h-0.5 w-8 rounded-full bg-[#0B1220]"></span>
                @endif
            </a>
        @endforeach
    </div>
</div>
