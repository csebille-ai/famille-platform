@php
    /** @var \App\Models\SlidingPuzzle $puzzle */
    $type = strtolower(trim((string) ($puzzle->image_source_type ?? '')));
    $id = trim((string) ($puzzle->image_source_id ?? ''));

    $previewUrl = null;
    if ($type === 'media' && $id !== '' && ctype_digit($id)) {
        $previewUrl = route('images.thumb', ['node' => (int) $id]) . '?w=720&fallback=1';
    } elseif ($type === 'external' && $id !== '') {
        $previewUrl = str_starts_with($id, '/') ? url($id) : $id;
    }
@endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-4">
        <div>
            <label class="block text-sm font-semibold text-slate-900">Titre</label>
            <input name="title" value="{{ old('title', $puzzle->title) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required />
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-900">Description</label>
            <textarea name="description" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('description', $puzzle->description) }}</textarea>
            <div class="mt-1 text-xs text-slate-500">Optionnel (affiché côté joueur).</div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-semibold text-slate-900">Taille</label>
                <select name="grid_size" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @foreach([3,4] as $n)
                        <option value="{{ $n }}" @selected((int) old('grid_size', $puzzle->grid_size ?: 3) === $n)>{{ $n }}×{{ $n }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked((bool) old('is_active', $puzzle->is_active)) />
                    Actif
                </label>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 space-y-4">
        <div>
            <div class="text-sm font-semibold text-slate-900">Image</div>
            <div class="mt-1 text-xs text-slate-600">
                <span class="font-semibold">media</span> = choisir une photo du cloud (recommandé)
                <span class="text-slate-400">•</span>
                <span class="font-semibold">external</span> = URL (ou <span class="font-mono">/images/…</span>)
                <span class="text-slate-400">•</span>
                <span class="font-semibold">avatar</span> = <span class="font-mono">user:123</span>
                <span class="text-slate-400">•</span>
                vide = tuiles chiffrées
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-semibold text-slate-900">Source</label>
                <select id="sp-image-source-type" name="image_source_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @foreach(['media' => 'Media (photo)', 'external' => 'External (URL)', 'avatar' => 'Avatar (user id)'] as $k => $label)
                        <option value="{{ $k }}" @selected(old('image_source_type', $puzzle->image_source_type ?: 'media') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-900">ID / URL</label>
                <input id="sp-image-source-id" name="image_source_id" value="{{ old('image_source_id', $puzzle->image_source_id) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="ex: 123, https://…, /images/…, user:123" />
            </div>
        </div>

        <div>
            <div class="text-xs font-semibold text-slate-500">Aperçu</div>
            <div class="mt-2 rounded-2xl border border-slate-200 bg-slate-50 overflow-hidden" id="sp-image-preview-wrap">
                @if($previewUrl)
                    <img id="sp-image-preview" src="{{ $previewUrl }}" alt="" class="w-full h-auto block" loading="lazy" />
                @else
                    <div class="p-4 text-sm text-slate-600" id="sp-image-preview-empty">Pas d’aperçu.</div>
                @endif
            </div>
        </div>

        @if(!empty($recentImages))
            <div>
                <div class="text-xs font-semibold text-slate-500">Dernières photos</div>
                <div class="mt-2 grid grid-cols-3 sm:grid-cols-6 gap-2">
                    @foreach($recentImages as $img)
                        <button type="button"
                                class="group rounded-xl border border-slate-200 bg-white overflow-hidden hover:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-400"
                                data-sp-pick-image="{{ $img['id'] }}"
                                title="{{ $img['label'] }}">
                            <img src="{{ $img['thumb_url'] }}?w=240&fallback=1" alt="" class="w-full aspect-square object-cover block" loading="lazy" />
                        </button>
                    @endforeach
                </div>
                <div class="mt-2 text-xs text-slate-500">Clique sur une miniature pour remplir automatiquement la source.</div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            if (window.__spAdminPickerInit) return;
            window.__spAdminPickerInit = true;

            const typeEl = document.getElementById('sp-image-source-type');
            const idEl = document.getElementById('sp-image-source-id');
            const previewWrap = document.getElementById('sp-image-preview-wrap');

            function setPreview(url) {
                if (!previewWrap) return;
                previewWrap.innerHTML = '';
                if (!url) {
                    const div = document.createElement('div');
                    div.className = 'p-4 text-sm text-slate-600';
                    div.textContent = 'Pas d\'aperçu.';
                    previewWrap.appendChild(div);
                    return;
                }

                const img = document.createElement('img');
                img.id = 'sp-image-preview';
                img.src = url;
                img.alt = '';
                img.loading = 'lazy';
                img.className = 'w-full h-auto block';
                previewWrap.appendChild(img);
            }

            function refreshPreview() {
                const type = (typeEl && typeEl.value || '').trim().toLowerCase();
                const id = (idEl && idEl.value || '').trim();

                if (type === 'media' && /^\d+$/.test(id)) {
                    const baseThumb = @json(route('images.thumb', ['node' => 0]));
                    setPreview(baseThumb.replace('/0/thumb', '/' + id + '/thumb') + '?w=720&fallback=1');
                    return;
                }

                if (type === 'external' && id) {
                    if (id.startsWith('/')) {
                        setPreview('{{ url('/') }}' + id);
                    } else {
                        setPreview(id);
                    }
                    return;
                }

                setPreview(null);
            }

            document.querySelectorAll('[data-sp-pick-image]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-sp-pick-image');
                    if (!id) return;
                    if (typeEl) typeEl.value = 'media';
                    if (idEl) idEl.value = String(id);
                    refreshPreview();
                });
            });

            if (typeEl) typeEl.addEventListener('change', refreshPreview);
            if (idEl) idEl.addEventListener('input', refreshPreview);
        })();
    </script>
@endpush
