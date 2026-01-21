@php
    /** @var \App\Models\User $user */
    $avatarUrl = '';
    try {
        $avatarUrl = (string) (avatarUrl($user) ?? '');
    } catch (\Throwable) {
        $avatarUrl = '';
    }
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-5" id="profile-avatar" data-upload-url="{{ url('/profile/avatar') }}">
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="mt-1 text-lg font-semibold text-slate-900">Photo de profil</div>
            <div class="mt-1 text-xs text-slate-500">Choisis une photo, recadre, enregistre.</div>
        </div>

        <div class="shrink-0">
            <button type="button" data-action="change" class="rounded-xl border border-black/10 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]">
                Changer la photo
            </button>
            <input type="file" data-avatar-file accept="image/*" class="hidden" />
        </div>
    </div>

    <div class="mt-4 flex items-center gap-4">
        <div class="h-20 w-20 rounded-full overflow-hidden bg-slate-100 border border-black/10 flex items-center justify-center shrink-0">
            @if($avatarUrl !== '')
                <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
            @else
                <div class="text-slate-700 font-semibold">
                    {{ $user->initials() }}
                </div>
            @endif
        </div>

        <div class="text-sm text-slate-600">
            <div class="font-semibold text-slate-900">Ton avatar est affiché en rond</div>
            <div class="mt-0.5 text-xs text-slate-500">On stocke un fichier carré (recadré), et on l’affiche en rond partout.</div>
        </div>
    </div>

    <div class="fixed inset-0 z-[9999] hidden" data-avatar-modal>
        <div class="absolute inset-0 bg-black/50" data-action="close"></div>

        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl border border-black/10">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div class="text-sm font-semibold text-slate-900">Recadrer la photo</div>
                    <button type="button" class="h-9 w-9 rounded-xl border border-black/10 bg-white text-slate-700 hover:bg-slate-50" data-action="close" aria-label="Fermer">
                        <i class="ph ph-x" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="p-5">
                    <div class="mx-auto w-[320px] max-w-full">
                        <div class="relative aspect-square w-full overflow-hidden rounded-2xl border border-black/10 bg-slate-100">
                            <canvas data-avatar-canvas class="absolute inset-0 h-full w-full"></canvas>
                            <div class="absolute inset-0 pointer-events-none rounded-full" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.35); border: 2px solid rgba(255,255,255,0.85);"></div>
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <div class="text-xs font-semibold text-slate-600">Zoom</div>
                            <input type="range" min="1" max="3" step="0.01" value="1" class="w-full" data-avatar-zoom />
                        </div>

                        <div class="mt-4 flex items-center justify-end gap-2">
                            <button type="button" class="rounded-xl border border-black/10 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-action="close">Annuler</button>
                            <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" data-action="save">Enregistrer</button>
                        </div>

                        <div class="mt-2 text-xs text-slate-500">Astuce: glisse l’image pour la repositionner.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const root = document.getElementById('profile-avatar');
    if (!root) return;

    const uploadUrl = root.getAttribute('data-upload-url');
    const fileInput = root.querySelector('[data-avatar-file]');
    const modal = root.querySelector('[data-avatar-modal]');
    const canvas = root.querySelector('[data-avatar-canvas]');
    const zoomInput = root.querySelector('[data-avatar-zoom]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!uploadUrl || !fileInput || !modal || !canvas || !zoomInput) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let img = null;
    let baseScale = 1;
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;

    let dragging = false;
    let lastX = 0;
    let lastY = 0;

    const setModalOpen = (open) => {
        modal.classList.toggle('hidden', !open);
        if (!open) {
            dragging = false;
        }
    };

    const resizeCanvasToCss = () => {
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const w = Math.max(1, Math.round(rect.width * dpr));
        const h = Math.max(1, Math.round(rect.height * dpr));
        if (canvas.width !== w || canvas.height !== h) {
            canvas.width = w;
            canvas.height = h;
        }
    };

    const computeBaseScale = () => {
        if (!img) return 1;
        const w = canvas.width;
        const h = canvas.height;
        return Math.max(w / img.naturalWidth, h / img.naturalHeight);
    };

    const clampOffsets = () => {
        if (!img) return;
        const s = baseScale * zoom;
        const drawW = img.naturalWidth * s;
        const drawH = img.naturalHeight * s;
        const minX = (canvas.width - drawW) / 2;
        const minY = (canvas.height - drawH) / 2;
        offsetX = Math.min(Math.max(offsetX, minX), -minX);
        offsetY = Math.min(Math.max(offsetY, minY), -minY);
    };

    const render = () => {
        resizeCanvasToCss();
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (!img) return;

        baseScale = computeBaseScale();
        const s = baseScale * zoom;
        const drawW = img.naturalWidth * s;
        const drawH = img.naturalHeight * s;
        const dx = (canvas.width - drawW) / 2 + offsetX;
        const dy = (canvas.height - drawH) / 2 + offsetY;

        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, dx, dy, drawW, drawH);
    };

    const loadFile = (file) => {
        if (!file) return;
        const url = URL.createObjectURL(file);
        const i = new Image();
        i.decoding = 'async';
        i.onload = () => {
            img = i;
            zoom = 1;
            zoomInput.value = '1';
            offsetX = 0;
            offsetY = 0;
            setModalOpen(true);
            render();
        };
        i.onerror = () => {
            try { URL.revokeObjectURL(url); } catch (_) {}
        };
        i.src = url;
    };

    root.addEventListener('click', (e) => {
        const action = e.target?.closest?.('[data-action]')?.getAttribute('data-action');
        if (!action) return;

        if (action === 'change') {
            fileInput.value = '';
            fileInput.click();
            return;
        }

        if (action === 'close') {
            setModalOpen(false);
            return;
        }

        if (action === 'save') {
            e.preventDefault();

            (async () => {
                if (!img) return;

                const outSize = 512;
                const out = document.createElement('canvas');
                out.width = outSize;
                out.height = outSize;
                const octx = out.getContext('2d');
                if (!octx) return;

                const dpr = window.devicePixelRatio || 1;
                const scaleFactor = outSize / (canvas.width / dpr);

                const s = baseScale * zoom * scaleFactor;
                const drawW = img.naturalWidth * s;
                const drawH = img.naturalHeight * s;
                const dx = (outSize - drawW) / 2 + offsetX * scaleFactor;
                const dy = (outSize - drawH) / 2 + offsetY * scaleFactor;

                octx.imageSmoothingEnabled = true;
                octx.imageSmoothingQuality = 'high';
                octx.fillStyle = '#fff';
                octx.fillRect(0, 0, outSize, outSize);
                octx.drawImage(img, dx, dy, drawW, drawH);

                const blob = await new Promise((resolve) => out.toBlob(resolve, 'image/webp', 0.9));
                if (!blob) return;

                const fd = new FormData();
                fd.append('avatar', blob, 'avatar.webp');

                const res = await fetch(uploadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                        'Accept': 'application/json',
                    },
                    body: fd,
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    const msg = (data && (data.message || data.error)) ? String(data.message || data.error) : "Impossible d'enregistrer la photo.";
                    alert(msg);
                    return;
                }

                setModalOpen(false);
                window.location.reload();
            })();
        }
    });

    fileInput.addEventListener('change', () => {
        const f = fileInput.files && fileInput.files[0];
        if (f) loadFile(f);
    });

    zoomInput.addEventListener('input', () => {
        zoom = Math.max(1, Math.min(3, parseFloat(String(zoomInput.value || '1')) || 1));
        clampOffsets();
        render();
    });

    const onPointerDown = (e) => {
        if (!img) return;
        dragging = true;
        lastX = e.clientX;
        lastY = e.clientY;
        canvas.setPointerCapture?.(e.pointerId);
    };

    const onPointerMove = (e) => {
        if (!dragging || !img) return;
        const dx = e.clientX - lastX;
        const dy = e.clientY - lastY;
        lastX = e.clientX;
        lastY = e.clientY;
        const dpr = window.devicePixelRatio || 1;
        offsetX += dx * dpr;
        offsetY += dy * dpr;
        clampOffsets();
        render();
    };

    const onPointerUp = (e) => {
        dragging = false;
        try { canvas.releasePointerCapture?.(e.pointerId); } catch (_) {}
    };

    canvas.addEventListener('pointerdown', onPointerDown);
    canvas.addEventListener('pointermove', onPointerMove);
    canvas.addEventListener('pointerup', onPointerUp);
    canvas.addEventListener('pointercancel', onPointerUp);

    window.addEventListener('resize', () => {
        if (modal.classList.contains('hidden')) return;
        render();
    });
})();
</script>
