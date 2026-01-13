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

        <style id="ui-tokens">
            :root {
                --ui-bg: #F6F7F9;
                --ui-surface: #ffffff;
                --ui-text: #0f172a;
                --ui-muted: #64748b;
                --ui-border: #e2e8f0;
                --ui-brand: #4f46e5;
                --ui-brand-hover: #4338ca;
                --ui-danger: #dc2626;
                --ui-danger-hover: #b91c1c;
                --ui-success: #16a34a;
                --ui-warning: #d97706;
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
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25);
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
                color: #334155;
                box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04);
            }

            .ui-btn--secondary:hover {
                background: #f8fafc;
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
                border-color: rgba(79, 70, 229, 0.9);
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
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
                border-color: rgba(79, 70, 229, 0.25);
                background: rgba(79, 70, 229, 0.08);
                color: #3730a3;
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
                border-bottom-color: rgba(79, 70, 229, 0.35);
            }

            .ui-nav-link--active {
                color: var(--ui-text);
                border-bottom-color: var(--ui-brand);
            }

            .ui-nav-link:focus {
                outline: none;
            }

            .ui-nav-link:focus-visible {
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
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
                color: #1f2937;
                background: #f8fafc;
                border-left-color: #cbd5e1;
            }

            .ui-nav-link-mobile--active {
                color: #3730a3;
                background: rgba(79, 70, 229, 0.08);
                border-left-color: rgba(79, 70, 229, 0.55);
            }

            .ui-nav-link-mobile:focus {
                outline: none;
            }

            .ui-nav-link-mobile:focus-visible {
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
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
                background: rgba(79, 70, 229, 0.08);
                color: #3730a3;
            }

            .ui-dropdown-link:focus {
                outline: none;
            }

            .ui-dropdown-link:focus-visible {
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
            }

            .ui-chip {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                border-radius: 9999px;
                border: 1px solid var(--ui-border);
                background: var(--ui-surface);
                color: #334155;
                box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04);
                transition: background-color 150ms ease, color 150ms ease, box-shadow 150ms ease;
                -webkit-tap-highlight-color: transparent;
            }

            .ui-chip:hover {
                background: #f8fafc;
                color: var(--ui-text);
            }

            .ui-chip:focus {
                outline: none;
            }

            .ui-chip:focus-visible {
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
            }
        </style>
    </head>
    <body
        class="font-sans antialiased"
        x-data="{ addOpen: false }"
        @open-add.window="addOpen = true"
    >
        <div class="min-h-screen {{ $attributes->get('pageBgClass', 'bg-[#F6F7F9]') }}">
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
            <main class="@unless($attributes->get('hideNavigation')) pb-8 @endunless">
                {{ $slot }}
            </main>

            @unless($attributes->get('hideNavigation'))
                <!-- Add sheet (mobile) -->
                <div class="sm:hidden">
                    <div x-show="addOpen" x-cloak class="fixed inset-0 z-50" aria-modal="true" role="dialog">
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

                <div id="global-cloud-upload-overlay" class="fixed inset-0 z-[60] hidden" aria-modal="true" role="dialog">
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
                    window.openGlobalUploadPicker = function () {
                        const input = document.getElementById('global-cloud-upload-input');
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

                        const showOverlay = () => {
                            if (!overlay) return;
                            overlay.classList.remove('hidden');
                        };

                        const hideOverlay = () => {
                            if (!overlay) return;
                            overlay.classList.add('hidden');
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

                        if (cancelBtn) {
                            cancelBtn.addEventListener('click', function () {
                                if (currentXhr) {
                                    try { currentXhr.abort(); } catch (e) {}
                                    currentXhr = null;
                                }
                                hideOverlay();
                            });
                        }

                        input.addEventListener('change', function () {
                            if (!input.files || input.files.length === 0) return;
                            if (!form) return;

                            const fd = new FormData(form);
                            const file = input.files[0];

                            showOverlay();
                            setProgress(0, 'Démarrage…');

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
                                const finalUrl = xhr.responseURL || null;
                                setProgress(100, 'Finalisation…');

                                // Ensure we re-navigate so session flashes/errors display properly.
                                if (finalUrl) {
                                    window.location.href = finalUrl;
                                    return;
                                }

                                // Fallback: reload.
                                window.location.reload();
                            };

                            xhr.onerror = function () {
                                setProgress(0, 'Erreur réseau pendant l\'upload.');
                                currentXhr = null;
                            };

                            xhr.onabort = function () {
                                setProgress(0, 'Annulé.');
                                currentXhr = null;
                            };

                            // Send
                            try {
                                xhr.send(fd);
                            } catch (e) {
                                setProgress(0, 'Impossible de démarrer l\'upload.');
                                currentXhr = null;
                            }
                        });
                    })();
                </script>
            @endcan
        </div>
    </body>
</html>
