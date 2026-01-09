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
    <body class="font-sans antialiased">
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
            <main class="@unless($attributes->get('hideNavigation')) pb-20 sm:pb-0 @endunless">
                {{ $slot }}
            </main>

            @unless($attributes->get('hideNavigation'))
                <nav class="sm:hidden fixed bottom-0 inset-x-0 z-50 bg-white border-t border-gray-200">
                    <div class="max-w-7xl mx-auto px-2">
                        <div class="grid grid-cols-5 h-16">
                            <a href="{{ route('dashboard') }}"
                               class="flex flex-col items-center justify-center text-xs font-semibold {{ request()->routeIs('dashboard') ? 'text-gray-900' : 'text-gray-500' }}">
                                <span class="leading-none">Accueil</span>
                                <span class="mt-1 h-0.5 w-6 rounded-full {{ request()->routeIs('dashboard') ? 'bg-gray-900' : 'bg-transparent' }}"></span>
                            </a>

                            <a href="{{ route('chat.index') }}"
                               class="flex flex-col items-center justify-center text-xs font-semibold {{ request()->routeIs('chat.*') ? 'text-gray-900' : 'text-gray-500' }}">
                                <span class="leading-none">Chat</span>
                                <span class="mt-1 h-0.5 w-6 rounded-full {{ request()->routeIs('chat.*') ? 'bg-gray-900' : 'bg-transparent' }}"></span>
                            </a>

                            <a href="{{ route('resources.index') }}"
                               class="flex flex-col items-center justify-center text-xs font-semibold {{ request()->routeIs('resources.*') ? 'text-gray-900' : 'text-gray-500' }}">
                                <span class="leading-none">Docs</span>
                                <span class="mt-1 h-0.5 w-6 rounded-full {{ request()->routeIs('resources.*') ? 'bg-gray-900' : 'bg-transparent' }}"></span>
                            </a>

                            <a href="{{ route('images.index') }}"
                               class="flex flex-col items-center justify-center text-xs font-semibold {{ request()->routeIs('images.*') ? 'text-gray-900' : 'text-gray-500' }}">
                                <span class="leading-none">Photos</span>
                                <span class="mt-1 h-0.5 w-6 rounded-full {{ request()->routeIs('images.*') ? 'bg-gray-900' : 'bg-transparent' }}"></span>
                            </a>

                            <a href="{{ route('videos.index') }}"
                               class="flex flex-col items-center justify-center text-xs font-semibold {{ request()->routeIs('videos.*') ? 'text-gray-900' : 'text-gray-500' }}">
                                <span class="leading-none">Vidéos</span>
                                <span class="mt-1 h-0.5 w-6 rounded-full {{ request()->routeIs('videos.*') ? 'bg-gray-900' : 'bg-transparent' }}"></span>
                            </a>
                        </div>
                    </div>
                </nav>
            @endunless
        </div>
    </body>
</html>
