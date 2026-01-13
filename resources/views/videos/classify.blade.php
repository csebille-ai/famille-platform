<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-900">Vidéo</h2>
            <a href="{{ route('cloud.index', $node->parent_id ? ['folder' => $node->parent_id] : []) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cloud</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="text-sm font-semibold text-gray-900">{{ $suggestedTitle }}</div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <form method="POST" action="{{ route('videos.classify.store', ['node' => $node->id]) }}">
                        @csrf
                        <input type="hidden" name="kind" value="film" />
                        <button type="submit" class="w-full rounded-xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white hover:bg-black">Film</button>
                    </form>

                    <form method="POST" action="{{ route('videos.classify.store', ['node' => $node->id]) }}">
                        @csrf
                        <input type="hidden" name="kind" value="serie" />
                        <button type="submit" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 hover:bg-gray-50">Série</button>
                    </form>
                </div>

                @if ($errors->any())
                    <div class="mt-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
