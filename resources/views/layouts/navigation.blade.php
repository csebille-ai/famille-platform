@php
    $isHome = request()->routeIs('dashboard');
    $isMedia = request()->routeIs('media.*') || request()->routeIs('images.*') || request()->routeIs('videos.*');
    $isResources = request()->routeIs('resources.*');
    $isChat = request()->routeIs('chat.*');

    $isPrimary = $isHome || request()->routeIs('media.index') || request()->routeIs('resources.index') || request()->routeIs('chat.index');

    $mobileTitle = '—';
    if ($isHome) $mobileTitle = 'Accueil';
    elseif (request()->routeIs('media.*')) $mobileTitle = 'Médias';
    elseif (request()->routeIs('images.*')) $mobileTitle = 'Photo';
    elseif (request()->routeIs('videos.*')) $mobileTitle = 'Vidéo';
    elseif ($isResources) $mobileTitle = 'Ressources';
    elseif ($isChat) $mobileTitle = 'Chat';

    $showBack = !$isPrimary;
@endphp

<nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
    <!-- Mobile: 2-row sticky header -->
    <div class="sm:hidden">
        <!-- Row 1: app bar -->
        <div class="px-4 pt-3 pb-2 border-b border-slate-100 bg-white/95 backdrop-blur">
            <div class="flex items-center justify-between gap-3">
                <div class="shrink-0">
                    @if($showBack)
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                            aria-label="Retour"
                            onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('dashboard') }}'; }"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                        </button>
                    @else
                        <a href="{{ route('dashboard') }}" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white">
                            <x-application-logo class="block h-7 w-auto fill-current text-gray-900" />
                            <span class="sr-only">Accueil</span>
                        </a>
                    @endif
                </div>

                <div class="min-w-0 flex-1 text-center">
                    <div class="text-sm font-semibold text-gray-900 truncate">{{ $mobileTitle }}</div>
                </div>

                <div class="shrink-0 flex items-center gap-2">
                    @if(request()->routeIs('media.*'))
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-900 hover:bg-gray-50"
                            @click="$dispatch('open-add')"
                            aria-haspopup="dialog"
                            aria-label="Ajouter"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true">
                                <path d="M12 5v14" />
                                <path d="M5 12h14" />
                            </svg>
                        </button>
                    @endif

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-sm font-semibold text-gray-700">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @can('manage-users')
                                <x-dropdown-link :href="route('admin.users.index')">
                                    {{ __('Admin') }}
                                </x-dropdown-link>
                            @endcan

                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>

        <!-- Row 2: primary nav icons -->
        <div class="px-4 py-2 bg-white/95 backdrop-blur">
            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $isHome ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}" aria-label="Accueil" aria-current="{{ $isHome ? 'page' : 'false' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M3 10.5L12 3l9 7.5" />
                        <path d="M5 10v10h14V10" />
                    </svg>
                </a>

                <a href="{{ route('media.index') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $isMedia ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}" aria-label="Médias" aria-current="{{ $isMedia ? 'page' : 'false' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path d="M8 13l2.5-2.5L14 14l2-2 3 3" />
                        <path d="M8.5 10.5h.01" />
                    </svg>
                </a>

                <a href="{{ route('resources.index') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $isResources ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}" aria-label="Ressources" aria-current="{{ $isResources ? 'page' : 'false' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M4 7a2 2 0 012-2h5l2 2h7a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V7z" />
                    </svg>
                </a>

                <a href="{{ route('chat.index') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $isChat ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}" aria-label="Chat" aria-current="{{ $isChat ? 'page' : 'false' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4v8z" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Desktop: keep existing menu -->
    <div class="hidden sm:block">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    @unless(request()->routeIs('dashboard'))
                        <div class="flex items-center me-2">
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                aria-label="Retour"
                                onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('dashboard') }}'; }"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                    <path d="M15 18l-6-6 6-6" />
                                </svg>
                            </button>
                        </div>
                    @endunless

                    <div class="shrink-0 flex items-center">
                        <a href="{{ route('dashboard') }}">
                            <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                        </a>
                    </div>

                    <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            Accueil
                        </x-nav-link>

                        <x-nav-link :href="route('media.index')" :active="request()->routeIs('media.*')">
                            Médias
                        </x-nav-link>

                        <x-nav-link :href="route('resources.index')" :active="request()->routeIs('resources.*')">
                            {{ __('Ressources') }}
                        </x-nav-link>

                        <x-nav-link :href="route('chat.index')" :active="request()->routeIs('chat.*')">
                            Chat
                        </x-nav-link>
                    </div>
                </div>

                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                <div>{{ Auth::user()->name }}</div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @can('manage-users')
                                <x-dropdown-link :href="route('admin.users.index')">
                                    {{ __('Admin') }}
                                </x-dropdown-link>
                            @endcan

                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>
</nav>
