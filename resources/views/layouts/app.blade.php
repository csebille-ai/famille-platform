<!DOCTYPE html>
@php
    $isProfileRoute = request()->routeIs('profile.*');
    $internalScroll = (bool) ($attributes->get('internalScroll') ?? false);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="background: {{ $isProfileRoute ? '#FAF7F2' : '#F6F2EC' }};">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if(!empty(config('services.webpush.public_key')))
            <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
        @endif

        <meta name="theme-color" content="#0EA5A0">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Famille') }}">
        <meta name="mobile-web-app-capable" content="yes">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}?v=2">
        <link rel="icon" href="{{ asset('favicon.ico') }}?v=2">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}?v=2">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16.png') }}?v=2">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">
        <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}?v=2">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <style>
            [x-cloak] {
                display: none !important;
            }

            /* Defensive: any dialog that declares itself hidden should not eat taps. */
            [role="dialog"][aria-hidden="true"],
            [aria-modal="true"][aria-hidden="true"] {
                pointer-events: none !important;
            }

            #tm-overlay-root {
                position: fixed;
                inset: 0;
                z-index: 9999;
                pointer-events: none;
                display: none;
            }

            html.tm-animating #tm-overlay-root {
                display: block;
            }

            html.tm-animating body {
                visibility: hidden;
            }

            html.tm-animating.tm-reveal body {
                visibility: visible;
            }

            /* During transition, keep viewer controls hidden until settle. */
            html.tm-animating [data-tm-controls] {
                opacity: 0;
            }
        </style>

        <script>
            (function () {
                try {
                    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    var raw = (window.sessionStorage && window.sessionStorage.getItem) ? window.sessionStorage.getItem('famille_tm_pending') : null;
                    if (!raw) return;
                    var st = JSON.parse(raw);
                    if (!st || !st.id || !st.ts) return;
                    if (Date.now() - Number(st.ts) > 6000) return;

                    document.documentElement.classList.add('tm-animating');
                    if (!document.getElementById('tm-overlay-root')) {
                        var root = document.createElement('div');
                        root.id = 'tm-overlay-root';
                        document.documentElement.appendChild(root);
                    }
                } catch (e) {
                    // ignore
                }
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style id="ui-tokens">
            /* Color tokens are centralized in resources/css/app.css (:root --fam-* / --ui-*). */

            /* Mobile header brand: show wordmark only when there's room (never truncate/crop). */
            .mobile-brand-wordmark {
                display: none;
                flex-shrink: 0;
            }

            @media (min-width: 380px) {
                .mobile-brand-wordmark {
                    display: block;
                }
            }

            .ui-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                border-radius: 0.75rem;
                border: 1px solid transparent;
                font-weight: 700;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                line-height: 1.25rem;
                transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease, box-shadow 150ms ease;
                user-select: none;
                -webkit-tap-highlight-color: transparent;
            }

            .ui-btn:focus {
                outline: none;
            }

            .ui-btn:focus-visible {
                box-shadow: 0 0 0 3px rgba(18, 138, 121, 0.22);
            }

            .ui-btn[disabled],
            .ui-btn[aria-disabled="true"] {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .ui-btn--primary {
                background: var(--ui-brand);
                color: #fff;
            }

            .ui-btn--primary:hover {
                background: var(--ui-brand-hover);
            }

            .ui-btn--secondary {
                background: var(--ui-surface);
                border-color: var(--ui-border);
                color: var(--ui-text);
                box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04);
            }

            .ui-btn--secondary:hover {
                background: var(--fam-surface-alt);
            }

            .ui-btn--danger {
                background: var(--ui-danger);
                color: #fff;
            }

            .ui-btn--danger:hover {
                background: var(--ui-danger-hover);
            }

            .ui-label {
                display: block;
                font-weight: 600;
                font-size: 0.875rem;
                color: #334155;
            }

            .ui-input {
                width: 100%;
                border: 1px solid var(--ui-border);
                border-radius: 0.75rem;
                padding: 0.625rem 0.75rem;
                background: var(--ui-surface);
                color: var(--ui-text);
                box-shadow: 0 1px 1px rgba(15, 23, 42, 0.03);
            }

            .ui-input:focus {
                outline: none;
                border-color: rgba(18, 138, 121, 0.9);
                box-shadow: 0 0 0 3px rgba(18, 138, 121, 0.18);
            }

            .ui-input[disabled],
            .ui-input[aria-disabled="true"] {
                opacity: 0.6;
                cursor: not-allowed;
                background: #f8fafc;
            }

            .ui-help {
                font-size: 0.875rem;
                line-height: 1.25rem;
            }

            .ui-help-list {
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .ui-help-list > li + li {
                margin-top: 0.25rem;
            }

            .ui-help--success {
                color: var(--ui-success);
                font-weight: 600;
            }

            .ui-help--error {
                color: var(--ui-danger);
            }

            .ui-badge {
                display: inline-flex;
                align-items: center;
                border-radius: 9999px;
                padding: 0.125rem 0.5rem;
                font-size: 0.75rem;
                font-weight: 700;
                border: 1px solid var(--ui-border);
                background: rgba(255, 255, 255, 0.75);
                color: #334155;
            }

            .ui-badge--brand {
                border-color: color-mix(in srgb, var(--fam-primary-200) 70%, transparent);
                background: var(--fam-primary-100);
                color: var(--ui-brand-hover);
            }

            .ui-badge--warning {
                border-color: rgba(217, 119, 6, 0.30);
                background: rgba(217, 119, 6, 0.10);
                color: #92400e;
            }

            .ui-badge--danger {
                border-color: rgba(220, 38, 38, 0.28);
                background: rgba(220, 38, 38, 0.08);
                color: #991b1b;
            }

            .ui-nav-link {
                display: inline-flex;
                align-items: center;
                padding: 0.25rem 0.25rem;
                border-bottom: 2px solid transparent;
                font-size: 0.875rem;
                font-weight: 600;
                line-height: 1.25rem;
                color: var(--ui-muted);
                text-decoration: none;
                transition: color 150ms ease, border-color 150ms ease, background-color 150ms ease, box-shadow 150ms ease;
            }

            .ui-nav-link:hover {
                color: var(--ui-brand-hover);
                border-bottom-color: color-mix(in srgb, var(--fam-primary-200) 75%, transparent);
            }

            .ui-nav-link--active {
                color: var(--ui-brand);
                border-bottom-color: var(--ui-brand);
            }

            .ui-nav-link:focus {
                outline: none;
            }

            .ui-nav-link:focus-visible {
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--fam-primary-200) 55%, transparent);
                border-radius: 0.5rem;
            }

            .ui-nav-link-mobile {
                display: block;
                width: 100%;
                padding: 0.5rem 1rem;
                border-left: 4px solid transparent;
                font-size: 1rem;
                font-weight: 600;
                color: #475569;
                text-decoration: none;
                transition: color 150ms ease, border-color 150ms ease, background-color 150ms ease, box-shadow 150ms ease;
            }

            .ui-nav-link-mobile:hover {
                color: var(--ui-text);
                background: var(--fam-primary-100);
                border-left-color: color-mix(in srgb, var(--fam-primary-200) 60%, transparent);
            }

            .ui-nav-link-mobile--active {
                color: var(--ui-brand-hover);
                background: var(--fam-primary-100);
                border-left-color: var(--ui-brand);
            }

            .ui-nav-link-mobile:focus {
                outline: none;
            }

            .ui-nav-link-mobile:focus-visible {
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--fam-primary-200) 55%, transparent);
                border-radius: 0.75rem;
            }

            .ui-dropdown-panel {
                padding: 0.25rem;
                background: var(--ui-surface);
            }

            .ui-dropdown-link {
                display: block;
                width: 100%;
                padding: 0.5rem 0.75rem;
                text-align: left;
                font-size: 0.875rem;
                line-height: 1.25rem;
                color: #334155;
                border-radius: 0.5rem;
                text-decoration: none;
                transition: background-color 150ms ease, color 150ms ease;
            }

            .ui-dropdown-link:hover {
                background: var(--fam-primary-100);
                color: var(--ui-brand-hover);
            }

            .ui-dropdown-link:focus {
                outline: none;
            }

            .ui-dropdown-link:focus-visible {
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--fam-primary-200) 55%, transparent);
            }

            .ui-chip {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                border-radius: 9999px;
                border: 1px solid var(--ui-border);
                background: var(--ui-surface);
                color: var(--ui-text);
                box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04);
                transition: background-color 150ms ease, color 150ms ease, box-shadow 150ms ease;
                -webkit-tap-highlight-color: transparent;
            }

            .ui-chip:hover {
                background: var(--fam-surface-alt);
                color: var(--ui-text);
            }

            .ui-chip:focus {
                outline: none;
            }

            .ui-chip:focus-visible {
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--fam-primary-200) 55%, transparent);
            }
        </style>
    </head>
    <body
        class="font-sans antialiased"
        style="min-height: 100dvh; background: #F6F2EC; color: #0F172A;"
        x-data="{ addOpen: false }"
        @open-add.window="addOpen = true"
    >
        <div class="{{ $attributes->get('pageBgClass', '') }}" style="min-height: 100dvh; background: transparent;">
            @unless($attributes->get('hideNavigation'))
                <div class="{{ $attributes->get('navigationClass', '') }}">
                    @include('layouts.navigation', ['pageTitle' => $attributes->get('pageTitle')])
                </div>

                <script>
                    (() => {
                        const apply = () => {
                            const nav = document.getElementById('appTopNav');
                            const h = nav ? Math.ceil(nav.offsetHeight || nav.getBoundingClientRect().height || 0) : 0;
                            document.documentElement.style.setProperty('--app-nav-h', `${h}px`);
                        };

                        const schedule = () => {
                            requestAnimationFrame(() => requestAnimationFrame(apply));
                        };

                        schedule();
                        window.addEventListener('load', schedule, { passive: true });
                        window.addEventListener('resize', schedule, { passive: true });

                        if (window.visualViewport) {
                            window.visualViewport.addEventListener('resize', schedule, { passive: true });
                            window.visualViewport.addEventListener('scroll', schedule, { passive: true });
                        }

                        if (window.ResizeObserver) {
                            const nav = document.getElementById('appTopNav');
                            if (nav) {
                                const ro = new ResizeObserver(schedule);
                                ro.observe(nav);
                            }
                        }
                    })();
                </script>
            @endunless

            <!-- Page Content -->
            <main
                class="{{ $isProfileRoute ? 'bg-[color:var(--fam-surface-alt)]' : '' }} @unless($attributes->get('hideNavigation')) {{ $internalScroll ? 'pb-0 sm:pb-0' : 'pb-[calc(5.25rem+env(safe-area-inset-bottom))] sm:pb-8' }} @endunless"
                style="@unless($attributes->get('hideNavigation')) padding-top: var(--app-nav-h, 0px) @endunless; {{ $internalScroll ? 'height: calc(100dvh - var(--app-nav-h, 0px)); overflow: hidden;' : '' }}"
            >
                <!-- Page Heading (must be below fixed top nav) -->
                @isset($header)
                    <header class="bg-[color:var(--fam-surface)] border-b border-[color:var(--fam-border)]">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                {{ $slot }}
            </main>

            @isset($afterMain)
                {{ $afterMain }}
            @endisset

            @unless($attributes->get('hideNavigation'))
                @isset($bottomDock)
                    <!-- Mobile: single bottom dock (composer + nav) -->
                    <div id="mobileBottomDock" class="sm:hidden fixed inset-x-0 bottom-0 z-40">
                        <div class="bg-white/95 supports-[backdrop-filter]:bg-white/80 supports-[backdrop-filter]:backdrop-blur-xl border-t border-black/10 shadow-[0_-10px_25px_rgba(0,0,0,0.10)]">
                            {{ $bottomDock }}
                        </div>
                        <x-mobile-primary-nav :fixed="false" />
                    </div>

                    <script>
                        (() => {
                            const apply = () => {
                                const dock = document.getElementById('mobileBottomDock');
                                const h = dock ? Math.ceil(dock.offsetHeight || dock.getBoundingClientRect().height || 0) : 0;
                                document.documentElement.style.setProperty('--mobile-bottom-dock-h', `${h}px`);
                            };

                            const schedule = () => {
                                requestAnimationFrame(() => requestAnimationFrame(apply));
                            };

                            schedule();
                            window.addEventListener('load', schedule, { passive: true });
                            window.addEventListener('resize', schedule, { passive: true });

                            if (window.visualViewport) {
                                window.visualViewport.addEventListener('resize', schedule, { passive: true });
                                window.visualViewport.addEventListener('scroll', schedule, { passive: true });
                            }

                            if (window.ResizeObserver) {
                                const dock = document.getElementById('mobileBottomDock');
                                if (dock) {
                                    const ro = new ResizeObserver(schedule);
                                    ro.observe(dock);
                                }
                            }
                        })();
                    </script>
                @else
                    <!-- Mobile: single primary navigation (bottom) -->
                    <x-mobile-primary-nav />
                @endisset
            @endunless

            @unless($attributes->get('hideNavigation'))
                <!-- Add sheet (mobile) -->
                <div class="sm:hidden">
                    <div x-show="addOpen" x-cloak :class="addOpen ? 'pointer-events-auto' : 'pointer-events-none'" :aria-hidden="addOpen ? 'false' : 'true'" class="fixed inset-0 z-50 pointer-events-none" aria-modal="true" role="dialog" aria-hidden="true">
                        <button type="button" @click="addOpen = false" class="absolute inset-0 bg-black/30" aria-label="Fermer"></button>

                        <div class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-4 shadow-sm" style="padding-bottom: calc(env(safe-area-inset-bottom) + 1rem)">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-semibold text-gray-900">Ajouter</div>
                                <button type="button" @click="addOpen = false" class="text-sm font-medium text-gray-600 hover:text-gray-900">Fermer</button>
                            </div>

                            <div class="mt-4">
                                <button type="button" @click="addOpen = false; window.openGlobalUploadPicker && window.openGlobalUploadPicker()" class="w-full rounded-xl border border-gray-200 px-3 py-3 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">Uploader</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endunless

            @can('cloud-write')
                <form id="global-cloud-upload-form" method="POST" action="{{ route('cloud.files.store') }}" enctype="multipart/form-data" class="hidden">
                    @csrf
                    <input type="hidden" name="parent_id" value="" />
                    <input type="hidden" name="return" value="{{ request()->getRequestUri() }}" />
                    <input id="global-cloud-upload-input" name="file" type="file" accept="image/*,video/*,application/pdf" />
                </form>

                <div id="global-cloud-upload-overlay" class="fixed inset-0 z-[60] hidden pointer-events-none" aria-modal="true" role="dialog">
                    <div class="absolute inset-0 bg-black/40"></div>
                    <div class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-4 shadow-sm sm:inset-0 sm:m-auto sm:h-auto sm:max-w-md sm:rounded-2xl">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold text-gray-900">Upload…</div>
                            <button type="button" id="global-cloud-upload-cancel" class="text-sm font-medium text-gray-600 hover:text-gray-900">Fermer</button>
                        </div>

                        <div class="mt-3">
                            <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
                                <div id="global-cloud-upload-bar" class="h-2 rounded-full bg-slate-900" style="width: 0%"></div>
                            </div>
                            <div id="global-cloud-upload-text" class="mt-2 text-sm text-gray-700">Préparation…</div>
                            <div id="global-cloud-upload-hint" class="microcopy mt-1 text-xs text-gray-500">Un fichier de 300–400MB peut prendre un moment selon la connexion.</div>
                        </div>
                    </div>
                </div>

                <script>
                    window.openGlobalUploadPicker = function (opts) {
                        opts = opts || {};
                        const input = document.getElementById('global-cloud-upload-input');
                        const form = document.getElementById('global-cloud-upload-form');

                        if (form) {
                            const returnInput = form.querySelector('input[name="return"]');

                            const fallbackReturn = window.location.pathname + window.location.search + window.location.hash;
                            const nextReturn = (typeof opts.return === 'string' && opts.return.trim()) ? opts.return.trim() : fallbackReturn;
                            if (returnInput) returnInput.value = nextReturn;
                        }

                        if (input) input.click();
                    };

                    (function () {
                        const input = document.getElementById('global-cloud-upload-input');
                        if (!input) return;

                        const form = document.getElementById('global-cloud-upload-form');
                        const overlay = document.getElementById('global-cloud-upload-overlay');
                        const bar = document.getElementById('global-cloud-upload-bar');
                        const text = document.getElementById('global-cloud-upload-text');
                        const cancelBtn = document.getElementById('global-cloud-upload-cancel');

                        let currentXhr = null;
                        let aborted = false;

                        const LS_KEY = 'cloudUploadSession:v1';

                        const loadSession = () => {
                            try {
                                const raw = window.localStorage ? localStorage.getItem(LS_KEY) : null;
                                if (!raw) return null;
                                const j = JSON.parse(raw);
                                if (!j || typeof j !== 'object') return null;
                                return j;
                            } catch (e) {
                                return null;
                            }
                        };

                        const saveSession = (data) => {
                            try {
                                if (!window.localStorage) return;
                                localStorage.setItem(LS_KEY, JSON.stringify(data));
                            } catch (e) {}
                        };

                        const clearSession = () => {
                            try {
                                if (!window.localStorage) return;
                                localStorage.removeItem(LS_KEY);
                            } catch (e) {}
                        };

                        const showOverlay = () => {
                            if (!overlay) return;
                            overlay.classList.remove('hidden');
                            overlay.classList.remove('pointer-events-none');
                        };

                        const hideOverlay = () => {
                            if (!overlay) return;
                            overlay.classList.add('hidden');
                            overlay.classList.add('pointer-events-none');
                        };

                        const setProgress = (pct, label) => {
                            if (bar) bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
                            if (text && typeof label === 'string') text.textContent = label;
                        };

                        const formatBytes = (bytes) => {
                            if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
                            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                            let v = bytes;
                            let i = 0;
                            while (v >= 1024 && i < units.length - 1) {
                                v /= 1024;
                                i++;
                            }
                            const n = v >= 10 ? v.toFixed(0) : v.toFixed(1);
                            return n + ' ' + units[i];
                        };

                        const withTimeout = (promise, ms, label) => {
                            let t = null;
                            const timeout = new Promise((_, reject) => {
                                t = window.setTimeout(() => reject(new Error(label || 'Timeout')), ms);
                            });
                            return Promise.race([promise, timeout]).finally(() => {
                                if (t) window.clearTimeout(t);
                            });
                        };

                        const generatePosterBlobFromVideoFile = async (file) => {
                            if (!file || !file.type || !String(file.type).startsWith('video/')) return null;

                            const url = URL.createObjectURL(file);
                            try {
                                const video = document.createElement('video');
                                video.muted = true;
                                video.playsInline = true;
                                video.preload = 'metadata';
                                video.src = url;

                                await withTimeout(new Promise((resolve, reject) => {
                                    const onLoaded = () => resolve();
                                    const onError = () => reject(new Error('Video unreadable'));
                                    video.addEventListener('loadedmetadata', onLoaded, { once: true });
                                    video.addEventListener('error', onError, { once: true });
                                }), 12000, 'Metadata timeout');

                                const targetTime = Math.min(1, Math.max(0, (Number.isFinite(video.duration) ? video.duration : 1) / 10));
                                try { video.currentTime = targetTime; } catch (e) { video.currentTime = 0; }

                                await withTimeout(new Promise((resolve, reject) => {
                                    const onSeeked = () => resolve();
                                    const onError = () => reject(new Error('Seek failed'));
                                    video.addEventListener('seeked', onSeeked, { once: true });
                                    video.addEventListener('error', onError, { once: true });
                                }), 12000, 'Seek timeout');

                                const w = Math.max(1, video.videoWidth || 0);
                                const h = Math.max(1, video.videoHeight || 0);
                                if (w <= 1 || h <= 1) return null;

                                const canvas = document.createElement('canvas');

                                // Cap size to keep uploads light.
                                const maxW = 960;
                                const scale = Math.min(1, maxW / w);
                                canvas.width = Math.max(1, Math.round(w * scale));
                                canvas.height = Math.max(1, Math.round(h * scale));

                                const ctx = canvas.getContext('2d');
                                if (!ctx) return null;
                                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                                const blob = await withTimeout(new Promise((resolve) => {
                                    canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.82);
                                }), 8000, 'Poster encode timeout');

                                return blob || null;
                            } finally {
                                try { URL.revokeObjectURL(url); } catch (e) {}
                            }
                        };

                        const uploadPoster = async (videoId, blob, token) => {
                            if (!videoId || !blob) return;

                            const fd = new FormData();
                            if (token) fd.append('_token', token);
                            fd.append('poster', blob, 'poster.jpg');

                            const res = await fetch(`/videos/${encodeURIComponent(String(videoId))}/poster`, {
                                method: 'POST',
                                body: fd,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                                credentials: 'same-origin',
                            });

                            // Best-effort: ignore failures.
                            if (!res.ok) return;
                            try { await res.json(); } catch (e) {}
                        };

                        const maybeGenerateAndUploadPoster = async (file, videoId, token) => {
                            if (aborted) return;
                            if (!file || !file.type || !String(file.type).startsWith('video/')) return;
                            if (!videoId) return;

                            const blob = await generatePosterBlobFromVideoFile(file);
                            if (!blob) return;

                            if (aborted) return;
                            await withTimeout(uploadPoster(videoId, blob, token), 15000, 'Poster upload timeout');
                        };

                        if (cancelBtn) {
                            cancelBtn.addEventListener('click', function () {
                                aborted = true;
                                if (currentXhr) {
                                    try { currentXhr.abort(); } catch (e) {}
                                    currentXhr = null;
                                }
                                hideOverlay();
                            });
                        }

                        const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

                        const csrfToken = () => {
                            const meta = document.querySelector('meta[name="csrf-token"]');
                            return meta ? String(meta.getAttribute('content') || '') : '';
                        };

                        const uploadsApi = {
                            presignUrl: @json(url('/api/uploads/presign')),
                            mpInitUrl: @json(url('/api/uploads/multipart/init')),
                            mpCompleteUrl: @json(url('/api/uploads/multipart/complete')),
                            finalizeUrl: @json(url('/api/uploads/finalize')),
                            maxUploadBytes: @json((int) config('uploads.max_upload_bytes')),
                            multipartThresholdBytes: @json((int) config('uploads.multipart_threshold_bytes')),
                            multipartPartSizeBytes: @json((int) config('uploads.multipart_part_size_bytes')),
                        };

                        const postJson = async (url, payload) => {
                            const token = csrfToken();
                            const res = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify(payload || {}),
                            });
                            let json = null;
                            try { json = await res.json(); } catch (e) { json = null; }
                            if (!res.ok) {
                                const err = new Error((json && json.message) ? String(json.message) : `HTTP ${res.status}`);
                                err.status = res.status;
                                err.json = json;
                                throw err;
                            }
                            return json || {};
                        };

                        const postForm = (url, formData) => new Promise((resolve, reject) => {
                            const xhr = new XMLHttpRequest();
                            currentXhr = xhr;

                            xhr.open('POST', url, true);
                            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                            xhr.setRequestHeader('Accept', 'application/json');

                            xhr.onload = function () {
                                let json = null;
                                try { json = xhr.responseText ? JSON.parse(xhr.responseText) : null; } catch (e) { json = null; }
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    resolve({ xhr, json: json || {} });
                                    return;
                                }
                                const message = (json && json.message) ? String(json.message) : 'Erreur.';
                                const error = new Error(message);
                                error.status = xhr.status;
                                error.json = json;
                                reject(error);
                            };

                            xhr.onerror = function () {
                                const error = new Error('Erreur réseau pendant l\'upload.');
                                error.status = 0;
                                error.json = null;
                                reject(error);
                            };

                            xhr.onabort = function () {
                                const error = new Error('Annulé.');
                                error.status = 0;
                                error.json = null;
                                reject(error);
                            };

                            try {
                                xhr.send(formData);
                            } catch (e) {
                                const error = new Error('Impossible de démarrer l\'upload.');
                                error.status = 0;
                                error.json = null;
                                reject(error);
                            }
                        });

                        const putWithProgress = (url, blobOrFile, contentType, onProgress) => new Promise((resolve, reject) => {
                            const xhr = new XMLHttpRequest();
                            xhr.open('PUT', url, true);
                            if (contentType) xhr.setRequestHeader('Content-Type', contentType);

                            xhr.upload.onprogress = (evt) => {
                                if (!evt.lengthComputable) return;
                                if (typeof onProgress === 'function') onProgress(evt.loaded, evt.total);
                            };

                            xhr.onload = () => {
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    resolve({
                                        status: xhr.status,
                                        etag: xhr.getResponseHeader('ETag') || xhr.getResponseHeader('etag') || null,
                                    });
                                } else {
                                    const error = new Error(`Upload failed (${xhr.status})`);
                                    error.status = xhr.status;
                                    reject(error);
                                }
                            };

                            xhr.onerror = () => {
                                const error = new Error('network_error');
                                error.status = 0;
                                reject(error);
                            };

                            xhr.onabort = () => {
                                const error = new Error('aborted');
                                error.status = 0;
                                reject(error);
                            };

                            xhr.send(blobOrFile);
                        });

                        const isRetryableStatus = (status) => {
                            return status === 0 || status === 408 || status === 425 || status === 429 || status === 500 || status === 502 || status === 503 || status === 504;
                        };

                        const postWithRetry = async (labelForUi, attemptFn, maxRetries = 4) => {
                            let attempt = 0;
                            while (true) {
                                if (aborted) throw new Error('Annulé.');
                                try {
                                    return await attemptFn();
                                } catch (err) {
                                    const status = Number(err && err.status ? err.status : 0) || 0;
                                    const retryable = isRetryableStatus(status);
                                    if (!retryable || attempt >= maxRetries) throw err;
                                    attempt++;
                                    const backoff = Math.round(400 * Math.pow(2, attempt - 1) + (Math.random() * 250));
                                    setProgress(Math.max(0, (bar && bar.style && bar.style.width) ? parseInt(bar.style.width, 10) || 0 : 0), `${labelForUi} (réseau) — reprise… (${attempt}/${maxRetries})`);
                                    await sleep(backoff);
                                }
                            }
                        };

                        const postFormData = postForm;

                        const chunkedUpload = async (file) => {
                            const token = (form.querySelector('input[name="_token"]') || {}).value;
                            const parentId = (form.querySelector('input[name="parent_id"]') || {}).value;
                            const returnPath = (form.querySelector('input[name="return"]') || {}).value;

                            // Attempt resume if a previous session exists for the same file+destination.
                            const previous = loadSession();
                            const canResume = previous
                                && previous.uploadId
                                && String(previous.name) === String(file.name)
                                && Number(previous.size) === Number(file.size)
                                && Number(previous.lastModified) === Number(file.lastModified)
                                && String(previous.parentId || '') === String(parentId || '');

                            const initFd = new FormData();
                            if (token) initFd.append('_token', token);
                            if (canResume) initFd.append('upload_id', String(previous.uploadId));
                            initFd.append('name', file.name);
                            initFd.append('size', String(file.size));
                            initFd.append('mime', file.type || '');
                            if (parentId) initFd.append('parent_id', parentId);
                            if (returnPath) initFd.append('return', returnPath);

                            setProgress(0, 'Préparation…');
                            const { json: initJson } = await postWithRetry('Préparation', () => postFormData('{{ route('cloud.uploads.init') }}', initFd));
                            const uploadId = initJson.upload_id;
                            const chunkSize = Number(initJson.chunk_size || 0) || (5 * 1024 * 1024);
                            const totalChunks = Number(initJson.total_chunks || 0) || Math.max(1, Math.ceil(file.size / chunkSize));
                            const received = Array.isArray(initJson.received) ? new Set(initJson.received) : new Set();

                            saveSession({
                                uploadId,
                                name: file.name,
                                size: file.size,
                                lastModified: file.lastModified,
                                parentId: parentId || '',
                                startedAt: Date.now(),
                            });

                            if (initJson && initJson.resumed === true && received.size > 0) {
                                setProgress(0, `Reprise… (${received.size}/${totalChunks} morceaux déjà envoyés)`);
                            }

                            let uploadedBytes = 0;
                            for (let i = 0; i < totalChunks; i++) {
                                const start = i * chunkSize;
                                const end = Math.min(file.size, start + chunkSize);
                                if (received.has(i)) {
                                    uploadedBytes = end;
                                    continue;
                                }

                                const blob = file.slice(start, end);
                                const fd = new FormData();
                                if (token) fd.append('_token', token);
                                fd.append('upload_id', uploadId);
                                fd.append('index', String(i));
                                fd.append('chunk', blob, file.name + '.part' + i);

                                const pct = Math.round((uploadedBytes / file.size) * 100);
                                setProgress(pct, `Upload… ${pct}% (${formatBytes(uploadedBytes)} / ${formatBytes(file.size)})`);

                                await postWithRetry('Upload', () => postFormData('{{ route('cloud.uploads.chunk') }}', fd));

                                uploadedBytes = end;
                                const pct2 = Math.round((uploadedBytes / file.size) * 100);
                                setProgress(pct2, `Upload… ${pct2}% (${formatBytes(uploadedBytes)} / ${formatBytes(file.size)})`);
                            }

                            const completeFd = new FormData();
                            if (token) completeFd.append('_token', token);
                            completeFd.append('upload_id', uploadId);
                            setProgress(100, 'Finalisation…');
                            const { json: completeJson } = await postWithRetry('Finalisation', () => postFormData('{{ route('cloud.uploads.complete') }}', completeFd));

                            const videoId = completeJson && completeJson.video_id ? completeJson.video_id : null;
                            if (videoId && file && file.type && String(file.type).startsWith('video/')) {
                                try {
                                    setProgress(100, 'Création du poster…');
                                    await maybeGenerateAndUploadPoster(file, videoId, token);
                                } catch (e) {
                                    // best-effort
                                }
                            }

                            const redirectUrl = completeJson.redirect_url || null;
                            clearSession();
                            if (redirectUrl) {
                                window.location.href = redirectUrl;
                                return;
                            }
                            window.location.reload();
                        };

                        const r2Upload = async (file) => {
                            const size = Number(file && file.size ? file.size : 0) || 0;
                            const mime = String(file && file.type ? file.type : 'application/octet-stream');
                            const kind = mime.startsWith('video/') ? 'video' : 'photo';

                            if (uploadsApi.maxUploadBytes && size > uploadsApi.maxUploadBytes) {
                                throw new Error('Fichier trop volumineux (max 2 Go).');
                            }

                            setProgress(0, 'Préparation (R2)…');
                            let presign = null;
                            try {
                                presign = await postJson(uploadsApi.presignUrl, {
                                    filename: file.name || 'file',
                                    mime,
                                    size,
                                    kind,
                                    context: 'media',
                                });
                            } catch (e) {
                                presign = null;
                            }

                            // If backend returns local, keep legacy chunked upload.
                            const storageDisk = presign && presign.storage_disk ? String(presign.storage_disk) : '';
                            if (storageDisk !== 'r2') {
                                throw new Error('R2 non disponible.');
                            }

                            if (size > Number(uploadsApi.multipartThresholdBytes || 0)) {
                                const init = await postJson(uploadsApi.mpInitUrl, {
                                    filename: file.name || 'file',
                                    mime,
                                    size,
                                    kind,
                                    context: 'media',
                                });

                                const uploadId = String(init.upload_id || '');
                                const key = String(init.key || '');
                                const partSize = Number(init.part_size || uploadsApi.multipartPartSizeBytes || (50 * 1024 * 1024));
                                const parts = Array.isArray(init.parts) ? init.parts : [];
                                if (!uploadId || !key || !parts.length || !partSize) {
                                    throw new Error('Multipart init invalide.');
                                }

                                const totalParts = parts.length;
                                const perPartBytes = parts.map((_, idx) => {
                                    const start = idx * partSize;
                                    const end = Math.min(size, start + partSize);
                                    return Math.max(0, end - start);
                                });

                                const loadedArr = parts.map(() => 0);
                                const etags = [];

                                const updateOverall = () => {
                                    const loaded = loadedArr.reduce((a, b) => a + (Number(b) || 0), 0);
                                    const pct = size > 0 ? Math.round((loaded / size) * 100) : 0;
                                    setProgress(pct, `Upload (R2)… ${pct}% (${formatBytes(loaded)} / ${formatBytes(size)})`);
                                };

                                const uploadPartAt = async (idx) => {
                                    const p = parts[idx] || {};
                                    const uploadUrl = String(p.upload_url || '');
                                    const partNumber = Number(p.part_number || 0);
                                    if (!uploadUrl || !partNumber) throw new Error('Part invalide.');

                                    const start = (partNumber - 1) * partSize;
                                    const end = Math.min(size, start + partSize);
                                    const blob = file.slice(start, end);
                                    const partBytes = perPartBytes[idx] || (end - start);

                                    let attempt = 0;
                                    while (true) {
                                        try {
                                            const res = await putWithProgress(uploadUrl, blob, mime, (loaded) => {
                                                loadedArr[idx] = Math.max(0, Math.min(partBytes, Number(loaded || 0)));
                                                updateOverall();
                                            });
                                            const etag = String(res && res.etag ? res.etag : '').trim();
                                            if (!etag) throw new Error('ETag manquant (R2).');
                                            loadedArr[idx] = partBytes;
                                            updateOverall();
                                            etags.push({ part_number: partNumber, etag });
                                            return;
                                        } catch (e) {
                                            attempt++;
                                            if (attempt >= 3) throw e;
                                            await sleep(750 * attempt);
                                        }
                                    }
                                };

                                const concurrency = 3;
                                let next = 0;
                                const workers = Array.from({ length: concurrency }, () => (async () => {
                                    while (true) {
                                        const idx = next;
                                        next++;
                                        if (idx >= totalParts) return;
                                        await uploadPartAt(idx);
                                    }
                                })());
                                await Promise.all(workers);

                                etags.sort((a, b) => Number(a.part_number) - Number(b.part_number));
                                const complete = await postJson(uploadsApi.mpCompleteUrl, {
                                    key,
                                    upload_id: uploadId,
                                    parts: etags,
                                    mime,
                                    size,
                                    kind,
                                    context: 'media',
                                });
                                const finPublicUrl = complete && complete.public_url ? String(complete.public_url) : (init.public_url ? String(init.public_url) : null);

                                setProgress(100, 'Finalisation…');
                                if (kind === 'video') {
                                    const posterBlob = await generatePosterBlobFromVideoFile(file);
                                    const fd = new FormData();
                                    fd.append('key', key);
                                    if (finPublicUrl) fd.append('public_url', finPublicUrl);
                                    fd.append('mime', mime);
                                    fd.append('size', String(size));
                                    fd.append('kind', kind);
                                    fd.append('context', 'media');
                                    fd.append('storage_disk', 'r2');
                                    if (file.name) fd.append('filename', String(file.name));
                                    if (posterBlob) fd.append('poster_file', posterBlob, 'poster.jpg');
                                    const token = csrfToken();
                                    if (token) fd.append('_token', token);
                                    const { json: finalized } = await postForm(uploadsApi.finalizeUrl, fd);
                                    return finalized || {};
                                }

                                return await postJson(uploadsApi.finalizeUrl, {
                                    key,
                                    public_url: finPublicUrl,
                                    mime,
                                    size,
                                    kind,
                                    context: 'media',
                                    storage_disk: 'r2',
                                    filename: file.name || null,
                                });
                            }

                            // Single PUT
                            const uploadUrl = String(presign.upload_url || '');
                            const key = String(presign.key || '');
                            const publicUrl = presign.public_url ? String(presign.public_url) : null;
                            if (!uploadUrl || !key) throw new Error('Presign invalide.');

                            setProgress(0, 'Upload (R2)…');
                            await putWithProgress(uploadUrl, file, mime, (loaded, total) => {
                                const t = Number(total || size) || size || 1;
                                const pct = Math.max(0, Math.min(100, Math.round((Number(loaded || 0) / t) * 100)));
                                setProgress(pct, `Upload (R2)… ${pct}% (${formatBytes(loaded)} / ${formatBytes(t)})`);
                            });

                            setProgress(100, 'Finalisation…');
                            if (kind === 'video') {
                                const posterBlob = await generatePosterBlobFromVideoFile(file);
                                const fd = new FormData();
                                fd.append('key', key);
                                if (publicUrl) fd.append('public_url', publicUrl);
                                fd.append('mime', mime);
                                fd.append('size', String(size));
                                fd.append('kind', kind);
                                fd.append('context', 'media');
                                fd.append('storage_disk', 'r2');
                                if (file.name) fd.append('filename', String(file.name));
                                const token = csrfToken();
                                if (token) fd.append('_token', token);
                                if (posterBlob) fd.append('poster_file', posterBlob, 'poster.jpg');
                                const { json: finalized } = await postForm(uploadsApi.finalizeUrl, fd);
                                return finalized || {};
                            }

                            return await postJson(uploadsApi.finalizeUrl, {
                                key,
                                public_url: publicUrl,
                                mime,
                                size,
                                kind,
                                context: 'media',
                                storage_disk: 'r2',
                                filename: file.name || null,
                            });
                        };

                        const directUpload = async (file) => {
                            const fd = new FormData(form);
                            showOverlay();
                            setProgress(0, 'Démarrage…');

                            const returnPath = (form.querySelector('input[name="return"]') || {}).value || null;

                            // Replace file in the formdata with current selection.
                            fd.set('file', file, file.name);

                            const xhr = new XMLHttpRequest();
                            currentXhr = xhr;

                            xhr.open('POST', form.action, true);
                            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                            xhr.upload.onprogress = function (e) {
                                if (!e.lengthComputable) {
                                    setProgress(5, 'Upload…');
                                    return;
                                }
                                const pct = Math.round((e.loaded / e.total) * 100);
                                setProgress(pct, `Upload… ${pct}% (${formatBytes(e.loaded)} / ${formatBytes(e.total)})`);
                            };

                            xhr.onload = function () {
                                setProgress(100, 'Finalisation…');

                                // For XHR uploads, Laravel often returns JSON (expectsJson=true).
                                // Never navigate to xhr.responseURL (it can be a POST-only endpoint like /cloud/files => 405 on GET).
                                let json = null;
                                try {
                                    json = xhr.responseText ? JSON.parse(xhr.responseText) : null;
                                } catch (e) {
                                    json = null;
                                }

                                const redirectUrl = (json && json.redirect_url) ? String(json.redirect_url) : null;
                                const videoId = (json && json.video_id) ? json.video_id : null;
                                const token = (form.querySelector('input[name="_token"]') || {}).value;

                                const finishNav = () => {
                                    if (redirectUrl) {
                                        window.location.href = redirectUrl;
                                        return;
                                    }
                                    if (returnPath) {
                                        window.location.href = returnPath;
                                        return;
                                    }
                                    window.location.reload();
                                };

                                // Safety net: if navigation is blocked (mobile/PWA quirks), don't leave the user stuck.
                                const ensureNotStuck = () => {
                                    try {
                                        if (overlay && !overlay.classList.contains('hidden')) {
                                            window.location.reload();
                                        }
                                    } catch (e) {
                                        try { window.location.reload(); } catch (e2) {}
                                    }
                                };
                                window.setTimeout(ensureNotStuck, 1500);

                                try {
                                    if (videoId && file && file.type && String(file.type).startsWith('video/')) {
                                        setProgress(100, 'Création du poster…');
                                        Promise.resolve(maybeGenerateAndUploadPoster(file, videoId, token))
                                            .catch(() => {})
                                            .finally(finishNav);
                                        return;
                                    }

                                    finishNav();
                                } catch (e) {
                                    // Last resort: never keep overlay forever.
                                    ensureNotStuck();
                                }
                            };

                            xhr.onerror = function () {
                                setProgress(0, 'Erreur réseau pendant l\'upload.');
                                currentXhr = null;
                            };

                            xhr.onabort = function () {
                                setProgress(0, 'Annulé.');
                                currentXhr = null;
                            };

                            try {
                                xhr.send(fd);
                            } catch (e) {
                                setProgress(0, 'Impossible de démarrer l\'upload.');
                                currentXhr = null;
                            }
                        };

                        input.addEventListener('change', function () {
                            if (!input.files || input.files.length === 0) return;
                            if (!form) return;

                            const file = input.files[0];
                            aborted = false;
                            showOverlay();
                            setProgress(0, `Préparation… (${file.name})`);

                            // Use chunked mode for large files (o2switch-friendly).
                            const chunkThreshold = 25 * 1024 * 1024; // 25MB
                            const useChunked = file.size >= chunkThreshold;

                            const mime = String(file.type || 'application/octet-stream');
                            const isImageOrVideo = mime.startsWith('image/') || mime.startsWith('video/');
                            const isPdf = mime === 'application/pdf' || (file.name || '').toLowerCase().endsWith('.pdf');

                            const run = async () => {
                                if (isPdf || !isImageOrVideo) {
                                    return await (useChunked ? chunkedUpload(file) : directUpload(file));
                                }

                                // Prefer R2 (direct browser->R2 upload) to avoid shared-hosting limits/timeouts.
                                try {
                                    const finalized = await r2Upload(file);
                                    const redirectUrl = finalized && finalized.open_url ? String(finalized.open_url) : null;
                                    const returnPath = (form.querySelector('input[name="return"]') || {}).value || null;
                                    if (redirectUrl) {
                                        window.location.href = redirectUrl;
                                        return;
                                    }
                                    if (returnPath) {
                                        window.location.href = returnPath;
                                        return;
                                    }
                                    window.location.reload();
                                    return;
                                } catch (e) {
                                    // If R2 is not available, fall back to existing chunked/local upload.
                                    const msg = (e && e.message) ? String(e.message) : '';
                                    if (msg && msg.includes('R2 non disponible')) {
                                        return await (useChunked ? chunkedUpload(file) : directUpload(file));
                                    }

                                    // Provide a helpful hint for the most common case: CORS blocks on R2.
                                    if (msg === 'network_error') {
                                        throw new Error('Erreur réseau vers R2. Vérifie la config CORS du bucket (PUT + Content-Type + ETag) et R2_* dans .env.');
                                    }
                                    throw e;
                                }
                            };

                            run()
                                .catch((err) => {
                                    const msg = (err && err.message) ? err.message : 'Erreur upload.';
                                    setProgress(0, msg);
                                    currentXhr = null;
                                });
                        });
                    })();
                </script>
            @endcan
        </div>
    </body>
</html>
