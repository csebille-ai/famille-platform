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
            #image-viewer.viewer-ui-hidden [data-viewer-ui] {
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity 180ms ease, visibility 0s linear 180ms;
            }

            #image-viewer [data-viewer-ui] {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transition: opacity 180ms ease, visibility 0s linear 0s;

                /* Keep the UI overlay on its own composited layer (reduces the chance the image gets promoted and shows tiling seams). */
                transform: translateZ(0);
                will-change: opacity;
            }

            /* Isolate stacking/compositing for the image area. */
            #image-viewer-stage {
                isolation: isolate;
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
                            class="hidden absolute right-0 mt-2 w-[min(320px,calc(100vw-2rem))] rounded-2xl bg-slate-900/95 text-white shadow-2xl ring-1 ring-white/10 overflow-hidden"
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
            style="padding: calc(env(safe-area-inset-top) + 0.75rem) 0.5rem calc(env(safe-area-inset-bottom) + 0.75rem) 0.5rem"
        >
            <img
                id="image-viewer-img"
                src="{{ route('images.view', $node) }}"
                alt="{{ $node->name }}"
                class="max-w-full object-contain select-none"
                style="max-height: calc(100svh - env(safe-area-inset-top) - env(safe-area-inset-bottom) - 1.5rem)"
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
                const stage = document.getElementById('image-viewer-stage');
                const img = document.getElementById('image-viewer-img') || root.querySelector('img[data-shared-id]');

                if (stage) {
                    try {
                        stage.style.touchAction = 'none';
                        stage.style.overscrollBehavior = 'contain';
                    } catch {}
                }
                if (img) {
                    try {
                        img.style.touchAction = 'none';
                        img.style.transformOrigin = 'center center';
                    } catch {}
                }

                const setHeaderVisible = (visible) => {
                    const show = !!visible;
                    root.classList.toggle('viewer-ui-hidden', !show);
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

                // --- True zoom (pinch + pan + double tap) ---
                const zoom = {
                    scale: 1,
                    tx: 0,
                    ty: 0,
                    min: 1,
                    max: 3.25,
                };

                const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
                const dpr = (() => {
                    try {
                        const v = Number(window.devicePixelRatio || 1);
                        return Number.isFinite(v) && v > 0 ? v : 1;
                    } catch {
                        return 1;
                    }
                })();
                const snapToDevicePx = (v) => {
                    const n = Number(v || 0);
                    if (!Number.isFinite(n)) return 0;
                    return Math.round(n * dpr) / dpr;
                };

                const getStageRect = () => {
                    const r = stage ? stage.getBoundingClientRect() : root.getBoundingClientRect();
                    return { x: r.left, y: r.top, w: r.width, h: r.height };
                };

                const getContainBaseSize = () => {
                    const sr = getStageRect();
                    const sw = Math.max(1, sr.w);
                    const sh = Math.max(1, sr.h);

                    let ar = 1;
                    try {
                        const nw = Number(img?.naturalWidth || 0);
                        const nh = Number(img?.naturalHeight || 0);
                        if (nw > 0 && nh > 0) ar = nw / nh;
                    } catch {}
                    if (!Number.isFinite(ar) || ar <= 0.05) ar = 1;

                    let w = sw;
                    let h = w / ar;
                    if (h > sh) {
                        h = sh;
                        w = h * ar;
                    }
                    return { w, h, stageW: sw, stageH: sh };
                };

                const clampPan = () => {
                    const base = getContainBaseSize();
                    const scaledW = base.w * zoom.scale;
                    const scaledH = base.h * zoom.scale;
                    const maxX = Math.max(0, (scaledW - base.stageW) / 2);
                    const maxY = Math.max(0, (scaledH - base.stageH) / 2);
                    zoom.tx = clamp(zoom.tx, -maxX, maxX);
                    zoom.ty = clamp(zoom.ty, -maxY, maxY);
                };

                const applyTransform = () => {
                    if (!img) return;
                    if (zoom.scale <= 1.001) {
                        zoom.scale = 1;
                        zoom.tx = 0;
                        zoom.ty = 0;
                        img.style.transform = 'none';
                        try { img.style.willChange = ''; } catch {}
                        return;
                    } else {
                        clampPan();
                        // Snap to device pixels to reduce GPU tiling seams / grid artifacts.
                        zoom.tx = snapToDevicePx(zoom.tx);
                        zoom.ty = snapToDevicePx(zoom.ty);
                        clampPan();
                    }
                    // CSS transforms apply right-to-left; using translate() scale() means pan isn't scaled.
                    img.style.transform = `translate3d(${zoom.tx}px, ${zoom.ty}px, 0) scale(${zoom.scale})`;
                    try { img.style.willChange = 'transform'; } catch {}
                };

                const setScaleAroundPoint = (newScale, focal) => {
                    const sr = getStageRect();
                    const O = { x: sr.x + sr.w / 2, y: sr.y + sr.h / 2 };
                    const F = { x: Number(focal?.x || O.x), y: Number(focal?.y || O.y) };

                    const oldScale = Math.max(zoom.min, zoom.scale);
                    const target = clamp(Number(newScale || 1), zoom.min, zoom.max);
                    if (Math.abs(target - oldScale) < 0.001) return;

                    // Adjust pan so the focal point stays visually anchored.
                    const ratio = target / oldScale;
                    zoom.tx = zoom.tx + (1 - ratio) * (F.x - (O.x + zoom.tx));
                    zoom.ty = zoom.ty + (1 - ratio) * (F.y - (O.y + zoom.ty));
                    zoom.scale = target;
                    applyTransform();
                };

                const resetZoom = () => {
                    zoom.scale = 1;
                    zoom.tx = 0;
                    zoom.ty = 0;
                    applyTransform();
                };

                // --- Gestures ---
                // Track pointers with start + current position so we can detect swipes correctly.
                const pointers = new Map();
                let panPointerId = null;
                let lastPanX = 0;
                let lastPanY = 0;
                let lastPinchDist = 0;

                let tapTimer = 0;
                let lastTapAt = 0;
                let lastTapX = 0;
                let lastTapY = 0;

                const clearTapTimer = () => {
                    if (tapTimer) {
                        clearTimeout(tapTimer);
                        tapTimer = 0;
                    }
                };

                const isDoubleTap = (x, y) => {
                    const t = Date.now();
                    const dt = t - lastTapAt;
                    const dx = x - lastTapX;
                    const dy = y - lastTapY;
                    return dt > 0 && dt < 280 && (dx * dx + dy * dy) < (28 * 28);
                };

                const onTap = (x, y) => {
                    if (detailsPanel && !detailsPanel.classList.contains('hidden')) return;
                    clearTapTimer();

                    if (isDoubleTap(x, y)) {
                        // Double tap => true zoom toggle.
                        lastTapAt = 0;
                        lastTapX = 0;
                        lastTapY = 0;

                        if (zoom.scale > 1.01) {
                            resetZoom();
                        } else {
                            setScaleAroundPoint(2.5, { x, y });
                            setHeaderVisible(false);
                        }
                        return;
                    }

                    // Single tap is delayed slightly so we can detect double tap without flashing UI.
                    lastTapAt = Date.now();
                    lastTapX = x;
                    lastTapY = y;
                    tapTimer = setTimeout(() => {
                        tapTimer = 0;
                        setHeaderVisible(root.classList.contains('viewer-ui-hidden'));
                    }, 240);
                };

                const dist = (a, b) => {
                    const dx = a.x - b.x;
                    const dy = a.y - b.y;
                    return Math.sqrt(dx * dx + dy * dy);
                };

                const midpoint = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });

                const startPinchIfReady = () => {
                    if (pointers.size !== 2) return;
                    const pts = Array.from(pointers.values());
                    lastPinchDist = dist(pts[0], pts[1]);
                    panPointerId = null;
                };

                const onPointerDown = (e) => {
                    if (!e) return;
                    if (e.target && e.target.closest && e.target.closest('[data-viewer-ui]')) return;
                    if (!img) return;
                    clearTapTimer();

                    pointers.set(e.pointerId, { sx: e.clientX, sy: e.clientY, x: e.clientX, y: e.clientY });

                    if (pointers.size === 1) {
                        panPointerId = e.pointerId;
                        lastPanX = e.clientX;
                        lastPanY = e.clientY;
                    } else if (pointers.size === 2) {
                        startPinchIfReady();
                    }

                    // Capture on the stage so we keep receiving moves even when finger leaves the image.
                    try { stage?.setPointerCapture && stage.setPointerCapture(e.pointerId); } catch {}
                };

                const onPointerMove = (e) => {
                    if (!e) return;
                    if (!pointers.has(e.pointerId)) return;

                    const prev = pointers.get(e.pointerId);
                    pointers.set(e.pointerId, { sx: prev?.sx ?? e.clientX, sy: prev?.sy ?? e.clientY, x: e.clientX, y: e.clientY });

                    // Pinch zoom
                    if (pointers.size === 2) {
                        const pts = Array.from(pointers.values());
                        const d = dist(pts[0], pts[1]);
                        if (lastPinchDist > 0) {
                            const factor = d / lastPinchDist;
                            const target = clamp(zoom.scale * factor, zoom.min, zoom.max);
                            const mid = midpoint(pts[0], pts[1]);
                            setScaleAroundPoint(target, mid);
                        }
                        lastPinchDist = d;
                        return;
                    }

                    // Pan when zoomed
                    if (zoom.scale > 1.01 && panPointerId === e.pointerId) {
                        const dx = e.clientX - lastPanX;
                        const dy = e.clientY - lastPanY;
                        lastPanX = e.clientX;
                        lastPanY = e.clientY;
                        zoom.tx += dx;
                        zoom.ty += dy;
                        applyTransform();
                    }
                };

                const onPointerUp = (e) => {
                    if (!e) return;
                    if (!pointers.has(e.pointerId)) return;

                    const start = pointers.get(e.pointerId);
                    const endPt = { x: e.clientX, y: e.clientY };

                    pointers.delete(e.pointerId);
                    if (panPointerId === e.pointerId) panPointerId = null;
                    if (pointers.size < 2) lastPinchDist = 0;

                    const dx = endPt.x - Number(start?.sx ?? endPt.x);
                    const dy = endPt.y - Number(start?.sy ?? endPt.y);

                    // If zoomed, we treat gestures as pan/zoom only (no slide navigation).
                    if (zoom.scale > 1.01) {
                        if (Math.abs(dx) < 10 && Math.abs(dy) < 10) onTap(endPt.x, endPt.y);
                        return;
                    }

                    // Tap / Swipe behaviors when not zoomed.
                    if (Math.abs(dx) < 10 && Math.abs(dy) < 10) {
                        onTap(endPt.x, endPt.y);
                        return;
                    }

                    closeDetails();

                    // Swipe down = close.
                    if (dy > 90 && Math.abs(dy) > Math.abs(dx)) {
                        // Close should be immediate; don't toggle UI.
                        clearTapTimer();
                        if (backLink) backLink.click();
                        else if (backUrl) window.location.href = backUrl;
                        return;
                    }

                    // Swipe left/right = prev/next.
                    if (Math.abs(dx) >= 60 && Math.abs(dx) > Math.abs(dy)) {
                        if (dx < 0 && nextUrl) window.location.href = nextUrl;
                        if (dx > 0 && prevUrl) window.location.href = prevUrl;
                    }
                };

                const onPointerCancel = (e) => {
                    if (!e) return;
                    pointers.delete(e.pointerId);
                    if (pointers.size < 2) lastPinchDist = 0;
                    if (panPointerId === e.pointerId) panPointerId = null;
                };

                // Pointer Events (pinch/pan/tap/swipe)
                // Bind on the full stage (covers letterboxing) but ignore interactions starting on the UI overlay.
                if (window.PointerEvent && stage) {
                    stage.addEventListener('pointerdown', onPointerDown, { passive: true });
                    stage.addEventListener('pointermove', onPointerMove, { passive: true });
                    stage.addEventListener('pointerup', onPointerUp, { passive: true });
                    stage.addEventListener('pointercancel', onPointerCancel, { passive: true });
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
