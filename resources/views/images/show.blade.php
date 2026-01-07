@php
    $selectedUserId = (int) ($selectedUserId ?? 0);
    $returnUrl = trim((string) ($returnUrl ?? ''));

    $backParams = $selectedUserId !== 0 ? ['user' => $selectedUserId] : [];
    $backUrl = $returnUrl !== '' ? $returnUrl : route('images.index', $backParams);

    $viewerParams = $backParams;
    if ($returnUrl !== '') {
        $viewerParams['return'] = $returnUrl;
    }

    $prevUrl = !empty($prevNode)
        ? route('images.open', array_merge(['node' => $prevNode], $viewerParams))
        : '';

    $nextUrl = !empty($nextNode)
        ? route('images.open', array_merge(['node' => $nextNode], $viewerParams))
        : '';
@endphp

<x-app-layout hideNavigation="1" pageBgClass="bg-slate-950">
    <div
        id="image-viewer"
        class="min-h-[100svh] relative"
        data-prev-url="{{ $prevUrl }}"
        data-next-url="{{ $nextUrl }}"
        data-back-url="{{ $backUrl }}"
    >
        <div class="absolute top-4 left-4 right-4 z-10 flex items-center justify-between gap-3">
            <a
                href="{{ $backUrl }}"
                class="inline-flex items-center justify-center min-h-[44px] rounded-xl px-3 text-sm font-semibold bg-slate-900/70 text-white"
                aria-label="Retour"
            >
                ← Retour
            </a>

            <div class="min-w-0 text-right">
                <div class="text-xs text-white/80 truncate">{{ $node->name }}</div>
                <div class="text-[11px] text-white/60">{{ $node->created_at?->format('d/m/Y H:i') }}</div>
            </div>
        </div>

        @if(!empty($prevNode))
            <a
                href="{{ $prevUrl }}"
                class="absolute left-3 top-1/2 -translate-y-1/2 z-10 inline-flex items-center justify-center w-11 h-11 rounded-xl bg-slate-900/70 text-white"
                aria-label="Image précédente"
            >
                ←
            </a>
        @endif

        @if(!empty($nextNode))
            <a
                href="{{ $nextUrl }}"
                class="absolute right-3 top-1/2 -translate-y-1/2 z-10 inline-flex items-center justify-center w-11 h-11 rounded-xl bg-slate-900/70 text-white"
                aria-label="Image suivante"
            >
                →
            </a>
        @endif

        <div class="min-h-[100svh] flex items-center justify-center px-2 py-16">
            <img
                src="{{ route('images.view', $node) }}"
                alt="{{ $node->name }}"
                class="max-h-[100svh] max-w-full object-contain select-none"
                draggable="false"
            />
        </div>

        <script>
            (() => {
                const root = document.getElementById('image-viewer');
                if (!root) return;

                const prevUrl = root.dataset.prevUrl || '';
                const nextUrl = root.dataset.nextUrl || '';
                const backUrl = root.dataset.backUrl || '';

                let startX = 0;
                let startY = 0;
                let active = false;

                const start = (x, y) => {
                    startX = x;
                    startY = y;
                    active = true;
                };

                const end = (x, y) => {
                    if (!active) return;
                    active = false;

                    const dx = x - startX;
                    const dy = y - startY;

                    if (Math.abs(dx) < 60) return;
                    if (Math.abs(dx) < Math.abs(dy)) return;

                    if (dx < 0 && nextUrl) window.location.href = nextUrl;
                    if (dx > 0 && prevUrl) window.location.href = prevUrl;
                };

                // Swipe (PWA/mobile)
                root.addEventListener('pointerdown', (e) => start(e.clientX, e.clientY), { passive: true });
                root.addEventListener('pointerup', (e) => end(e.clientX, e.clientY), { passive: true });
                root.addEventListener('touchstart', (e) => {
                    const t = e.touches && e.touches[0];
                    if (!t) return;
                    start(t.clientX, t.clientY);
                }, { passive: true });
                root.addEventListener('touchend', (e) => {
                    const t = e.changedTouches && e.changedTouches[0];
                    if (!t) return;
                    end(t.clientX, t.clientY);
                }, { passive: true });

                // Desktop keyboard
                window.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft' && prevUrl) window.location.href = prevUrl;
                    if (e.key === 'ArrowRight' && nextUrl) window.location.href = nextUrl;
                    if (e.key === 'Escape' && backUrl) window.location.href = backUrl;
                });
            })();
        </script>
    </div>
</x-app-layout>
