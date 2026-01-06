<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Images') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-red-600">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @can('images-upload')
                        <h3 class="mt-6 font-semibold text-gray-900">{{ __('Upload image') }}</h3>
                        <form method="POST" action="{{ route('images.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                            @csrf

                            <div>
                                <x-input-label for="image" :value="__('Image')" />
                                <input id="image" name="image" type="file" accept="image/*" class="mt-1 block w-full" required />
                                @if (!empty($maxUploadMb))
                                    <div class="mt-2 text-sm text-gray-600">
                                        {{ __('Taille max : :mb MB', ['mb' => $maxUploadMb]) }}
                                    </div>
                                @endif
                                <x-input-error class="mt-2" :messages="$errors->get('image')" />
                            </div>

                            <div class="flex items-center justify-end">
                                <x-primary-button>
                                    {{ __('Upload') }}
                                </x-primary-button>
                            </div>
                        </form>
                    @else
                        <div class="mt-6 text-sm text-gray-600">
                            {{ __('Read-only access.') }}
                        </div>
                    @endcan
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($images->count() === 0)
                        <p class="text-gray-700">{{ __('Aucune image pour l\'instant.') }}</p>
                    @else
                        <div class="mb-4 w-full max-w-xs">
                            <x-input-label for="user" :value="__('Voir par utilisateur')" />
                            <form method="GET" action="{{ route('images.index') }}" class="mt-1">
                                <select id="user" name="user" class="block w-full" onchange="this.form.submit()">
                                    <option value="0" {{ (int)($selectedUserId ?? 0) === 0 ? 'selected' : '' }}>{{ __('Tous') }}</option>
                                    @foreach (($users ?? collect()) as $u)
                                        @php($count = (int) (($userImageCounts ?? [])[$u->id] ?? 0))
                                        <option value="{{ $u->id }}" {{ (int)($selectedUserId ?? 0) === (int)$u->id ? 'selected' : '' }}>
                                            {{ $u->name }} ({{ $count }})
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                            @foreach ($images as $image)
                                <div class="relative bg-gray-100 rounded overflow-hidden">
                                    <a href="{{ route('images.view', $image) }}" class="block">
                                        <div class="aspect-square">
                                            <img src="{{ route('images.view', $image) }}" alt="{{ $image->name }}" class="w-full h-full object-cover" loading="lazy" />
                                        </div>
                                    </a>

                                    @php($isLiked = in_array((int) $image->id, array_map('intval', $likedImageIds ?? []), true))
                                    <form method="POST" action="{{ route('images.like', $image) }}" class="absolute bottom-2 left-2 z-10">
                                        @csrf
                                        <button type="submit" class="bg-black/70 px-2 py-1 text-xs text-white rounded inline-flex items-center gap-1" aria-label="{{ $isLiked ? __('Unlike') : __('Like') }}">
                                            <svg viewBox="0 0 24 24" class="w-4 h-4 {{ $isLiked ? 'fill-red-500 stroke-red-500' : 'fill-transparent stroke-white' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20.84 4.61c-1.6-1.6-4.2-1.6-5.8 0L12 7.65 8.96 4.61c-1.6-1.6-4.2-1.6-5.8 0-1.6 1.6-1.6 4.2 0 5.8L12 21.25l8.84-10.84c1.6-1.6 1.6-4.2 0-5.8z"/>
                                            </svg>
                                            <span>{{ (int) ($image->likers_count ?? 0) }}</span>
                                        </button>
                                    </form>

                                    @can('images-delete')
                                        <form method="POST" action="{{ route('images.destroy', $image) }}" class="absolute top-2 right-2 z-10" onsubmit="return confirm('{{ __('Supprimer cette image ?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bg-white/80 px-2 py-1 text-xs text-gray-700 rounded">
                                                {{ __('Supprimer') }}
                                            </button>
                                        </form>
                                    @endcan

                                    <div class="absolute bottom-2 right-2 z-10 bg-black/70 px-2 py-1 text-xs text-white rounded">
                                        {{ $image->created_at?->format('d/m/Y') }}
                                        @if (!empty($image->uploader?->name))
                                            <span class="ml-2">{{ $image->uploader->name }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $images->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
