@php
    $isHome = request()->routeIs('dashboard') || request()->is('home');
    $isMedia = request()->routeIs('media.*') || request()->routeIs('images.*') || request()->is('media') || request()->is('media/*');
    $isLibrary = request()->routeIs('mediatheque.*')
        || request()->is('mediatheque')
        || request()->is('mediatheque/*')
        || request()->routeIs('videos.*')
        || request()->is('videos')
        || request()->is('videos/*');
    $isChat = request()->routeIs('chat.*') || request()->is('chat') || request()->is('chat/*');

    $isPrimary = $isHome
        || request()->routeIs('media.index')
        || request()->routeIs('mediatheque.index')
        || request()->routeIs('videos.index')
        || request()->routeIs('chat.index');

    $mobileTitle = '—';
    if ($isHome) $mobileTitle = 'Accueil';
    elseif (request()->routeIs('mediatheque.index') || request()->routeIs('videos.index')) $mobileTitle = 'Médiathèque';
    elseif (request()->routeIs('images.*')) $mobileTitle = 'Photo';
    elseif (request()->routeIs('videos.*')) $mobileTitle = 'Vidéo';
    elseif ($isMedia) $mobileTitle = 'Médias';
    elseif ($isLibrary) $mobileTitle = 'Médiathèque';
    elseif ($isChat) $mobileTitle = 'Chat';
    else $mobileTitle = 'Famille';

    $showBack = !$isPrimary;
    $userName = Auth::user()->name ?? '';
    $userInitial = strtoupper(substr(trim($userName), 0, 1));
@endphp

<nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
    <!-- Mobile: 2-row sticky header -->
    <div class="sm:hidden">
        <!-- Row 1: app bar (iOS-clean) -->
        <div class="bg-white/95 backdrop-blur border-b border-[#EEF0F4]" style="padding-top: calc(env(safe-area-inset-top) + 0.75rem)">
            <div class="px-4 pb-1">
                <div class="flex items-center justify-between gap-3">
                    <div class="shrink-0">
                        @if($showBack)
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-2xl border border-[#EEF0F4] bg-white text-[#0F172A] hover:bg-[#F6F7F9]"
                                aria-label="Retour"
                                onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('dashboard') }}'; }"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                    <path d="M15 18l-6-6 6-6" />
                                </svg>
                            </button>
                        @else
                            <a href="{{ route('dashboard') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-2xl border border-[#EEF0F4] bg-white" aria-label="Accueil">
                                <x-application-logo class="block h-6 w-auto fill-current text-[#0F172A]" />
                            </a>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1 text-center">
                        <div class="text-[0.95rem] font-semibold text-[#0F172A] truncate">{{ $mobileTitle }}</div>
                    </div>

                    <div class="shrink-0 flex items-center gap-2">
                        <div class="inline-flex items-center">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-[#EEF0F4] bg-white text-xs font-semibold text-[#0F172A]">
                                        {{ $userInitial }}
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

                            <div class="-ml-2 inline-flex h-8 w-8 items-center justify-center rounded-full border border-[#EEF0F4] bg-white text-xs font-semibold text-slate-300">&nbsp;</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: primary nav (component, 4 equal tap areas) -->
            <x-mobile-primary-nav />
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
