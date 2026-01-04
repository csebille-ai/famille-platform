<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Resources') }}
            </h2>

            <a href="{{ route('resources.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Create') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($resources->count() === 0)
                        <p class="text-gray-700">{{ __('No resources yet.') }}</p>
                    @else
                        <div class="space-y-4">
                            @foreach ($resources as $resource)
                                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                                    <div>
                                        <a href="{{ route('resources.show', $resource) }}" class="font-semibold text-gray-900 hover:underline">
                                            {{ $resource->title }}
                                        </a>
                                        @if ($resource->category)
                                            <div class="text-sm text-gray-600">{{ $resource->category }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500">
                                            {{ __('Created') }}: {{ $resource->created_at->diffForHumans() }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('resources.edit', $resource) }}" class="text-sm text-gray-700 hover:underline">
                                            {{ __('Edit') }}
                                        </a>
                                        <form method="POST" action="{{ route('resources.destroy', $resource) }}" onsubmit="return confirm('Delete this resource?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:underline">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $resources->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
