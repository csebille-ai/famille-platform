@php
    $selectedUserId = (int) ($selectedUserId ?? 0);
    $returnUrl = trim((string) ($returnUrl ?? ''));

    $backParams = $selectedUserId !== 0 ? ['user' => $selectedUserId] : [];
    $backUrl = $returnUrl !== '' ? $returnUrl : route('media.index', ['tab' => 'photos']);

    $viewerParams = $backParams;
    if ($returnUrl !== '') {
        $viewerParams['return'] = $returnUrl;
    }

    $prevUrl = !empty($prevNode)
        ? route('media.photos.show', array_merge(['node' => $prevNode], $viewerParams))
        : '';

    $nextUrl = !empty($nextNode)
        ? route('media.photos.show', array_merge(['node' => $nextNode], $viewerParams))
        : '';

    $rawName = (string) ($node->name ?? '');
    $baseName = $rawName;
    try {
        $baseName = pathinfo($rawName, PATHINFO_FILENAME);
    } catch (Throwable $e) {
        // ignore
    }
    // Remove common noisy suffixes (timestamps, long numeric ids, hashes).
    $displayTitle = preg_replace('/([_-])(\d{8}(?:[_-]\d{6})?|\d{10,}|[a-f0-9]{8,})$/i', '', $baseName);
    $displayTitle = trim((string) ($displayTitle ?? ''));
    if ($displayTitle === '') {
        $displayTitle = $baseName !== '' ? $baseName : 'Photo';
    }
@endphp

<x-app-layout hideNavigation="1" pageBgClass="bg-slate-950">
    <div
        id="image-viewer"
        class="min-h-[100svh] relative"
        data-prev-url="{{ $prevUrl }}"
        data-next-url="{{ $nextUrl }}"
        data-back-url="{{ $backUrl }}"
    >
        <style>
            /* Viewer UI is an overlay: it must never reflow the image. */
            #image-viewer {
                --viewer-header-h: 0px;
            }

            #image-viewer.viewer-ui-hidden [data-viewer-ui] {
                opacity: 0;
                pointer-events: none;
            }

            #image-viewer [data-viewer-ui] {
                opacity: 1;
                pointer-events: auto;
                transition: opacity 180ms ease;
            }
        </style>

        <div
            id="image-viewer-header"
            data-viewer-ui
            data-tm-controls
            class="absolute left-4 right-4 z-10 flex items-start justify-between gap-3"
            style="top: calc(env(safe-area-inset-top) + 1rem)"
        >
            <a
                href="{{ $backUrl }}"
                class="inline-flex items-center justify-center min-h-[44px] rounded-xl px-3 text-sm font-semibold bg-slate-900/70 text-white"
                aria-label="Retour"
                data-tm-back="1"
            >
                ← Retour
            </a>

            <div class="min-w-0 flex-1 text-right">
                <div class="flex items-start justify-end gap-2">
                    <div class="min-w-0">
                        <div class="text-sm text-white/90 font-semibold truncate">{{ $displayTitle }}</div>
                        <div class="text-[11px] text-white/60">
                            {{ $node->uploader?->name ?? 'Quelqu\’un' }}
                            <span class="text-white/40">·</span>
                            {{ $node->created_at?->diffForHumans() }}
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            id="image-viewer-details-btn"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-900/70 text-white"
                            aria-label="Détails"
                        >
                            …
                        </button>

                        <div
                            id="image-viewer-details"
                            class="hidden absolute right-0 mt-2 w-[min(320px,calc(100vw-2rem))] rounded-2xl bg-slate-900/90 text-white shadow-2xl ring-1 ring-white/10 overflow-hidden"
                            style="backdrop-filter: blur(10px)"
                        >
                            <div class="p-3 text-left">
                                <div class="text-xs text-white/60">Fichier</div>
                                <div class="text-sm font-semibold break-all">{{ $node->name }}</div>

                                <div class="mt-3 text-xs text-white/60">Infos</div>
                                <div class="text-sm">
                                    {{ $node->uploader?->name ?? 'Quelqu\’un' }}
                                    <span class="text-white/40">·</span>
                                    {{ $node->created_at?->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            id="image-viewer-stage"
            class="absolute inset-0 flex items-center justify-center"
            style="padding: calc(env(safe-area-inset-top) + var(--viewer-header-h, 0px) + 0.75rem) 0.5rem calc(env(safe-area-inset-bottom) + 0.75rem) 0.5rem"
        >
            <img
                src="{{ route('images.view', $node) }}"
                alt="{{ $node->name }}"
                class="max-w-full object-contain select-none"
                style="max-height: calc(100svh - env(safe-area-inset-top) - env(safe-area-inset-bottom) - var(--viewer-header-h, 0px) - 1.5rem)"
                draggable="false"
                data-shared-id="media:{{ (int) $node->id }}"
            />
        </div>

        <script>
            (() => {
                const root = document.getElementById('image-viewer');
                if (!root) return;

                const prevUrl = root.dataset.prevUrl || '';
                const nextUrl = root.dataset.nextUrl || '';
                const backUrl = root.dataset.backUrl || '';

                const header = document.getElementById('image-viewer-header');
                const detailsBtn = document.getElementById('image-viewer-details-btn');
                const detailsPanel = document.getElementById('image-viewer-details');
                const backLink = root.querySelector('a[data-tm-back="1"]');

                const setHeaderVisible = (visible) => {
                    const show = !!visible;
                    root.classList.toggle('viewer-ui-hidden', !show);

                    const h = header ? Math.ceil(header.getBoundingClientRect().height || 0) : 0;
                    root.style.setProperty('--viewer-header-h', show ? `${h}px` : '0px');
                };

                // On load: show 1s then hide.
                setHeaderVisible(true);
                setTimeout(() => setHeaderVisible(false), 1000);

                // Details menu
                const closeDetails = () => {
                    if (detailsPanel) detailsPanel.classList.add('hidden');
                };
                const toggleDetails = () => {
                    if (!detailsPanel) return;
                    const isOpen = !detailsPanel.classList.contains('hidden');
                    if (isOpen) detailsPanel.classList.add('hidden');
                    else detailsPanel.classList.remove('hidden');
                };
                if (detailsBtn) {
                    detailsBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleDetails();
                    });
                }
                document.addEventListener('click', (e) => {
                    if (!detailsPanel || detailsPanel.classList.contains('hidden')) return;
                    if (detailsPanel.contains(e.target) || (detailsBtn && detailsBtn.contains(e.target))) return;
                    closeDetails();
                }, { capture: true });

                let startX = 0;
                let startY = 0;
                let active = false;
                let moved = false;

                const start = (x, y) => {
                    startX = x;
                    startY = y;
                    moved = false;
                    active = true;
                };

                const end = (x, y) => {
                    if (!active) return;
                    active = false;

                    const dx = x - startX;
                    const dy = y - startY;

                    // Tap toggles UI (ignore if a details menu is open).
                    if (Math.abs(dx) < 10 && Math.abs(dy) < 10 && !moved) {
                        if (detailsPanel && !detailsPanel.classList.contains('hidden')) return;
                        setHeaderVisible(root.classList.contains('viewer-ui-hidden'));
                        return;
                    }

                    // Swipe down = close.
                    if (dy > 90 && Math.abs(dy) > Math.abs(dx)) {
                        closeDetails();
                        if (backLink) backLink.click();
                        else if (backUrl) window.location.href = backUrl;
                        return;
                    }

                    // Swipe left/right = prev/next.
                    if (Math.abs(dx) >= 60 && Math.abs(dx) > Math.abs(dy)) {
                        closeDetails();
                        if (dx < 0 && nextUrl) window.location.href = nextUrl;
                        if (dx > 0 && prevUrl) window.location.href = prevUrl;
                    }
                };

                const onPointerDown = (e) => {
                    // Don't start gesture on UI controls.
                    if (e && e.target && e.target.closest && e.target.closest('[data-viewer-ui]')) return;
                    start(e.clientX, e.clientY);
                };
                const onPointerMove = (e) => {
                    if (!active) return;
                    const dx = e.clientX - startX;
                    const dy = e.clientY - startY;
                    if (Math.abs(dx) > 4 || Math.abs(dy) > 4) moved = true;
                };
                const onPointerUp = (e) => end(e.clientX, e.clientY);

                // Prefer Pointer Events to avoid double-firing on iOS.
                if (window.PointerEvent) {
                    root.addEventListener('pointerdown', onPointerDown, { passive: true });
                    root.addEventListener('pointermove', onPointerMove, { passive: true });
                    root.addEventListener('pointerup', onPointerUp, { passive: true });
                } else {
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
                }

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
