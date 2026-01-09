<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if(!empty(config('services.webpush.public_key')))
            <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
        @endif

        <meta name="theme-color" content="#ffffff">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Famille') }}">
        <meta name="mobile-web-app-capable" content="yes">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" type="image/png" href="{{ asset('images/logo1.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logo1.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" x-data="{ addOpen: false }">
        <div class="min-h-screen {{ $attributes->get('pageBgClass', 'bg-gray-100') }}">
            @unless($attributes->get('hideNavigation'))
                <div class="{{ $attributes->get('navigationClass', '') }}">
                    @include('layouts.navigation')
                </div>
            @endunless

            @include('partials.ios-a2hs-banner')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="@unless($attributes->get('hideNavigation')) pb-24 sm:pb-0 @endunless">
                {{ $slot }}
            </main>

            @unless($attributes->get('hideNavigation'))
                <!-- Global Add FAB (mobile) -->
                <div class="sm:hidden fixed bottom-20 right-4 z-50">
                    <button
                        type="button"
                        @click="addOpen = true"
                        class="inline-flex h-12 w-12 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-900 shadow-sm"
                        aria-haspopup="dialog"
                        aria-label="Ajouter"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true">
                            <path d="M12 5v14" />
                            <path d="M5 12h14" />
                        </svg>
                    </button>
                </div>

                <!-- Add sheet (mobile) -->
                <div class="sm:hidden">
                    <div x-show="addOpen" x-cloak class="fixed inset-0 z-50" aria-modal="true" role="dialog">
                        <button type="button" @click="addOpen = false" class="absolute inset-0 bg-black/30" aria-label="Fermer"></button>

                        <div class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-4 shadow-sm" style="padding-bottom: calc(env(safe-area-inset-bottom) + 1rem)">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-semibold text-gray-900">Ajouter</div>
                                <button type="button" @click="addOpen = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">Fermer</button>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <a href="{{ route('images.create') }}" class="rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">Photo</a>
                                <a href="{{ route('videos.create') }}" class="rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">Vidéo</a>
                                <a href="{{ route('resources.create') }}" class="rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">Document</a>
                                <a href="{{ route('resources.create', ['mode' => 'scan']) }}" class="rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">Scanner</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom navigation (mobile) -->
                <nav class="sm:hidden fixed bottom-0 inset-x-0 z-40 border-t border-gray-200 bg-white" style="padding-bottom: env(safe-area-inset-bottom)">
                    <div class="max-w-7xl mx-auto px-2">
                        <div class="flex h-14">
                            @php $isHome = request()->routeIs('dashboard'); @endphp
                            <a
                                href="{{ route('dashboard') }}"
                                class="flex-1 basis-1/4 min-w-0 w-full flex flex-col items-center justify-center gap-1.5 text-xs font-semibold transition {{ $isHome ? 'text-slate-900' : 'text-slate-600' }}"
                                aria-current="{{ $isHome ? 'page' : 'false' }}"
                            >
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl {{ $isHome ? 'bg-slate-100' : 'bg-transparent' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                        <path d="M3 10.5L12 3l9 7.5" />
                                        <path d="M5 10v10h14V10" />
                                    </svg>
                                </span>
                                <span class="w-full px-1 text-center text-[11px] leading-none truncate whitespace-nowrap">Accueil</span>
                            </a>

                            @php $isMedia = request()->routeIs('media.*') || request()->routeIs('images.*') || request()->routeIs('videos.*'); @endphp
                            <a
                                href="{{ route('media.index') }}"
                                class="flex-1 basis-1/4 min-w-0 w-full flex flex-col items-center justify-center gap-1.5 text-xs font-semibold transition {{ $isMedia ? 'text-slate-900' : 'text-slate-600' }}"
                                aria-current="{{ $isMedia ? 'page' : 'false' }}"
                            >
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl {{ $isMedia ? 'bg-slate-100' : 'bg-transparent' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2" />
                                        <path d="M8 13l2.5-2.5L14 14l2-2 3 3" />
                                        <path d="M8.5 10.5h.01" />
                                    </svg>
                                </span>
                                <span class="w-full px-1 text-center text-[11px] leading-none truncate whitespace-nowrap">Médias</span>
                            </a>

                            @php $isResources = request()->routeIs('resources.*'); @endphp
                            <a
                                href="{{ route('resources.index') }}"
                                class="flex-1 basis-1/4 min-w-0 w-full flex flex-col items-center justify-center gap-1.5 text-xs font-semibold transition {{ $isResources ? 'text-slate-900' : 'text-slate-600' }}"
                                aria-current="{{ $isResources ? 'page' : 'false' }}"
                            >
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl {{ $isResources ? 'bg-slate-100' : 'bg-transparent' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                        <path d="M4 7a2 2 0 012-2h5l2 2h7a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V7z" />
                                    </svg>
                                </span>
                                <span class="w-full px-1 text-center text-[11px] leading-none truncate whitespace-nowrap">Ress.</span>
                            </a>

                            @php $isChat = request()->routeIs('chat.*'); @endphp
                            <a
                                href="{{ route('chat.index') }}"
                                class="flex-1 basis-1/4 min-w-0 w-full flex flex-col items-center justify-center gap-1.5 text-xs font-semibold transition {{ $isChat ? 'text-slate-900' : 'text-slate-600' }}"
                                aria-current="{{ $isChat ? 'page' : 'false' }}"
                            >
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl {{ $isChat ? 'bg-slate-100' : 'bg-transparent' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                        <path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4v8z" />
                                    </svg>
                                </span>
                                <span class="w-full px-1 text-center text-[11px] leading-none truncate whitespace-nowrap">Chat</span>
                            </a>
                        </div>
                    </div>
                </nav>
            @endunless
        </div>
    </body>
</html>
