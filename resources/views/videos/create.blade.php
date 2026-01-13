<x-app-layout>
    @php
        $maxVideoMb = max(1, (int) floor(((int) config('videos.max_upload_kb', 2097152)) / 1024));
        $maxVideoLabel = $maxVideoMb >= 1024
            ? (string) ((int) floor($maxVideoMb / 1024)) . ' GB'
            : (string) $maxVideoMb . ' MB';

        $mode = (string) request()->query('mode', '');
        $isPersonal = $mode === 'personal';
        $returnPath = (string) request()->query('return', '');

        $prefCategory = strtolower((string) request()->query('category', ''));
        if ($isPersonal) {
            $prefCategory = 'docs';
        } else {
            // Médiathèque uploads are only films/series.
            if (!in_array($prefCategory, ['films', 'series'], true)) {
                $prefCategory = '';
            }
        }
    @endphp
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $isPersonal ? __('Importer une vidéo perso') : __('Importer une vidéo') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($errors->any())
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('videos.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        @if($returnPath !== '')
                            <input type="hidden" name="return" value="{{ $returnPath }}" />
                        @endif

                        <div>
                            <label for="title" class="block font-medium text-sm text-gray-700">
                                {{ __('Titre') }}
                            </label>
                            <input
                                id="title"
                                type="text"
                                name="title"
                                value="{{ old('title') }}"
                                required
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            />
                            @error('title')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        @if($isPersonal)
                            <input type="hidden" name="category" value="docs" />
                        @else
                            <div>
                                <label for="category" class="block font-medium text-sm text-gray-700">
                                    {{ __('Catégorie') }}
                                </label>
                                <select
                                    id="category"
                                    name="category"
                                    required
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                                >
                                    <option value="">-- Choisir --</option>
                                    <option value="films" {{ (old('category') === 'films' || (old('category') === null && $prefCategory === 'films')) ? 'selected' : '' }}>Films</option>
                                    <option value="series" {{ (old('category') === 'series' || (old('category') === null && $prefCategory === 'series')) ? 'selected' : '' }}>Séries</option>
                                </select>
                                @error('category')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div>
                            <label for="video_file" class="block font-medium text-sm text-gray-700">
                                {{ __('Fichier vidéo') }}
                            </label>
                            <input
                                id="video_file"
                                type="file"
                                name="video_file"
                                accept="video/*"
                                required
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            />

                            <input id="poster_file" type="file" name="poster_file" accept="image/*" class="hidden" />

                            @error('video_file')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="microcopy text-xs text-gray-500 mt-1">Formats acceptés : mp4, webm, avi, mov, mkv (max {{ $maxVideoLabel }})</p>

                            <div id="poster_status" class="microcopy text-xs text-slate-500 mt-2">Miniature : génération automatique…</div>
                            <img id="poster_preview" alt="" class="mt-2 hidden w-40 aspect-video rounded-lg object-cover" />
                        </div>

                        <div>
                            <label for="description" class="block font-medium text-sm text-gray-700">
                                {{ __('Description (optionnel)') }}
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                rows="4"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                            >{{ old('description') }}</textarea>
                            @error('description')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ $returnPath !== '' ? $returnPath : route('videos.index') }}" class="text-gray-600 hover:text-gray-900">
                                {{ __('Annuler') }}
                            </a>
                            <div class="flex-1 px-4">
                                <div id="r2Quota" class="text-xs text-slate-500"></div>
                                <div id="r2UploadStatus" class="text-xs text-slate-600"></div>
                                <div id="r2UploadBarWrap" class="hidden mt-1 h-2 w-full rounded bg-slate-100 overflow-hidden">
                                    <div id="r2UploadBar" class="h-full bg-slate-900" style="width:0%"></div>
                                </div>
                            </div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                {{ __('Importer la vidéo') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const quotaUrl = @json(url('/api/uploads/quota'));
            const presignUrl = @json(url('/api/uploads/presign'));
            const mpInitUrl = @json(url('/api/uploads/multipart/init'));
            const mpCompleteUrl = @json(url('/api/uploads/multipart/complete'));
            const finalizeUrl = @json(url('/api/uploads/finalize'));
            const returnPath = @json($returnPath);
            const IS_PERSONAL = @json($isPersonal);
            const MAX_UPLOAD_BYTES = @json((int) config('uploads.max_upload_bytes'));
            const MULTIPART_THRESHOLD_BYTES = @json((int) config('uploads.multipart_threshold_bytes'));

            const form = document.querySelector('form[action="{{ route('videos.store') }}"]');
            const quotaEl = document.getElementById('r2Quota');
            const statusEl = document.getElementById('r2UploadStatus');
            const barWrap = document.getElementById('r2UploadBarWrap');
            const barEl = document.getElementById('r2UploadBar');

            const videoInput = document.getElementById('video_file');
            const posterInput = document.getElementById('poster_file');
            const posterStatus = document.getElementById('poster_status');
            const posterPreview = document.getElementById('poster_preview');

            if (!videoInput || !posterInput) return;

            let previewUrl = null;
            let posterBlob = null;

            const setStatus = (txt) => {
                if (posterStatus) posterStatus.textContent = txt;
            };

            const setUploadStatus = (txt) => {
                if (statusEl) statusEl.textContent = String(txt || '');
            };

            const setUploadProgress = (pct) => {
                const p = Math.max(0, Math.min(100, Math.round(Number(pct || 0))));
                if (barWrap) barWrap.classList.toggle('hidden', p <= 0 || p >= 100);
                if (barEl) barEl.style.width = p + '%';
            };

            const formatBytes = (bytes) => {
                const b = Number(bytes || 0);
                if (!Number.isFinite(b) || b <= 0) return '0 B';
                const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                let v = b;
                let i = 0;
                while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
                const txt = (v >= 10 || i === 0) ? v.toFixed(0) : v.toFixed(1);
                return `${txt} ${units[i]}`;
            };

            const postJson = async (url, payload) => {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(payload || {}),
                });
                const txt = await res.text();
                let json = null;
                try { json = txt ? JSON.parse(txt) : null; } catch (e) {}
                if (!res.ok) {
                    const msg = (json && json.message) ? String(json.message) : `Erreur upload (${res.status})`;
                    throw new Error(msg);
                }
                return json;
            };

            const postForm = async (url, formData) => {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: formData,
                });
                const txt = await res.text();
                let json = null;
                try { json = txt ? JSON.parse(txt) : null; } catch (e) {}
                if (!res.ok) {
                    const msg = (json && json.message) ? String(json.message) : `Erreur upload (${res.status})`;
                    throw new Error(msg);
                }
                return json;
            };

            const putWithProgress = (url, blobOrFile, contentType, onProgress) => {
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('PUT', url, true);
                    if (contentType) xhr.setRequestHeader('Content-Type', contentType);
                    xhr.upload.onprogress = (evt) => {
                        if (!evt.lengthComputable) return;
                        if (typeof onProgress === 'function') onProgress(evt.loaded, evt.total);
                    };
                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve({ etag: xhr.getResponseHeader('ETag') || xhr.getResponseHeader('etag') || null });
                        } else {
                            reject(new Error(`Upload failed (${xhr.status})`));
                        }
                    };
                    xhr.onerror = () => reject(new Error('network_error'));
                    xhr.send(blobOrFile);
                });
            };

            const refreshQuota = async () => {
                if (!quotaEl) return;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch(quotaUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                });
                if (!res.ok) return;
                const data = await res.json();
                const remaining = Number(data?.remaining_bytes ?? 0);
                quotaEl.textContent = `Espace restant : ${formatBytes(remaining)}`;
            };

            refreshQuota().catch(() => {});

            const clearPreview = () => {
                if (previewUrl) {
                    try { URL.revokeObjectURL(previewUrl); } catch (e) {}
                    previewUrl = null;
                }
                posterBlob = null;
                if (posterPreview) {
                    posterPreview.src = '';
                    posterPreview.classList.add('hidden');
                }
            };

            const wait = (target, eventName, timeoutMs) => {
                return new Promise((resolve, reject) => {
                    let done = false;
                    const onDone = () => {
                        if (done) return;
                        done = true;
                        cleanup();
                        resolve();
                    };
                    const onErr = (e) => {
                        if (done) return;
                        done = true;
                        cleanup();
                        reject(e);
                    };
                    const cleanup = () => {
                        clearTimeout(timer);
                        target.removeEventListener(eventName, onDone);
                        target.removeEventListener('error', onErr);
                    };
                    const timer = setTimeout(() => onErr(new Error('timeout:' + eventName)), timeoutMs);
                    target.addEventListener(eventName, onDone, { once: true });
                    target.addEventListener('error', onErr, { once: true });
                });
            };

            const buildPosterBlob = async (file) => {
                if (!file) return null;
                if (!('URL' in window) || !('createObjectURL' in URL)) return null;

                const url = URL.createObjectURL(file);
                try {
                    const video = document.createElement('video');
                    video.preload = 'metadata';
                    video.muted = true;
                    video.playsInline = true;
                    video.src = url;

                    await wait(video, 'loadedmetadata', 8000);
                    const duration = Number(video.duration || 0);
                    const target = (Number.isFinite(duration) && duration > 2) ? 1 : 0;
                    try { video.currentTime = target; } catch (e) { /* ignore */ }
                    await wait(video, 'seeked', 8000);

                    const w = video.videoWidth || 0;
                    const h = video.videoHeight || 0;
                    if (!w || !h) return null;

                    const maxW = 640;
                    const scale = Math.min(1, maxW / w);
                    const cw = Math.max(1, Math.round(w * scale));
                    const ch = Math.max(1, Math.round(h * scale));

                    const canvas = document.createElement('canvas');
                    canvas.width = cw;
                    canvas.height = ch;
                    const ctx = canvas.getContext('2d');
                    if (!ctx) return null;
                    ctx.drawImage(video, 0, 0, cw, ch);

                    const blob = await new Promise((resolve) => {
                        canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.75);
                    });
                    return blob || null;
                } catch (e) {
                    return null;
                } finally {
                    try { URL.revokeObjectURL(url); } catch (e) {}
                }
            };

            const setPosterFile = (blob) => {
                try {
                    if (!blob || !('DataTransfer' in window)) return false;
                    const dt = new DataTransfer();
                    dt.items.add(new File([blob], 'poster.jpg', { type: blob.type || 'image/jpeg' }));
                    posterInput.files = dt.files;
                    return posterInput.files && posterInput.files.length > 0;
                } catch (e) {
                    return false;
                }
            };

            videoInput.addEventListener('change', async () => {
                clearPreview();

                const file = videoInput.files && videoInput.files[0];
                if (!file) {
                    setStatus('Miniature : aucune');
                    return;
                }

                setStatus('Miniature : génération automatique…');
                const blob = await buildPosterBlob(file);
                if (!blob) {
                    setStatus('Miniature : non générée (le navigateur ne supporte pas la capture)');
                    return;
                }

                // Keep a reference to the blob so we can always upload it later,
                // even if the browser prevents programmatically setting <input type="file">.
                posterBlob = blob;

                const ok = setPosterFile(blob);
                setStatus(ok
                    ? 'Miniature : générée automatiquement'
                    : 'Miniature : générée (sera envoyée directement)'
                );
                if (posterPreview) {
                    previewUrl = URL.createObjectURL(blob);
                    posterPreview.src = previewUrl;
                    posterPreview.classList.remove('hidden');
                }
            });

            setStatus('Miniature : génération automatique…');

            if (form) {
                form.addEventListener('submit', async (e) => {
                    try {
                        e.preventDefault();

                        const file = videoInput.files && videoInput.files[0];
                        if (!file) {
                            alert('Choisis une vidéo.');
                            return;
                        }

                        const size = Number(file.size || 0);
                        if (size <= 0) {
                            alert('Fichier invalide.');
                            return;
                        }

                        if (size > MAX_UPLOAD_BYTES) {
                            alert('Fichier trop volumineux (max 2 Go).');
                            return;
                        }

                        const title = (document.getElementById('title')?.value || '').trim();
                        const category = (document.getElementById('category')?.value || document.querySelector('input[name="category"]')?.value || '').trim();
                        const description = (document.getElementById('description')?.value || '').trim();
                        const mime = String(file.type || 'video/mp4');

                        // Médiathèque uploads MUST have an explicit category (films/series).
                        if (document.getElementById('category') && !category) {
                            alert('Choisis une catégorie (films / séries / documentaires).');
                            return;
                        }

                        setUploadStatus('Upload…');
                        setUploadProgress(1);

                        let key = '';
                        let publicUrl = null;

                        if (size > MULTIPART_THRESHOLD_BYTES) {
                            const init = await postJson(mpInitUrl, {
                                filename: file.name || 'video',
                                mime,
                                size,
                                kind: 'video',
                                context: 'media',
                            });

                            const partSize = Number(init?.part_size || 0);
                            const parts = Array.isArray(init?.parts) ? init.parts : [];
                            const uploadId = String(init?.upload_id || '');
                            key = String(init?.key || '');
                            publicUrl = init?.public_url || null;

                            if (!uploadId || !key || !partSize || parts.length === 0) {
                                throw new Error('Multipart init invalide.');
                            }

                            const etags = [];

                            const partBytesArr = parts.map((p) => {
                                const partNumber = Number(p?.part_number || 0);
                                const start = (partNumber - 1) * partSize;
                                const end = Math.min(size, start + partSize);
                                return Math.max(0, end - start);
                            });
                            const partLoadedArr = parts.map(() => 0);

                            const updateOverallProgress = () => {
                                const loaded = partLoadedArr.reduce((a, b) => a + Number(b || 0), 0);
                                const pct = Math.max(0, Math.min(99, Math.round((loaded / size) * 100)));
                                setUploadStatus(`Upload… ${pct}%`);
                                setUploadProgress(pct);
                            };

                            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
                            const maxConc = isMobile ? 2 : 4;
                            const concurrency = Math.max(1, Math.min(maxConc, parts.length));

                            const uploadPartAtIndex = async (idx) => {
                                const p = parts[idx];
                                const partNumber = Number(p?.part_number || 0);
                                const uploadUrl = String(p?.upload_url || '');
                                if (!partNumber || !uploadUrl) throw new Error('Part invalide.');

                                const start = (partNumber - 1) * partSize;
                                const end = Math.min(size, start + partSize);
                                const blob = file.slice(start, end);
                                const partBytes = partBytesArr[idx];

                                let attempt = 0;
                                while (true) {
                                    try {
                                        const res = await putWithProgress(uploadUrl, blob, mime, (loaded) => {
                                            const v = Math.max(0, Math.min(partBytes, Number(loaded || 0)));
                                            partLoadedArr[idx] = v;
                                            updateOverallProgress();
                                        });
                                        const etag = String(res?.etag || '').trim();
                                        if (!etag) throw new Error('ETag manquant (R2).');
                                        partLoadedArr[idx] = partBytes;
                                        updateOverallProgress();
                                        etags.push({ part_number: partNumber, etag });
                                        return;
                                    } catch (err) {
                                        attempt++;
                                        if (attempt >= 3) throw err;
                                        await new Promise(r => setTimeout(r, 750 * attempt));
                                    }
                                }
                            };

                            let nextIndex = 0;
                            const workers = Array.from({ length: concurrency }, () => (async () => {
                                while (true) {
                                    const idx = nextIndex;
                                    nextIndex++;
                                    if (idx >= parts.length) return;
                                    await uploadPartAtIndex(idx);
                                }
                            })());

                            await Promise.all(workers);
                            etags.sort((a, b) => Number(a.part_number) - Number(b.part_number));

                            const complete = await postJson(mpCompleteUrl, {
                                key,
                                upload_id: uploadId,
                                parts: etags,
                                mime,
                                size,
                                kind: 'video',
                                context: 'media',
                            });
                            publicUrl = complete?.public_url || publicUrl;
                        } else {
                            const presign = await postJson(presignUrl, {
                                filename: file.name || 'video',
                                mime,
                                size,
                                kind: 'video',
                                context: 'media',
                            });
                            const uploadUrl = String(presign?.upload_url || '');
                            key = String(presign?.key || '');
                            publicUrl = presign?.public_url || null;
                            if (!uploadUrl || !key) throw new Error('Presign invalide.');

                            await putWithProgress(uploadUrl, file, mime, (loaded, total) => {
                                const pct = Math.max(0, Math.min(99, Math.round((loaded / (total || size)) * 100)));
                                setUploadStatus(`Upload… ${pct}%`);
                                setUploadProgress(pct);
                            });
                        }

                        setUploadStatus('Finalisation…');
                        setUploadProgress(99);

                        const finForm = new FormData();
                        finForm.append('key', key);
                        if (publicUrl) finForm.append('public_url', String(publicUrl));
                        finForm.append('mime', mime);
                        finForm.append('size', String(size));
                        finForm.append('kind', 'video');
                        finForm.append('context', 'media');
                        finForm.append('scope', IS_PERSONAL ? 'personal' : 'library');
                        if (file.name) finForm.append('filename', String(file.name));
                        if (title) finForm.append('title', String(title));
                        if (category) finForm.append('category', String(category));
                        if (description) finForm.append('description', String(description));

                        const posterFile = posterInput?.files?.[0];
                        if (posterFile) finForm.append('poster_file', posterFile, posterFile.name || 'poster.jpg');
                        else if (posterBlob) finForm.append('poster_file', posterBlob, 'poster.jpg');

                        const fin = await postForm(finalizeUrl, finForm);

                        setUploadProgress(100);
                        setUploadStatus('Terminé.');
                        refreshQuota().catch(() => {});

                        const target = (returnPath && String(returnPath).trim() !== '')
                            ? returnPath
                            : (fin?.open_url || '{{ route('videos.index') }}');

                        window.location.href = target;
                    } catch (err) {
                        setUploadProgress(0);
                        setUploadStatus('');
                        alert(String(err?.message || 'Upload impossible.'));
                    }
                });
            }
        })();
    </script>
</x-app-layout>
