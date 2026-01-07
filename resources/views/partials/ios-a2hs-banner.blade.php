@php
    // Minimal iOS Add-to-Home-Screen hint banner.
    // Shown only via JS when:
    // - iOS device
    // - NOT already in standalone display-mode
    // - NOT dismissed
@endphp

<div
    id="iosA2hsBanner"
    class="hidden bg-white border-b border-slate-200"
    role="region"
    aria-label="Installer l’application"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-start gap-3">
        <div class="shrink-0 mt-0.5">
            <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center text-sm font-semibold">
                +
            </div>
        </div>

        <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold text-gray-900">Installer sur iPhone</div>
            <div class="text-sm text-slate-600">
                Ouvre dans <span class="font-semibold">Safari</span>, puis <span class="font-semibold">Partager</span> → <span class="font-semibold">Sur l’écran d’accueil</span>.
            </div>
        </div>

        <div class="shrink-0">
            <button
                type="button"
                id="iosA2hsDismiss"
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900"
            >
                OK
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        const banner = document.getElementById('iosA2hsBanner');
        const dismiss = document.getElementById('iosA2hsDismiss');
        if (!banner || !dismiss) return;

        const storageKey = 'famille_a2hs_ios_dismissed_v1';

        function isIos() {
            return /iphone|ipad|ipod/i.test(navigator.userAgent || '');
        }

        function isStandalone() {
            // iOS Safari exposes navigator.standalone when launched from home screen.
            // Other browsers may support display-mode media query.
            const navStandalone = typeof window.navigator.standalone === 'boolean' ? window.navigator.standalone : false;
            const mqStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
            return Boolean(navStandalone || mqStandalone);
        }

        function isDismissed() {
            try {
                return window.localStorage.getItem(storageKey) === '1';
            } catch (_) {
                return false;
            }
        }

        function setDismissed() {
            try {
                window.localStorage.setItem(storageKey, '1');
            } catch (_) {
                // ignore
            }
        }

        function show() {
            banner.classList.remove('hidden');
        }

        function hide() {
            banner.classList.add('hidden');
        }

        // Only show on iOS *and* only when not installed.
        if (isIos() && !isStandalone() && !isDismissed()) {
            show();
        }

        dismiss.addEventListener('click', () => {
            setDismissed();
            hide();
        });
    })();
</script>
