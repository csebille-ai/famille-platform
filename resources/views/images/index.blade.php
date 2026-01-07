@php
    $selectedUserId = (int) ($selectedUserId ?? 0);
    $maxUploadMb = (int) ($maxUploadMb ?? 0);

    $formatBytes = function (?int $bytes): string {
        $bytes = (int) ($bytes ?? 0);
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return rtrim(rtrim(number_format($value, $i === 0 ? 0 : 1, '.', ''), '0'), '.') . ' ' . $units[$i];
    };
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div
        class="max-w-6xl mx-auto px-6 py-6 space-y-6"
        x-data="{
            uploadFileName: '',
            uploadFileSize: '',
            isDragOver: false,
            confirmOpen: false,
            confirmAction: '',
            setFileFromInput(e) {
                const f = e?.target?.files?.[0];
                if (!f) {
                    this.uploadFileName = '';
                    this.uploadFileSize = '';
                    return;
                }
                this.uploadFileName = f.name;
                this.uploadFileSize = `${Math.round(f.size / 1024 / 1024 * 10) / 10} MB`;
            },
            setFileFromDrop(e) {
                const f = e?.dataTransfer?.files?.[0];
                if (!f) return;
                if (this.$refs.uploadInput) {
                    this.$refs.uploadInput.files = e.dataTransfer.files;
                }
                this.uploadFileName = f.name;
                this.uploadFileSize = `${Math.round(f.size / 1024 / 1024 * 10) / 10} MB`;
            },
        }"
    >
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Images</h1>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('images.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900">
                    Voir tout
                </a>
                @can('images-upload')
                    <button type="button" class="bg-slate-900 text-white rounded-xl px-4 py-2 text-sm font-semibold" @click="$refs.uploadInput?.click()">
                        Importer
                    </button>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="bg-white rounded-2xl shadow-sm p-4 text-sm text-gray-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <div class="text-sm font-semibold text-red-600">Erreur</div>
                <ul class="mt-2 space-y-1 text-sm text-red-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-base font-semibold text-gray-900">Importer une image</div>
                    <div class="text-sm text-slate-500 mt-1">Taille max : {{ $maxUploadMb > 0 ? $maxUploadMb : 100 }} MB</div>
                </div>
            </div>

            @can('images-upload')
                <form method="POST" action="{{ route('images.store') }}" enctype="multipart/form-data" class="mt-4" @submit="if(!$refs.uploadInput?.files?.length){ $event.preventDefault(); $refs.uploadInput?.click(); }">
                    @csrf

                    <input
                        x-ref="uploadInput"
                        id="image-upload-input"
                        name="image"
                        type="file"
                        accept="image/*"
                        class="sr-only"
                        required
                        @change="setFileFromInput($event)"
                    />

                    <div
                        class="border-2 border-dashed border-slate-200 rounded-2xl p-6"
                        :class="isDragOver ? 'bg-slate-50' : 'bg-white'"
                        @dragover.prevent="isDragOver = true"
                        @dragleave.prevent="isDragOver = false"
                        @drop.prevent="isDragOver = false; setFileFromDrop($event)"
                        @click="$refs.uploadInput?.click()"
                        role="button"
                        tabindex="0"
                        @keydown.enter.prevent="$refs.uploadInput?.click()"
                        @keydown.space.prevent="$refs.uploadInput?.click()"
                        aria-label="Zone d'import"
                    >
                        <div class="text-center">
                            <div class="text-sm font-medium text-gray-900">Glissez-déposez une image ici</div>
                            <div class="text-sm text-slate-500 mt-1">ou</div>
                            <div class="mt-3">
                                <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900" @click.stop="$refs.uploadInput?.click()">
                                    Choisir un fichier
                                </button>
                            </div>

                            <template x-if="uploadFileName">
                                <div class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-gray-900">
                                    <span class="font-medium" x-text="uploadFileName"></span>
                                    <span class="text-slate-500" x-text="uploadFileSize"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end">
                        <button type="submit" class="bg-slate-900 text-white rounded-xl px-4 py-2 text-sm font-semibold">
                            Importer
                        </button>
                    </div>
                </form>
            @else
                <div class="mt-4 text-sm text-slate-500">Accès en lecture seule.</div>
            @endcan
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-base font-semibold text-gray-900">Filtrer</div>
                    <div class="text-sm text-slate-500 mt-1">Choisir un utilisateur</div>
                </div>

                @if($selectedUserId !== 0)
                    <a href="{{ route('images.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900">
                        Effacer
                    </a>
                @endif
            </div>

            <div class="mt-4 md:hidden">
                <form method="GET" action="{{ route('images.index') }}">
                    <label for="user" class="block text-sm font-semibold text-gray-900">Utilisateur</label>
                    <select id="user" name="user" class="mt-2 block w-full rounded-xl border-slate-200" onchange="this.form.submit()">
                        <option value="0" {{ $selectedUserId === 0 ? 'selected' : '' }}>Tous</option>
                        <option value="-1" {{ $selectedUserId === -1 ? 'selected' : '' }}>Commun</option>
                        @foreach (($users ?? collect()) as $u)
                            @php($count = (int) (($userImageCounts ?? [])[$u->id] ?? 0))
                            <option value="{{ $u->id }}" {{ $selectedUserId === (int)$u->id ? 'selected' : '' }}>
                                {{ $u->name }} ({{ $count }})
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="hidden md:block">
                <div class="mt-4 flex items-center gap-2 overflow-x-auto pb-1">
                    @php($baseUrl = route('images.index'))
                    <a href="{{ $baseUrl }}" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold border {{ $selectedUserId === 0 ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-gray-900 border-slate-200' }}">
                        Tous
                    </a>
                    <a href="{{ route('images.index', ['user' => -1]) }}" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold border {{ $selectedUserId === -1 ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-gray-900 border-slate-200' }}">
                        Commun
                    </a>
                    @foreach (($users ?? collect()) as $u)
                        @php($count = (int) (($userImageCounts ?? [])[$u->id] ?? 0))
                        <a href="{{ route('images.index', ['user' => $u->id]) }}" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold border {{ $selectedUserId === (int)$u->id ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-gray-900 border-slate-200' }}">
                            {{ $u->name }}
                            <span class="ml-1 text-xs {{ $selectedUserId === (int)$u->id ? 'text-white/80' : 'text-slate-500' }}">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @if(($images ?? collect())->count() === 0)
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="text-base font-semibold text-gray-900">Aucune image pour l’instant</div>
                <div class="text-sm text-slate-500 mt-1">Importe une première photo pour démarrer.</div>
                @can('images-upload')
                    <div class="mt-4">
                        <button type="button" class="bg-slate-900 text-white rounded-xl px-4 py-2 text-sm font-semibold" @click="$refs.uploadInput?.click()">
                            Importer une image
                        </button>
                    </div>
                @endcan
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($images as $image)
                    @php
                        $isLiked = in_array((int) $image->id, array_map('intval', $likedImageIds ?? []), true);
                        $likeCount = (int) ($image->likers_count ?? 0);
                    @endphp

                    <div class="group relative rounded-2xl overflow-hidden bg-white shadow-sm" x-data="{menuOpen:false, broken:false}">
                        <a href="{{ route('images.view', $image) }}" class="block">
                            <div class="relative">
                                <div class="w-full aspect-[4/3] bg-slate-100" x-show="!broken">
                                    <img
                                        src="{{ route('images.view', $image) }}"
                                        alt="{{ $image->name }}"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                        @error="broken = true"
                                    />
                                </div>

                                <div class="w-full aspect-[4/3] bg-slate-100 flex items-center justify-center" x-show="broken" x-cloak>
                                    <div class="text-center px-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-slate-400 mx-auto" aria-hidden="true">
                                            <rect x="3" y="3" width="18" height="18" rx="2" />
                                            <path d="M3 16l5-5 4 4 3-3 6 6" />
                                            <path d="M15 9h.01" />
                                        </svg>
                                        <div class="mt-2 text-xs text-slate-500 truncate">{{ $image->name }}</div>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                @if($likeCount > 0 || $isLiked)
                                    <div class="text-xs bg-slate-900/80 text-white rounded-lg px-2 py-1 inline-flex items-center gap-1 shrink-0">
                                        <svg viewBox="0 0 24 24" class="w-4 h-4 {{ $isLiked ? 'fill-red-500 stroke-red-500' : 'fill-transparent stroke-white' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M20.84 4.61c-1.6-1.6-4.2-1.6-5.8 0L12 7.65 8.96 4.61c-1.6-1.6-4.2-1.6-5.8 0-1.6 1.6-1.6 4.2 0 5.8L12 21.25l8.84-10.84c1.6-1.6 1.6-4.2 0-5.8z"/>
                                        </svg>
                                        <span>{{ $likeCount }}</span>
                                    </div>
                                @endif

                                <div class="text-xs bg-slate-900/80 text-white rounded-lg px-2 py-1 min-w-0 truncate">
                                    {{ $image->created_at?->format('d/m/Y') }}
                                    @if (!empty($image->uploader?->name))
                                        <span class="ml-2">{{ $image->uploader->name }}</span>
                                    @endif
                                </div>
                            </div>

                            <form method="POST" action="{{ route('images.like', $image) }}" class="shrink-0">
                                @csrf
                                <button type="submit" class="text-xs bg-slate-900/80 text-white rounded-lg px-2 py-1 inline-flex items-center gap-1" aria-label="{{ $isLiked ? __('Unlike') : __('Like') }}">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4 {{ $isLiked ? 'fill-red-500 stroke-red-500' : 'fill-transparent stroke-white' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20.84 4.61c-1.6-1.6-4.2-1.6-5.8 0L12 7.65 8.96 4.61c-1.6-1.6-4.2-1.6-5.8 0-1.6 1.6-1.6 4.2 0 5.8L12 21.25l8.84-10.84c1.6-1.6 1.6-4.2 0-5.8z"/>
                                    </svg>
                                </button>
                            </form>
                        </div>

                        <button
                            type="button"
                            class="absolute top-2 right-2 bg-slate-900/80 text-white rounded-lg px-2 py-2 md:opacity-0 md:group-hover:opacity-100 transition"
                            @click="menuOpen = !menuOpen"
                            @click.outside="menuOpen = false"
                            aria-label="Actions"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.75" />
                                <circle cx="12" cy="12" r="1.75" />
                                <circle cx="19" cy="12" r="1.75" />
                            </svg>
                        </button>

                        <div
                            class="absolute top-12 right-2 z-20 w-44 rounded-xl border border-slate-200 bg-white shadow-sm p-1"
                            x-show="menuOpen"
                            x-cloak
                        >
                            <a href="{{ route('images.view', $image) }}" class="block rounded-lg px-3 py-2 text-sm text-gray-900 hover:bg-slate-50">Ouvrir</a>
                            <a href="{{ route('cloud.files.download', $image) }}" class="block rounded-lg px-3 py-2 text-sm text-gray-900 hover:bg-slate-50">Télécharger</a>
                            @can('images-delete')
                                <button
                                    type="button"
                                    class="w-full text-left rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-slate-50"
                                    @click="menuOpen = false; confirmAction = '{{ route('images.destroy', $image) }}'; confirmOpen = true;"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $images->links() }}
            </div>
        @endif

        @can('images-delete')
            <div
                class="fixed inset-0 z-50 flex items-center justify-center px-4"
                x-show="confirmOpen"
                x-cloak
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 bg-black/40" @click="confirmOpen = false"></div>
                <div class="relative w-full max-w-md bg-white rounded-2xl shadow-sm p-6">
                    <div class="text-base font-semibold text-gray-900">Supprimer l’image ?</div>
                    <div class="text-sm text-slate-500 mt-2">Cette action est irréversible.</div>

                    <form method="POST" x-bind:action="confirmAction" class="mt-6 flex items-center justify-end gap-2">
                        @csrf
                        @method('DELETE')

                        <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900" @click="confirmOpen = false">
                            Annuler
                        </button>
                        <button type="submit" class="bg-slate-900 text-white rounded-xl px-4 py-2 text-sm font-semibold">
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        @endcan
    </div>
</x-app-layout>
