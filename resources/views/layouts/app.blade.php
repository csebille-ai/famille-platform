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
                <div class="sm:hidden fixed bottom-20 left-1/2 -translate-x-1/2 z-50">
                    <button
                        type="button"
                        @click="addOpen = true"
                        class="inline-flex items-center justify-center rounded-full border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-900 shadow-sm"
                        aria-haspopup="dialog"
                        aria-label="Ajouter"
                    >
                        + Ajouter
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

                            <div class="mt-4 grid grid-cols-3 gap-2">
                                <a href="{{ route('images.create') }}" class="rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">Photo</a>
                                <a href="{{ route('videos.create') }}" class="rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">Vidéo</a>
                                <a href="{{ route('resources.create') }}" class="rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">Doc</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom navigation (mobile) -->
                <nav class="sm:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200" style="padding-bottom: env(safe-area-inset-bottom)">
                    <div class="max-w-7xl mx-auto px-2">
                        <div class="grid grid-cols-4 h-16">
                            <a href="{{ route('dashboard') }}"
                               class="flex flex-col items-center justify-center text-xs font-semibold {{ request()->routeIs('dashboard') ? 'text-gray-900' : 'text-gray-500' }}">
                                <span class="leading-none">Accueil</span>
                                <span class="mt-1 h-0.5 w-6 rounded-full {{ request()->routeIs('dashboard') ? 'bg-gray-900' : 'bg-transparent' }}"></span>
                            </a>

                            <a href="{{ route('resources.index') }}"
                               class="flex flex-col items-center justify-center text-xs font-semibold {{ request()->routeIs('resources.*') ? 'text-gray-900' : 'text-gray-500' }}">
                                <span class="leading-none">Ressources</span>
                                <span class="mt-1 h-0.5 w-6 rounded-full {{ request()->routeIs('resources.*') ? 'bg-gray-900' : 'bg-transparent' }}"></span>
                            </a>

                            <a href="{{ route('media.index') }}"
                               class="flex flex-col items-center justify-center text-xs font-semibold {{ request()->routeIs('media.*') || request()->routeIs('images.*') || request()->routeIs('videos.*') ? 'text-gray-900' : 'text-gray-500' }}">
                                <span class="leading-none">Médias</span>
                                <span class="mt-1 h-0.5 w-6 rounded-full {{ request()->routeIs('media.*') || request()->routeIs('images.*') || request()->routeIs('videos.*') ? 'bg-gray-900' : 'bg-transparent' }}"></span>
                            </a>

                            <a href="{{ route('chat.index') }}"
                               class="flex flex-col items-center justify-center text-xs font-semibold {{ request()->routeIs('chat.*') ? 'text-gray-900' : 'text-gray-500' }}">
                                <span class="leading-none">Chat</span>
                                <span class="mt-1 h-0.5 w-6 rounded-full {{ request()->routeIs('chat.*') ? 'bg-gray-900' : 'bg-transparent' }}"></span>
                            </a>
                        </div>
                    </div>
                </nav>
            @endunless
        </div>
    </body>
</html>
