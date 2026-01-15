@php
    $rawReturn = trim((string) request()->query('return', ''));
    $safeReturn = '';
    if ($rawReturn !== '') {
        $u = parse_url($rawReturn);
        // Only allow same-site relative paths like "/media?tab=videos".
        if (is_array($u) && empty($u['scheme']) && empty($u['host']) && str_starts_with($rawReturn, '/')) {
            $safeReturn = $rawReturn;
        }
    }
    $backUrl = $safeReturn !== '' ? $safeReturn : route('media.index', ['tab' => 'videos']);

    $fileName = '';
    try {
        $fileName = $video->video_path ? basename((string) $video->video_path) : '';
    } catch (Throwable $e) {
        $fileName = '';
    }

    $title = trim((string) ($video->title ?? ''));
    if ($title === '') {
        $title = $fileName !== '' ? $fileName : 'Vidéo';
    }

    $canDelete = false;
    try {
        $uid = Auth::id();
        $canDelete = ($uid !== null && (int) $video->created_by === (int) $uid) || (Auth::user()?->role === 'admin');
    } catch (Throwable $e) {
        $canDelete = false;
    }
@endphp

<x-app-layout hideNavigation="1" pageBgClass="bg-slate-950">
    <div
        id="video-viewer"
        class="min-h-[100svh] relative overflow-hidden bg-slate-950 text-white"
        data-back-url="{{ $backUrl }}"
        data-stream-url="{{ route('videos.stream', $video) }}"
        data-poster-url="{{ $video->poster_path ? route('videos.poster', $video) : '' }}"
    >
        <style>
            #video-viewer.viewer-ui-hidden [data-viewer-ui] {
                opacity: 0;
                pointer-events: none;
            }

            #video-viewer [data-viewer-ui] {
                opacity: 1;
                pointer-events: auto;
                transition: opacity 180ms ease;
            }

            #video-viewer.reduce-motion [data-viewer-ui] {
                transition: none;
            }

            #video-sheet {
                transform: translate3d(0, 100%, 0);
                transition: transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1);
            }

            #video-viewer.sheet-open #video-sheet {
                transform: translate3d(0, 0, 0);
            }

            #video-viewer.reduce-motion #video-sheet {
                transition: none;
            }

            #video-sheet-backdrop {
                opacity: 0;
                pointer-events: none;
                transition: opacity 160ms ease;
            }

            #video-viewer.sheet-open #video-sheet-backdrop {
                opacity: 1;
                pointer-events: auto;
            }

            #video-viewer.reduce-motion #video-sheet-backdrop {
                transition: none;
            }

            #video-toast {
                opacity: 0;
                transform: translate3d(0, 8px, 0);
                transition: opacity 180ms ease, transform 180ms ease;
                pointer-events: none;
            }

            #video-toast.show {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }

            #video-viewer.reduce-motion #video-toast {
                transition: none;
            }
        </style>

        <!-- TOP BAR -->
        <div
            id="video-topbar"
            data-viewer-ui
            class="absolute left-3 right-3 z-20 flex items-center justify-between gap-3"
            style="top: calc(env(safe-area-inset-top) + 0.75rem)"
        >
            <a
                href="{{ $backUrl }}"
                class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-900/70 text-white"
                aria-label="Retour"
            >
                <span aria-hidden="true">←</span>
            </a>

            <button
                type="button"
                id="video-title-btn"
                class="min-w-0 flex-1 text-center text-sm font-semibold text-white/90 truncate px-2"
                aria-label="Infos"
            >
                {{ $title }}
            </button>

            <button
                type="button"
                id="video-menu-btn"
                class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-900/70 text-white"
                aria-label="Menu"
            >
                <span aria-hidden="true">⋯</span>
            </button>
        </div>

        <!-- STAGE -->
        <div
            id="video-stage"
            class="absolute inset-0 flex items-center justify-center"
            style="padding: calc(env(safe-area-inset-top) + 3.75rem) 0.75rem calc(env(safe-area-inset-bottom) + 2.75rem) 0.75rem"
        >
            <div
                id="video-frame"
                class="relative w-full max-w-[520px]"
                style="height: calc(100svh - env(safe-area-inset-top) - env(safe-area-inset-bottom) - 6.5rem)"
            >
                <div
                    class="absolute inset-0 rounded-2xl overflow-hidden bg-black/50 shadow-[0_10px_30px_rgba(0,0,0,0.35)]"
                >
                    <video
                        id="video-el"
                        class="w-full h-full object-contain bg-black"
                        playsinline
                        preload="metadata"
                        poster="{{ $video->poster_path ? route('videos.poster', $video) : '' }}"
                    >
                        <source src="{{ route('videos.stream', $video) }}" type="video/mp4" />
                        Votre navigateur ne supporte pas la balise vidéo.
                    </video>

                    <!-- Tap area overlay (for consistent gestures) -->
                    <button
                        type="button"
                        id="video-tap-layer"
                        class="absolute inset-0"
                        aria-label="Lecture/Pause"
                        style="background: transparent"
                    ></button>

                    <!-- Center play icon (only when paused) -->
                    <div
                        id="video-center-icon"
                        class="absolute inset-0 flex items-center justify-center"
                        style="pointer-events: none"
                    >
                        <div class="w-16 h-16 rounded-full bg-black/50 ring-1 ring-white/10 flex items-center justify-center">
                            <span class="text-2xl" aria-hidden="true">▶</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTROLS (minimal) -->
        <div
            id="video-controls"
            data-viewer-ui
            class="absolute left-0 right-0 z-20"
            style="bottom: calc(env(safe-area-inset-bottom) + 0.75rem)"
        >
            <div class="mx-auto w-[min(640px,calc(100vw-1.5rem))]">
                <div class="rounded-2xl bg-slate-900/65 ring-1 ring-white/10 px-3 py-2">
                    <input
                        id="video-progress"
                        type="range"
                        min="0"
                        max="100"
                        step="0.1"
                        value="0"
                        class="w-full"
                        aria-label="Progression"
                    />
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <button
                            type="button"
                            id="video-play-btn"
                            class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-800/60 text-white"
                            aria-label="Lecture/Pause"
                        >
                            <span id="video-play-ico" aria-hidden="true">⏸</span>
                        </button>

                        <button
                            type="button"
                            id="video-mute-btn"
                            class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-800/60 text-white"
                            aria-label="Muet"
                        >
                            <span id="video-mute-ico" aria-hidden="true">🔊</span>
                        </button>

                        <div class="flex-1"></div>

                        <button
                            type="button"
                            id="video-fullscreen-btn"
                            class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-800/60 text-white"
                            aria-label="Plein écran"
                        >
                            <span aria-hidden="true">⛶</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTTOM SHEET BACKDROP -->
        <div id="video-sheet-backdrop" class="absolute inset-0 z-30 bg-black/40"></div>

        <!-- BOTTOM SHEET -->
        <div
            id="video-sheet"
            class="absolute left-0 right-0 bottom-0 z-40"
            style="padding: 0.75rem 0.75rem calc(env(safe-area-inset-bottom) + 0.75rem) 0.75rem"
        >
            <div class="mx-auto w-[min(680px,100%)] rounded-3xl bg-slate-900 text-white ring-1 ring-white/10 shadow-2xl overflow-hidden">
                <div class="py-2 flex items-center justify-center">
                    <div class="w-12 h-1.5 rounded-full bg-white/15"></div>
                </div>

                <div class="px-4 pb-4">
                    <div class="text-xs text-white/60">Fichier</div>
                    <div class="text-sm font-semibold break-all">{{ $fileName !== '' ? $fileName : $title }}</div>

                    <div class="mt-3 text-xs text-white/60">Infos</div>
                    <div class="text-sm text-white/90">
                        <span class="inline-flex items-center rounded-full bg-white/10 px-2 py-0.5 text-xs mr-2">{{ ucfirst((string) ($video->category ?? 'Docs')) }}</span>
                        {{ $video->creator?->name ?? 'Quelqu\’un' }}
                        <span class="text-white/40">·</span>
                        {{ $video->created_at?->diffForHumans() }}
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" id="video-share-btn" class="min-h-[44px] rounded-2xl bg-white/10 hover:bg-white/15 text-sm font-semibold">Partager</button>
                        <a href="{{ route('videos.stream', $video, ['download' => 1]) }}" class="min-h-[44px] rounded-2xl bg-white/10 hover:bg-white/15 text-sm font-semibold inline-flex items-center justify-center">Télécharger</a>
                    </div>

                    @if ($canDelete)
                        <div class="mt-4 pt-4 border-t border-white/10">
                            <button type="button" id="video-delete-btn" class="w-full min-h-[44px] rounded-2xl bg-red-600/90 hover:bg-red-600 text-sm font-semibold">Supprimer</button>
                        </div>

                        <form id="video-delete-form" method="POST" action="{{ route('videos.destroy', $video) }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="return" value="{{ $backUrl }}">
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- CONFIRM MODAL -->
        <div id="video-confirm" class="hidden absolute inset-0 z-50 flex items-end justify-center" style="padding: 0.75rem">
            <div class="absolute inset-0 bg-black/55" data-confirm-close></div>
            <div class="relative w-full max-w-[520px] rounded-3xl bg-slate-900 text-white ring-1 ring-white/10 shadow-2xl overflow-hidden" style="margin-bottom: calc(env(safe-area-inset-bottom) + 0.25rem)">
                <div class="p-4">
                    <div class="text-base font-semibold">Supprimer cette vidéo ?</div>
                    <div class="mt-1 text-sm text-white/70">Cette action est irréversible.</div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" class="min-h-[44px] rounded-2xl bg-white/10 hover:bg-white/15 font-semibold" data-confirm-cancel>Annuler</button>
                        <button type="button" class="min-h-[44px] rounded-2xl bg-red-600/90 hover:bg-red-600 font-semibold" data-confirm-delete>Supprimer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOAST -->
        <div class="absolute left-0 right-0 z-50 flex justify-center" style="bottom: calc(env(safe-area-inset-bottom) + 1rem)">
            <div id="video-toast" class="max-w-[min(520px,calc(100vw-1.5rem))] rounded-2xl bg-black/70 text-white px-4 py-3 text-sm ring-1 ring-white/10">
                <span id="video-toast-text"></span>
            </div>
        </div>

        <script>
            (() => {
                const root = document.getElementById('video-viewer');
                if (!root) return;

                const prefersReducedMotion = (() => {
                    try {
                        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
                    } catch {
                        return false;
                    }
                })();
                if (prefersReducedMotion) root.classList.add('reduce-motion');

                const backUrl = String(root.dataset.backUrl || '').trim();

                const video = document.getElementById('video-el');
                const frame = document.getElementById('video-frame');
                const tapLayer = document.getElementById('video-tap-layer');
                const centerIcon = document.getElementById('video-center-icon');

                const playBtn = document.getElementById('video-play-btn');
                const playIco = document.getElementById('video-play-ico');
                const muteBtn = document.getElementById('video-mute-btn');
                const muteIco = document.getElementById('video-mute-ico');
                const fullscreenBtn = document.getElementById('video-fullscreen-btn');
                const progress = document.getElementById('video-progress');

                const menuBtn = document.getElementById('video-menu-btn');
                const titleBtn = document.getElementById('video-title-btn');
                const sheet = document.getElementById('video-sheet');
                const sheetBackdrop = document.getElementById('video-sheet-backdrop');

                const shareBtn = document.getElementById('video-share-btn');

                const confirmWrap = document.getElementById('video-confirm');
                const deleteBtn = document.getElementById('video-delete-btn');
                const deleteForm = document.getElementById('video-delete-form');

                const toast = document.getElementById('video-toast');
                const toastText = document.getElementById('video-toast-text');

                let hideUiTimer = 0;
                let isScrubbing = false;
                let lastTapAt = 0;
                let lastTapX = 0;
                let lastTapY = 0;

                const setUiVisible = (visible) => {
                    root.classList.toggle('viewer-ui-hidden', !visible);
                };

                const showToast = (msg, ms = 1400) => {
                    if (!toast || !toastText) return;
                    toastText.textContent = String(msg || '');
                    toast.classList.add('show');
                    window.setTimeout(() => toast.classList.remove('show'), Math.max(400, ms));
                };

                const closeSheet = () => root.classList.remove('sheet-open');
                const openSheet = () => {
                    root.classList.add('sheet-open');
                    setUiVisible(true);
                };

                const openConfirm = () => {
                    if (!confirmWrap) return;
                    confirmWrap.classList.remove('hidden');
                    setUiVisible(true);
                };
                const closeConfirm = () => {
                    if (!confirmWrap) return;
                    confirmWrap.classList.add('hidden');
                };

                const scheduleAutoHide = () => {
                    if (hideUiTimer) {
                        clearTimeout(hideUiTimer);
                        hideUiTimer = 0;
                    }
                    // Only auto-hide when playing.
                    if (!video || video.paused) return;
                    hideUiTimer = setTimeout(() => {
                        if (!root.classList.contains('sheet-open') && !root.classList.contains('confirm-open')) {
                            setUiVisible(false);
                        }
                    }, 950);
                };

                const updatePlayUi = () => {
                    if (!video) return;
                    const paused = !!video.paused;
                    if (playIco) playIco.textContent = paused ? '▶' : '⏸';
                    if (centerIcon) centerIcon.style.opacity = paused ? '1' : '0';
                    if (centerIcon) centerIcon.style.transform = paused ? 'scale(1)' : 'scale(0.98)';
                    scheduleAutoHide();
                };

                const updateMuteUi = () => {
                    if (!video) return;
                    const muted = !!video.muted;
                    if (muteIco) muteIco.textContent = muted ? '🔇' : '🔊';
                };

                const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
                const updateProgressUi = () => {
                    if (!video || !progress || isScrubbing) return;
                    const d = Number(video.duration || 0);
                    const t = Number(video.currentTime || 0);
                    if (!Number.isFinite(d) || d <= 0) {
                        progress.value = '0';
                        return;
                    }
                    progress.value = String(clamp((t / d) * 100, 0, 100));
                };

                const togglePlay = async () => {
                    if (!video) return;
                    if (video.paused) {
                        try {
                            await video.play();
                        } catch {
                            // ignore (autoplay restrictions)
                        }
                    } else {
                        try { video.pause(); } catch {}
                    }
                    updatePlayUi();
                };

                const toggleFullscreen = async () => {
                    if (!video) return;
                    // iOS Safari
                    try {
                        if (typeof video.webkitEnterFullscreen === 'function') {
                            video.webkitEnterFullscreen();
                            return;
                        }
                    } catch {}
                    try {
                        if (document.fullscreenElement) {
                            await document.exitFullscreen();
                        } else if (frame && frame.requestFullscreen) {
                            await frame.requestFullscreen();
                        } else if (video.requestFullscreen) {
                            await video.requestFullscreen();
                        }
                    } catch {
                        // ignore
                    }
                };

                const isDoubleTap = (x, y) => {
                    const t = Date.now();
                    const dt = t - lastTapAt;
                    const dx = x - lastTapX;
                    const dy = y - lastTapY;
                    return dt > 0 && dt < 280 && (dx * dx + dy * dy) < (28 * 28);
                };

                const seekBy = (seconds) => {
                    if (!video) return;
                    const d = Number(video.duration || 0);
                    if (!Number.isFinite(d) || d <= 0) return;
                    const t = Number(video.currentTime || 0);
                    video.currentTime = clamp(t + Number(seconds || 0), 0, d);
                    updateProgressUi();
                };

                const onTap = (x, y) => {
                    if (!video) return;
                    // Double-tap: skip ±10s (optional premium).
                    if (isDoubleTap(x, y)) {
                        lastTapAt = 0;
                        const rect = (tapLayer || video).getBoundingClientRect();
                        const isRight = x > rect.left + rect.width / 2;
                        seekBy(isRight ? 10 : -10);
                        setUiVisible(true);
                        scheduleAutoHide();
                        return;
                    }

                    lastTapAt = Date.now();
                    lastTapX = x;
                    lastTapY = y;

                    // Single tap: toggle play/pause and toggle UI.
                    togglePlay();
                    setUiVisible(root.classList.contains('viewer-ui-hidden'));
                    scheduleAutoHide();
                };

                // Bind controls
                if (tapLayer) {
                    tapLayer.addEventListener('click', (e) => {
                        e.preventDefault();
                        onTap(e.clientX, e.clientY);
                    });
                }
                if (playBtn) playBtn.addEventListener('click', (e) => { e.preventDefault(); togglePlay(); setUiVisible(true); });
                if (muteBtn) muteBtn.addEventListener('click', (e) => { e.preventDefault(); if (!video) return; video.muted = !video.muted; updateMuteUi(); setUiVisible(true); scheduleAutoHide(); });
                if (fullscreenBtn) fullscreenBtn.addEventListener('click', (e) => { e.preventDefault(); toggleFullscreen(); setUiVisible(true); scheduleAutoHide(); });

                if (progress) {
                    progress.addEventListener('pointerdown', () => { isScrubbing = true; setUiVisible(true); }, { passive: true });
                    progress.addEventListener('pointerup', () => { isScrubbing = false; scheduleAutoHide(); }, { passive: true });
                    progress.addEventListener('input', () => {
                        if (!video) return;
                        const d = Number(video.duration || 0);
                        if (!Number.isFinite(d) || d <= 0) return;
                        const pct = clamp(Number(progress.value || 0), 0, 100) / 100;
                        video.currentTime = pct * d;
                    });
                }

                if (video) {
                    video.addEventListener('play', () => { updatePlayUi(); setUiVisible(true); scheduleAutoHide(); });
                    video.addEventListener('pause', () => { updatePlayUi(); setUiVisible(true); });
                    video.addEventListener('timeupdate', updateProgressUi);
                    video.addEventListener('durationchange', updateProgressUi);
                    video.addEventListener('loadedmetadata', () => { updateProgressUi(); updateMuteUi(); updatePlayUi(); });
                }

                // Bottom sheet open/close
                if (menuBtn) menuBtn.addEventListener('click', (e) => { e.preventDefault(); openSheet(); });
                if (titleBtn) titleBtn.addEventListener('click', (e) => { e.preventDefault(); openSheet(); });
                if (sheetBackdrop) sheetBackdrop.addEventListener('click', (e) => { e.preventDefault(); closeSheet(); }, { passive: true });

                // Swipe up to open sheet
                let sy = 0;
                let sx = 0;
                let tracking = false;
                const stage = document.getElementById('video-stage');
                if (stage && window.PointerEvent) {
                    stage.style.touchAction = 'none';
                    stage.addEventListener('pointerdown', (e) => {
                        tracking = true;
                        sx = e.clientX;
                        sy = e.clientY;
                        try { stage.setPointerCapture(e.pointerId); } catch {}
                    }, { passive: true });
                    stage.addEventListener('pointerup', (e) => {
                        if (!tracking) return;
                        tracking = false;
                        const dx = e.clientX - sx;
                        const dy = e.clientY - sy;
                        if (Math.abs(dy) > 80 && Math.abs(dy) > Math.abs(dx) && dy < 0) {
                            openSheet();
                        }
                    }, { passive: true });
                    stage.addEventListener('pointercancel', () => { tracking = false; }, { passive: true });
                }

                // Share
                if (shareBtn) {
                    shareBtn.addEventListener('click', async (e) => {
                        e.preventDefault();
                        const url = window.location.href;
                        try {
                            if (navigator.share) {
                                await navigator.share({ title: document.title || 'Vidéo', url });
                                return;
                            }
                        } catch {
                            // ignore
                        }
                        try {
                            await navigator.clipboard.writeText(url);
                            showToast('Lien copié');
                        } catch {
                            showToast('Impossible de copier le lien');
                        }
                    });
                }

                // Delete confirmation
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        closeSheet();
                        openConfirm();
                    });
                }
                if (confirmWrap) {
                    confirmWrap.addEventListener('click', (e) => {
                        if (e.target && e.target.closest && e.target.closest('[data-confirm-close]')) {
                            closeConfirm();
                        }
                    });
                    const cancel = confirmWrap.querySelector('[data-confirm-cancel]');
                    const ok = confirmWrap.querySelector('[data-confirm-delete]');
                    if (cancel) cancel.addEventListener('click', (e) => { e.preventDefault(); closeConfirm(); });
                    if (ok) {
                        ok.addEventListener('click', async (e) => {
                            e.preventDefault();
                            if (!deleteForm) return;

                            // Try AJAX delete to provide toast feedback, fallback to regular submit.
                            try {
                                const action = deleteForm.getAttribute('action');
                                const token = (deleteForm.querySelector('input[name="_token"]') || {}).value;
                                const returnInput = deleteForm.querySelector('input[name="return"]');
                                const returnVal = returnInput ? String(returnInput.value || '') : '';

                                const body = new URLSearchParams();
                                body.set('_method', 'DELETE');
                                body.set('_token', token || '');
                                if (returnVal) body.set('return', returnVal);

                                const res = await fetch(action, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                    },
                                    body: body.toString(),
                                    credentials: 'same-origin',
                                });

                                if (!res.ok) throw new Error('delete_failed');
                                const json = await res.json().catch(() => null);
                                const msg = (json && (json.status || json.message)) ? String(json.status || json.message) : 'Vidéo supprimée.';
                                const redirect = (json && json.redirect) ? String(json.redirect) : (backUrl || '/');

                                closeConfirm();
                                showToast(msg, 900);
                                setTimeout(() => { window.location.href = redirect; }, 650);
                                return;
                            } catch {
                                // Fallback: submit (server will flash status).
                                try { deleteForm.submit(); } catch {}
                            }
                        });
                    }
                }

                // Keyboard
                window.addEventListener('keydown', (e) => {
                    if (!e) return;
                    if (e.key === 'Escape') {
                        if (confirmWrap && !confirmWrap.classList.contains('hidden')) { closeConfirm(); return; }
                        if (root.classList.contains('sheet-open')) { closeSheet(); return; }
                        if (backUrl) window.location.href = backUrl;
                    }
                    if (e.key === ' ') {
                        e.preventDefault();
                        togglePlay();
                    }
                    if (e.key === 'ArrowLeft') seekBy(-5);
                    if (e.key === 'ArrowRight') seekBy(5);
                });

                // Initial UI: visible briefly then focus.
                setUiVisible(true);
                updateMuteUi();
                updatePlayUi();
                updateProgressUi();
                setTimeout(() => scheduleAutoHide(), 1200);

                // If a status flash exists (from server), show it as a toast.
                const initialStatus = @json(session('status'));
                if (initialStatus) {
                    showToast(String(initialStatus), 1800);
                }
            })();
        </script>
    </div>
</x-app-layout>
