@php
    $selectedUserId = (int) ($selectedUserId ?? 0);
    $maxUploadMb = (int) ($maxUploadMb ?? 0);
    $returnUrl = request()->fullUrl();

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
            confirmOpen: false,
            confirmAction: '',
        }"
    >
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Images</h1>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('images.index') }}" class="hidden md:inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900">
                    Voir tout
                </a>
                @can('images-upload')
                    <a href="{{ route('images.create') }}" class="hidden md:inline-flex bg-slate-900 text-white rounded-xl px-4 py-2 text-sm font-semibold">
                        Importer
                    </a>
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

        <div class="bg-white rounded-2xl shadow-sm p-3 md:p-6 md:static sticky top-16 z-30">
            <div class="flex items-center justify-between gap-3">
                <form method="GET" action="{{ route('images.index') }}" class="flex items-center gap-3 min-w-0">
                    <div class="text-sm font-semibold text-gray-900 shrink-0">Personne</div>
                    <select id="user" name="user" class="block w-full md:w-auto rounded-xl border-slate-200" onchange="this.form.submit()">
                        <option value="0" {{ $selectedUserId === 0 ? 'selected' : '' }}>Tous</option>
                        <option value="-1" {{ $selectedUserId === -1 ? 'selected' : '' }}>Commun</option>
                        @foreach (($users ?? collect()) as $u)
                            @php
                                $count = (int) (($userImageCounts ?? [])[$u->id] ?? 0);
                            @endphp
                            <option value="{{ $u->id }}" {{ $selectedUserId === (int)$u->id ? 'selected' : '' }}>
                                {{ $u->name }} ({{ $count }})
                            </option>
                        @endforeach
                    </select>
                </form>

                @if($selectedUserId !== 0)
                    <a href="{{ route('images.index') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-gray-900">
                        Effacer
                    </a>
                @endif
            </div>

            <div class="hidden md:block">
                <div class="mt-4 flex items-center gap-2 overflow-x-auto pb-1">
                    @php
                        $baseUrl = route('images.index');
                    @endphp
                    <a href="{{ $baseUrl }}" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold border {{ $selectedUserId === 0 ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-gray-900 border-slate-200' }}">
                        Tous
                    </a>
                    <a href="{{ route('images.index', ['user' => -1]) }}" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold border {{ $selectedUserId === -1 ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-gray-900 border-slate-200' }}">
                        Commun
                    </a>
                    @foreach (($users ?? collect()) as $u)
                        @php
                            $count = (int) (($userImageCounts ?? [])[$u->id] ?? 0);
                        @endphp
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
                    <div class="text-sm text-slate-500 mt-1">Utilise le bouton + en bas à droite.</div>
                @endcan
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($images as $image)
                    @php
                        $isLiked = in_array((int) $image->id, array_map('intval', $likedImageIds ?? []), true);
                        $likeCount = (int) ($image->likers_count ?? 0);

                        $openParams = ['node' => $image];
                        if ($selectedUserId !== 0) {
                            $openParams['user'] = $selectedUserId;
                        }
                        $openParams['return'] = $returnUrl;
                    @endphp

                    <div class="group relative rounded-2xl overflow-hidden bg-white shadow-sm" x-data="{menuOpen:false, broken:false}">
                        <a href="{{ route('images.open', $openParams) }}" class="block">
                            <div class="relative">
                                <div class="w-full aspect-[4/3] bg-slate-100" x-show="!broken">
                                    <img
                                        src="{{ route('images.view', $image) }}"
                                        alt="{{ $image->name }}"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                        x-on:error="broken = true"
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
                            x-on:click="menuOpen = !menuOpen"
                            x-on:click.outside="menuOpen = false"
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
                            <a href="{{ route('images.open', $openParams) }}" class="block rounded-lg px-3 py-2 text-sm text-gray-900 hover:bg-slate-50">Ouvrir</a>
                            <a href="{{ route('cloud.files.download', $image) }}" class="block rounded-lg px-3 py-2 text-sm text-gray-900 hover:bg-slate-50">Télécharger</a>
                            @can('images-delete')
                                <button
                                    type="button"
                                    class="w-full text-left rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-slate-50"
                                    x-on:click="menuOpen = false; confirmAction = '{{ route('images.destroy', $image) }}'; confirmOpen = true;"
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
                <div class="absolute inset-0 bg-black/40" x-on:click="confirmOpen = false"></div>
                <div class="relative w-full max-w-md bg-white rounded-2xl shadow-sm p-6">
                    <div class="text-base font-semibold text-gray-900">Supprimer l’image ?</div>
                    <div class="text-sm text-slate-500 mt-2">Cette action est irréversible.</div>

                    <form method="POST" x-bind:action="confirmAction" class="mt-6 flex items-center justify-end gap-2">
                        @csrf
                        @method('DELETE')

                        <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900" x-on:click="confirmOpen = false">
                            Annuler
                        </button>
                        <button type="submit" class="bg-slate-900 text-white rounded-xl px-4 py-2 text-sm font-semibold">
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        @endcan

        @can('images-upload')
            <a
                href="{{ route('images.create') }}"
                class="fixed md:hidden bottom-6 right-6 z-40 bg-slate-900 text-white rounded-full w-14 h-14 flex items-center justify-center shadow-sm"
                aria-label="Importer une image"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6" aria-hidden="true">
                    <path d="M12 5v14" />
                    <path d="M5 12h14" />
                </svg>
            </a>
        @endcan
    </div>
</x-app-layout>
