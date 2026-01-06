<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $video->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="aspect-video bg-gray-200 rounded-lg mb-6 flex items-center justify-center">
                        <video controls class="w-full h-full rounded-lg" @if ($video->poster_path) poster="{{ route('videos.poster', $video) }}" @endif>
                            <source src="{{ route('videos.stream', $video) }}">
                            Votre navigateur ne supporte pas la balise vidéo.
                        </video>
                    </div>

                    <h1 class="text-3xl font-bold mb-2">{{ $video->title }}</h1>
                    <div class="text-sm text-gray-600 mb-4">
                        <span class="inline-block bg-gray-100 px-3 py-1 rounded">{{ ucfirst($video->category) }}</span>
                        <span class="ms-4">{{ $video->created_at->diffForHumans() }}</span>
                    </div>

                    @if ($video->description)
                        <div class="mt-6 prose prose-sm max-w-none">
                            {{ $video->description }}
                        </div>
                    @endif

                    <div class="mt-6 flex gap-4">
                        @if (Auth::id() === $video->created_by)
                            <a href="{{ route('videos.edit', $video) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                {{ __('Éditer') }}
                            </a>

                            <form method="POST" action="{{ route('videos.destroy', $video) }}" onsubmit="return confirm('Supprimer cette vidéo ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                                    {{ __('Supprimer') }}
                                </button>
                            </form>
                        @elseif (Auth::user()?->role === 'admin')
                            <form method="POST" action="{{ route('videos.destroy', $video) }}" onsubmit="return confirm('Supprimer cette vidéo ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                                    {{ __('Supprimer') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <a href="{{ route('videos.index') }}" class="text-indigo-600 hover:text-indigo-900">
                    ← {{ __('Retour aux vidéos') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
