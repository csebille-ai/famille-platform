@php
    $isHome = request()->routeIs('dashboard') || request()->is('home');
    $isMedia = request()->routeIs('media.*')
        || request()->routeIs('images.*')
        || request()->is('media')
        || request()->is('media/*')
        || request()->routeIs('videos.*')
        || request()->is('videos/*');
    $isChat = request()->routeIs('chat.*') || request()->is('chat') || request()->is('chat/*');

    $isPrimary = $isHome
        || request()->routeIs('media.index')
        || request()->routeIs('chat.index')
        || request()->routeIs('family.*')
        || request()->routeIs('tarot.index')
        || request()->routeIs('actu.index');

    $mobileTitle = '—';
    if ($isHome) $mobileTitle = 'Accueil';
    elseif (request()->routeIs('profile.*')) $mobileTitle = 'Profile';
    elseif (request()->routeIs('astro.show')) $mobileTitle = 'Ma fiche astro';
    elseif (request()->routeIs('tarot.*')) $mobileTitle = 'Tarot';
    elseif (request()->routeIs('actu.*')) $mobileTitle = 'Actu locale';
    elseif (request()->routeIs('images.*')) $mobileTitle = 'Photo';
    elseif (request()->routeIs('videos.*')) $mobileTitle = 'Vidéo';
    elseif ($isMedia) $mobileTitle = 'Médias';
    elseif ($isChat) $mobileTitle = 'Chat';
    else $mobileTitle = 'Famille';

    $showBack = !$isPrimary;
    $userName = Auth::user()->name ?? '';
    $userInitial = strtoupper(substr(trim($userName), 0, 1));

    $avatarUrl = null;
    try {
        $avatarUrl = avatarUrl(Auth::user());
    } catch (\Throwable $e) {
        $avatarUrl = null;
    }

    $hasTarotDraft = (bool) session()->has('tarot.draft');
    $hasNewActu = (bool) session()->get('news.has_new', false);

    $tarotIconUrl = asset('images/carte.png');
    try {
        $tarotIconPath = public_path('images/carte.png');
        if (is_string($tarotIconPath) && is_file($tarotIconPath)) {
            $tarotIconUrl .= '?v=' . (string) filemtime($tarotIconPath);
        }
    } catch (\Throwable $e) {
        // ignore
    }
@endphp

@if(request()->routeIs('chat.*'))
    {{-- Chat is conversation-first and provides its own sticky header. --}}
@else
<nav id="appTopNav" class="bg-[color:var(--fam-surface)] border-b border-[color:var(--fam-border)] fixed top-0 inset-x-0 z-50">
    <!-- Mobile: single sticky top bar -->
    <div class="sm:hidden">
        <!-- App bar (iOS-clean) -->
        <div class="bg-[color:var(--fam-surface)]/95 backdrop-blur border-b border-[color:var(--fam-border)]" style="padding-top: calc(env(safe-area-inset-top) + 0.25rem)">
            <div class="px-4 pb-0.5">
                <div class="relative flex items-center justify-between gap-3">
                    <div class="shrink-0 z-10">
                        @if($showBack)
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-[color:var(--fam-border)] bg-[color:var(--fam-surface)] text-[color:var(--fam-text)] hover:bg-[color:rgba(14,165,160,0.10)]"
                                aria-label="Retour"
                                onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('dashboard') }}'; }"
                            >
                                <i class="ph ph-caret-left" aria-hidden="true"></i>
                            </button>
                        @else
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-0 py-0.5" aria-label="Accueil">
                                <img
                                    src="{{ asset('images/icon-192.png') }}?v=3"
                                    srcset="{{ asset('images/icon-192.png') }}?v=3 192w, {{ asset('images/icon-512.png') }}?v=3 512w"
                                    sizes="44px"
                                    alt="Famille"
                                    class="h-10 w-10 object-contain"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </a>
                        @endif
                    </div>

                    <div class="pointer-events-none absolute inset-y-0 left-0 right-0 flex items-center justify-center px-24">
                        <div class="text-[0.95rem] font-semibold text-[color:var(--fam-text)] truncate max-w-[55vw]">{{ $mobileTitle }}</div>
                    </div>

                    <div class="shrink-0 flex items-center gap-2 z-10">
                        <div class="inline-flex items-center gap-2">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button class="ui-chip h-8 w-8 text-xs font-semibold overflow-hidden border-transparent shadow-none bg-white/80">
                                        @if(is_string($avatarUrl) && $avatarUrl !== '')
                                            <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                                        @else
                                            {{ $userInitial }}
                                        @endif
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

                                    <x-dropdown-link :href="route('astro.show')">
                                        Ma fiche astro
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
                                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[color:var(--fam-border)] bg-[color:var(--fam-surface)] text-[color:var(--fam-text)] hover:bg-[color:rgba(14,165,160,0.10)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25"
                                aria-label="Retour"
                                onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('dashboard') }}'; }"
                            >
                                <i class="ph ph-caret-left" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endunless

                    <div class="shrink-0 flex items-center">
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2" aria-label="Accueil">
                            <span class="inline-flex items-center px-0 py-0.5">
                                <img
                                    src="{{ asset('images/icon-192.png') }}?v=3"
                                    srcset="{{ asset('images/icon-192.png') }}?v=3 192w, {{ asset('images/icon-512.png') }}?v=3 512w"
                                    sizes="48px"
                                    alt="Famille"
                                    class="h-12 w-12 object-contain"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </span>
                        </a>
                    </div>

                    <div class="hidden sm:-my-px sm:ms-10 sm:flex items-center gap-8">
                        <x-nav-link :href="route('dashboard')" :active="$isHome">
                            Accueil
                        </x-nav-link>

                        <x-nav-link :href="route('family.index')" :active="request()->routeIs('family.*')">
                            Famille
                        </x-nav-link>

                        <x-nav-link :href="route('media.index')" :active="$isMedia">
                            Médias
                        </x-nav-link>

                        <x-nav-link :href="route('chat.index')" :active="$isChat">
                            Chat
                        </x-nav-link>

                        <div class="flex items-center gap-8 ms-10">
                            <x-nav-link :href="route('tarot.index')" :active="request()->routeIs('tarot.*')">
                                <span class="inline-flex items-center gap-2">
                                    <img
                                        src="{{ $tarotIconUrl }}"
                                        alt=""
                                        class="h-5 w-5 object-contain drop-shadow-sm"
                                        aria-hidden="true"
                                        onerror="this.onerror=null;this.src='{{ asset('images/crystal.png') }}';"
                                    />
                                    Tarot
                                    @if($hasTarotDraft)
                                        <span class="ms-2 inline-block h-2 w-2 rounded-full bg-[color:var(--fam-primary)]" aria-hidden="true"></span>
                                    @endif
                                </span>
                            </x-nav-link>

                            <x-nav-link :href="route('actu.index')" :active="request()->routeIs('actu.*')">
                                <span class="inline-flex items-center">
                                    Actu locale
                                    @if($hasNewActu)
                                        <span class="ms-2 inline-block h-2 w-2 rounded-full bg-[color:var(--fam-primary)]" aria-hidden="true"></span>
                                    @endif
                                </span>
                            </x-nav-link>
                        </div>
                    </div>
                </div>

                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="ui-chip px-3 py-2 text-sm font-medium">
                                <div>{{ Auth::user()->name }}</div>

                                <div class="ms-1">
                                    <i class="ph ph-caret-down text-[16px]" aria-hidden="true"></i>
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

                            <x-dropdown-link :href="route('astro.show')">
                                Ma fiche astro
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
@endif
