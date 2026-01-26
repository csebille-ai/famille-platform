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

    $canDelete = false;
    try {
        $canDelete = auth()->check() && Illuminate\Support\Facades\Gate::allows('images-delete');
    } catch (Throwable $e) {
        $canDelete = false;
    }

    $diskName = (string) ($node->storage_disk ?? 'local');
    $hdUrl = route('images.view', $node);
    if ($diskName !== 'local') {
        $publicUrl = trim((string) ($node->public_url ?? ''));
        if ($publicUrl !== '') {
            $hdUrl = $publicUrl;
        }
    }

    $thumbW = 480;
    $thumbUrl = route('images.thumb', $node) . '?' . http_build_query(['w' => $thumbW]);
@endphp

<x-app-layout hideNavigation="1" pageBgClass="bg-slate-950">
    <div
        id="image-viewer"
        class="min-h-[100svh] relative overflow-hidden viewer-ui-hidden"
        data-loaded="0"
        data-details="0"
        data-ui-shown="0"
        data-no-bg="1"
        data-bg-loaded="0"
        data-prev-url="{{ $prevUrl }}"
        data-next-url="{{ $nextUrl }}"
        data-back-url="{{ $backUrl }}"
    >
        <style>
            /* Ensure the viewer never inherits the app's light "paper" background. */
            html, body { background: #020617 !important; }

            /* Viewer UI is an overlay: it must never reflow the image. */
            #image-viewer.viewer-ui-hidden [data-viewer-ui] {
                opacity: 0;
                pointer-events: none;
            }

            #image-viewer [data-viewer-ui] {
                opacity: 1;
                pointer-events: auto;
                transition: opacity 180ms ease;
            }

            /* Isolate stacking/compositing for the image area. */
            #image-viewer-stage {
                isolation: isolate;
            }

            /* Details panel: never display:none; animate opacity/transform only. */
            #image-details-panel {
                opacity: 0;
                transform: translate3d(0, -6px, 0);
                pointer-events: none;
                transition: opacity 160ms ease, transform 160ms ease;
                will-change: opacity, transform;
            }
            #image-viewer[data-details="1"] #image-details-panel {
                opacity: 1;
                transform: none;
                pointer-events: auto;
            }

            /* Background blur: keep it cheap and avoid large repaints. */
            #image-viewer-bg {
                filter: blur(12px);
                transform: translate3d(0, 0, 0) scale(1.06);
                will-change: transform, opacity;
                opacity: 0;
                transition: opacity 240ms ease;
            }
            #image-viewer[data-bg-loaded="1"] #image-viewer-bg { opacity: 0.32; }
            #image-viewer[data-no-bg="1"] #image-viewer-bg {
                opacity: 0;
            }

            /* Loading: keep it subtle and avoid white flashes. */
            #image-viewer-loading {
                opacity: 1;
                transition: opacity 220ms ease;
            }
            #image-viewer[data-loaded="1"] #image-viewer-loading {
                opacity: 0;
                pointer-events: none;
            }
            #image-viewer-img {
                opacity: 0;
                transition: opacity 220ms ease;
            }
            #image-viewer[data-loaded="1"] #image-viewer-img {
                opacity: 1;
            }
        </style>

        <a href="{{ $backUrl }}" class="hidden" aria-hidden="true" tabindex="-1" data-tm-back="1">Retour</a>

        <div
            id="image-viewer-header"
            data-viewer-ui
            class="absolute left-0 right-0 top-0 z-20"
            style="padding: calc(env(safe-area-inset-top) + 0.75rem) 0.75rem 0.75rem 0.75rem"
        >
            <div class="flex items-center gap-2">
                <a href="{{ $backUrl }}" class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-900/60 text-white" aria-label="Retour">
                    <span aria-hidden="true">←</span>
                </a>

                <div class="min-w-0 flex-1 text-center text-sm font-semibold text-white/90 truncate px-2">{{ $displayTitle }}</div>

                <button
                    type="button"
                    id="image-details-btn"
                    class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-900/60 text-white"
                    aria-label="Menu"
                >
                    <span aria-hidden="true">⋯</span>
                </button>
            </div>

            <div id="image-details-panel" class="mt-2 w-full max-w-[520px] mx-auto rounded-2xl bg-slate-900/70 ring-1 ring-white/10 p-2">
                <div class="grid gap-1">
                    <a href="{{ route('cloud.files.download', $node) }}" class="w-full min-h-[44px] rounded-2xl bg-white/10 hover:bg-white/15 px-3 inline-flex items-center justify-between text-sm font-semibold text-white">
                        <span>Télécharger</span>
                        <span aria-hidden="true">↓</span>
                    </a>

                    @if($canDelete)
                        <form method="POST" action="{{ route('images.destroy', $node) }}" onsubmit="return confirm('Supprimer cette photo ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full min-h-[44px] rounded-2xl bg-red-600/90 hover:bg-red-600 px-3 inline-flex items-center justify-between text-sm font-semibold text-white">
                                <span>Supprimer</span>
                                <span aria-hidden="true">🗑</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div
            id="image-viewer-footer"
            data-viewer-ui
            aria-hidden="true"
            class="absolute inset-x-0 bottom-0 z-10 pointer-events-none"
            style="padding: 0 1rem calc(env(safe-area-inset-bottom) + 0.75rem) 1rem"
        >
            <div class="pointer-events-none" style="background: linear-gradient(to top, rgba(2,6,23,0.86), rgba(2,6,23,0)); height: 5.5rem; position: absolute; inset: auto 0 0 0;"></div>
            <div class="relative pointer-events-none">
                <div class="text-[12px] text-white/75">
                    {{ $node->uploader?->name ?? 'Quelqu\’un' }}
                    <span class="text-white/35">·</span>
                    {{ $node->created_at?->diffForHumans() }}
                </div>
            </div>
        </div>

        <div
            id="image-viewer-stage"
            class="absolute inset-0"
            style="z-index: 0; padding: env(safe-area-inset-top) 0 env(safe-area-inset-bottom) 0"
        >
            <div class="absolute inset-0">
                <img
                    id="image-viewer-bg"
                    src="{{ $thumbUrl }}"
                    alt=""
                    class="absolute inset-0 w-full h-full object-cover"
                    style="opacity: 0.32;"
                    aria-hidden="true"
                    draggable="false"
                    decoding="async"
                    fetchpriority="low"
                />
                <div class="absolute inset-0" style="background: rgba(2,6,23,0.78);"></div>
            </div>

            <div id="image-viewer-loading" class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <div class="w-[min(92vw,740px)] aspect-[4/3] rounded-2xl" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);"></div>
            </div>

            <div class="absolute inset-0 flex items-center justify-center">
            <img
                id="image-viewer-img"
                src="{{ $hdUrl }}"
                alt="{{ $node->name }}"
                class="w-full h-full max-w-full object-contain select-none"
                draggable="false"
                data-shared-id="media:{{ (int) $node->id }}"
                decoding="async"
                fetchpriority="high"
            />
            </div>
        </div>

        <script>
            (() => {
                const root = document.getElementById('image-viewer');
                if (!root) return;

                const prevUrl = root.dataset.prevUrl || '';
                const nextUrl = root.dataset.nextUrl || '';
                const backUrl = root.dataset.backUrl || '';

                const backLink = root.querySelector('a[data-tm-back="1"]');
                const stage = document.getElementById('image-viewer-stage');
                const img = document.getElementById('image-viewer-img') || root.querySelector('img[data-shared-id]');
                const bg = document.getElementById('image-viewer-bg');
                const detailsBtn = document.getElementById('image-details-btn');
                const detailsPanel = document.getElementById('image-details-panel');
                const fitBtn = null;

                // Background image: off by default (prevents fullscreen photo behind).
                // Enable with ?bg=1. Disable explicitly with ?nobg=1 (or ?noblur=1).
                try {
                    const qs = new URLSearchParams(window.location.search || '');
                    const wantsBg = qs.get('bg') === '1';
                    const disableBg = (qs.get('nobg') === '1' || qs.get('noblur') === '1');
                    root.dataset.noBg = (!wantsBg || disableBg) ? '1' : '0';
                } catch {}

                if (bg) {
                    try {
                        bg.addEventListener('load', () => { try { root.dataset.bgLoaded = '1'; } catch {} }, { once: true });
                        bg.addEventListener('error', () => { try { root.dataset.noBg = '1'; } catch {} }, { once: true });
                        if (bg.complete) {
                            // Some browsers won't fire load for cached images.
                            if ((bg.naturalWidth || 0) > 0) { try { root.dataset.bgLoaded = '1'; } catch {} }
                        }
                    } catch {}
                }

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

                // Mark loaded (main image). Also helps prevent any brief "white" flash.
                const markLoaded = async () => {
                    if (!img) return;
                    try {
                        if (typeof img.decode === 'function') {
                            await img.decode().catch(() => {});
                        }
                    } catch {}

                    try { root.dataset.loaded = '1'; } catch {}

                    // Reveal UI once, after opening animation is done.
                    const revealOnce = () => {
                        if (String(root.dataset.uiShown || '0') === '1') return;

                        const html = document.documentElement;
                        const isOpening = () => {
                            try { return !!(html && html.classList && html.classList.contains('tm-animating')); }
                            catch { return false; }
                        };

                        if (isOpening()) {
                            requestAnimationFrame(revealOnce);
                            return;
                        }

                        try { root.dataset.uiShown = '1'; } catch {}
                        setTimeout(() => setHeaderVisible(true), 180);
                    };

                    requestAnimationFrame(revealOnce);
                };

                if (img) {
                    try {
                        if (img.complete && (img.naturalWidth || 0) > 0) {
                            markLoaded();
                        } else {
                            img.addEventListener('load', () => markLoaded(), { once: true });
                            img.addEventListener('error', () => { try { root.dataset.loaded = '1'; } catch {} }, { once: true });
                        }
                    } catch {}
                }

                // Details menu (must exist before setHeaderVisible(false) runs)
                const closeDetails = () => {
                    try { root.dataset.details = '0'; } catch {}
                };

                const toggleDetails = () => {
                    const isOpen = String(root.dataset.details || '0') === '1';
                    try { root.dataset.details = isOpen ? '0' : '1'; } catch {}
                };

                if (detailsBtn) {
                    detailsBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleDetails();
                    });
                }
                document.addEventListener('click', (e) => {
                    if (String(root.dataset.details || '0') !== '1') return;
                    if (detailsPanel.contains(e.target) || (detailsBtn && detailsBtn.contains(e.target))) return;
                    closeDetails();
                }, { capture: true });

                const setHeaderVisible = (visible) => {
                    const show = !!visible;

                    root.classList.toggle('viewer-ui-hidden', !show);
                    if (!show) closeDetails();
                };

                // On load: keep UI hidden during shared-element OPENING; reveal is gated by markLoaded().
                setHeaderVisible(false);

                // --- True zoom (pinch + pan + double tap) ---
                let fitMode = 'contain';

                const readFitMode = () => {
                    try {
                        const raw = String(window.localStorage.getItem('famille_viewer_fit') || '').trim();
                        return raw === 'cover' ? 'cover' : 'contain';
                    } catch {
                        return 'contain';
                    }
                };

                const writeFitMode = (mode) => {
                    try {
                        window.localStorage.setItem('famille_viewer_fit', mode === 'cover' ? 'cover' : 'contain');
                    } catch {
                        // ignore
                    }
                };

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

                const getBaseSize = () => {
                    const sr = getStageRect();
                    const sw = Math.max(1, sr.w);
                    const sh = Math.max(1, sr.h);

                    let nw = 0;
                    let nh = 0;
                    try {
                        nw = Number(img?.naturalWidth || 0);
                        nh = Number(img?.naturalHeight || 0);
                    } catch {}

                    if (!(nw > 0 && nh > 0)) {
                        // Unknown intrinsic size: fall back to stage.
                        return { w: sw, h: sh, stageW: sw, stageH: sh };
                    }

                    const ar = nw / nh;
                    if (!Number.isFinite(ar) || ar <= 0.05) return { w: sw, h: sh, stageW: sw, stageH: sh };

                    if (fitMode === 'cover') {
                        const s = Math.max(sw / nw, sh / nh);
                        return { w: nw * s, h: nh * s, stageW: sw, stageH: sh };
                    }

                    let w = sw;
                    let h = w / ar;
                    if (h > sh) {
                        h = sh;
                        w = h * ar;
                    }
                    return { w, h, stageW: sw, stageH: sh };
                };

                const recomputeZoomMax = () => {
                    // Goal: allow reaching (at least) 1:1 pixel size of the HD image
                    // when the image is displayed smaller than its natural dimensions.
                    const base = getBaseSize();
                    const baseW = Math.max(1, Number(base?.w || 1));
                    const baseH = Math.max(1, Number(base?.h || 1));

                    let nw = 0;
                    let nh = 0;
                    try {
                        nw = Number(img?.naturalWidth || 0);
                        nh = Number(img?.naturalHeight || 0);
                    } catch {}

                    let targetMax = 3.25;
                    if (nw > 0 && nh > 0) {
                        const oneToOne = Math.max(nw / baseW, nh / baseH);
                        if (Number.isFinite(oneToOne) && oneToOne > 1) targetMax = Math.max(targetMax, oneToOne);
                    }

                    // Safety cap for performance/memory.
                    const cap = 8;
                    zoom.max = clamp(targetMax, 3.25, cap);

                    if (zoom.scale > zoom.max) {
                        zoom.scale = zoom.max;
                        applyTransform();
                    }
                };

                const clampPan = () => {
                    const base = getBaseSize();
                    const scaledW = base.w * zoom.scale;
                    const scaledH = base.h * zoom.scale;
                    const maxX = Math.max(0, (scaledW - base.stageW) / 2);
                    const maxY = Math.max(0, (scaledH - base.stageH) / 2);
                    zoom.tx = clamp(zoom.tx, -maxX, maxX);
                    zoom.ty = clamp(zoom.ty, -maxY, maxY);
                };

                const getMaxPan = () => {
                    const base = getBaseSize();
                    const scaledW = base.w * zoom.scale;
                    const scaledH = base.h * zoom.scale;
                    const maxX = Math.max(0, (scaledW - base.stageW) / 2);
                    const maxY = Math.max(0, (scaledH - base.stageH) / 2);
                    return { maxX, maxY };
                };

                const applyResistance = (value, min, max, k = 0.35) => {
                    const v = Number(value || 0);
                    const a = Number(min || 0);
                    const b = Number(max || 0);
                    const kk = clamp(Number(k || 0.35), 0.12, 0.55);
                    if (v < a) return a + (v - a) * kk;
                    if (v > b) return b + (v - b) * kk;
                    return v;
                };

                let snapTimer = 0;
                const clearSnapTimer = () => {
                    if (snapTimer) {
                        clearTimeout(snapTimer);
                        snapTimer = 0;
                    }
                };

                const snapToCenterIfNear = () => {
                    if (!img) return;
                    if (fitMode !== 'cover') return;
                    if (zoom.scale > 1.01) return;

                    const { maxX, maxY } = getMaxPan();
                    // Only snap if close to center, so panning to inspect edges stays possible.
                    const thresholdX = Math.max(18, Math.min(42, maxX * 0.18));
                    const thresholdY = Math.max(18, Math.min(42, maxY * 0.18));
                    if (Math.abs(zoom.tx) > thresholdX || Math.abs(zoom.ty) > thresholdY) return;

                    clearSnapTimer();
                    try { img.style.transition = 'transform 160ms cubic-bezier(0.2, 0.8, 0.2, 1)'; } catch {}
                    zoom.tx = 0;
                    zoom.ty = 0;
                    applyTransform();
                    snapTimer = setTimeout(() => {
                        snapTimer = 0;
                        try { img.style.transition = ''; } catch {}
                    }, 190);
                };

                const applyTransform = ({ elastic = false } = {}) => {
                    if (!img) return;
                    const coverBasePan = (fitMode === 'cover' && zoom.scale <= 1.001);

                    const { maxX, maxY } = getMaxPan();

                    if (zoom.scale <= 1.001) {
                        zoom.scale = 1;
                        if (!coverBasePan) {
                            zoom.tx = 0;
                            zoom.ty = 0;
                            img.style.transform = 'none';
                            try { img.style.willChange = ''; } catch {}
                            return;
                        }
                        // In cover mode, keep translate at base scale (allows pan without zoom).
                        if (!elastic) {
                            zoom.tx = clamp(zoom.tx, -maxX, maxX);
                            zoom.ty = clamp(zoom.ty, -maxY, maxY);
                        }

                        const tx = elastic ? applyResistance(zoom.tx, -maxX, maxX) : zoom.tx;
                        const ty = elastic ? applyResistance(zoom.ty, -maxY, maxY) : zoom.ty;
                        const txSnap = snapToDevicePx(tx);
                        const tySnap = snapToDevicePx(ty);

                        if (Math.abs(txSnap) < 0.25 && Math.abs(tySnap) < 0.25) {
                            zoom.tx = 0;
                            zoom.ty = 0;
                            img.style.transform = 'none';
                            try { img.style.willChange = ''; } catch {}
                            return;
                        }

                        img.style.transform = `translate3d(${txSnap}px, ${tySnap}px, 0)`;
                        try { img.style.willChange = 'transform'; } catch {}
                        return;
                    }

                    if (!elastic) {
                        zoom.tx = clamp(zoom.tx, -maxX, maxX);
                        zoom.ty = clamp(zoom.ty, -maxY, maxY);
                    }

                    const tx = elastic ? applyResistance(zoom.tx, -maxX, maxX) : zoom.tx;
                    const ty = elastic ? applyResistance(zoom.ty, -maxY, maxY) : zoom.ty;
                    const txSnap = snapToDevicePx(tx);
                    const tySnap = snapToDevicePx(ty);

                    // CSS transforms apply right-to-left; using translate() scale() means pan isn't scaled.
                    img.style.transform = `translate3d(${txSnap}px, ${tySnap}px, 0) scale(${zoom.scale})`;
                    try { img.style.willChange = 'transform'; } catch {}
                };

                const settlePanToBounds = () => {
                    if (!img) return;
                    const { maxX, maxY } = getMaxPan();
                    const targetX = clamp(zoom.tx, -maxX, maxX);
                    const targetY = clamp(zoom.ty, -maxY, maxY);
                    const dx = targetX - zoom.tx;
                    const dy = targetY - zoom.ty;
                    if (Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5) return;

                    clearSnapTimer();
                    try { img.style.transition = 'transform 190ms cubic-bezier(0.2, 0.9, 0.2, 1)'; } catch {}
                    zoom.tx = targetX;
                    zoom.ty = targetY;
                    applyTransform({ elastic: false });
                    snapTimer = setTimeout(() => {
                        snapTimer = 0;
                        try { img.style.transition = ''; } catch {}
                    }, 220);
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

                const applyFitMode = (mode) => {
                    fitMode = mode === 'cover' ? 'cover' : 'contain';
                    if (img) {
                        try { img.style.objectFit = fitMode; } catch {}
                    }
                    if (fitBtn) {
                        fitBtn.textContent = fitMode === 'cover' ? 'Remplir' : 'Ajuster';
                    }
                    recomputeZoomMax();
                    resetZoom();
                    applyTransform();
                };

                fitMode = readFitMode();
                applyFitMode(fitMode);

                // Recompute zoom ceiling when the image becomes available and on viewport changes.
                if (img) {
                    try {
                        img.addEventListener('load', () => recomputeZoomMax(), { once: false });
                    } catch {}
                }
                window.addEventListener('resize', () => {
                    recomputeZoomMax();
                    applyTransform();
                }, { passive: true });

                if (fitBtn) {
                    fitBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        closeDetails();
                        const next = fitMode === 'cover' ? 'contain' : 'cover';
                        writeFitMode(next);
                        applyFitMode(next);
                        setHeaderVisible(true);
                    });
                }

                // --- Gestures ---
                // Track pointers with start + current position so we can detect swipes correctly.
                const pointers = new Map();
                let panPointerId = null;
                let lastPanX = 0;
                let lastPanY = 0;
                let lastPinchDist = 0;

                let didPanThisGesture = false;

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
                    if (String(root.dataset.details || '0') === '1') return;
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

                    didPanThisGesture = false;
                    clearSnapTimer();
                    try { if (img) img.style.transition = ''; } catch {}

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

                    // Pan when zoomed, OR base-pan in cover mode.
                    const allowBasePan = (fitMode === 'cover' && zoom.scale <= 1.01);
                    if ((zoom.scale > 1.01 || allowBasePan) && panPointerId === e.pointerId) {
                        const dx = e.clientX - lastPanX;
                        const dy = e.clientY - lastPanY;
                        lastPanX = e.clientX;
                        lastPanY = e.clientY;
                        zoom.tx += dx;
                        zoom.ty += dy;
                        applyTransform({ elastic: true });

                        if (Math.abs(dx) > 0.5 || Math.abs(dy) > 0.5) {
                            didPanThisGesture = true;
                            // While panning the image, keep UI hidden.
                            setHeaderVisible(false);
                        }
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

                    const coverBasePan = (fitMode === 'cover' && zoom.scale <= 1.01);

                    // If zoomed, we treat gestures as pan/zoom only (no slide navigation).
                    if (zoom.scale > 1.01) {
                        if (didPanThisGesture) settlePanToBounds();
                        if (Math.abs(dx) < 10 && Math.abs(dy) < 10) onTap(endPt.x, endPt.y);
                        return;
                    }

                    // In cover mode, if the gesture was used to pan the image, don't treat it as navigation/close.
                    if (coverBasePan && didPanThisGesture) {
                        settlePanToBounds();
                        snapToCenterIfNear();
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
                        // In cover mode, only allow swipe-close when centered (prevents accidental close while panned).
                        if (coverBasePan && (Math.abs(zoom.tx) > 2 || Math.abs(zoom.ty) > 2)) return;
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
