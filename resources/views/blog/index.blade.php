<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Blog') }}
            </h2>

            <a href="{{ route('blog.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Create') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($posts->count() === 0)
                        <p class="text-gray-700">{{ __('No posts yet.') }}</p>
                    @else
                        <div class="space-y-4">
                            @foreach ($posts as $post)
                                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                                    <div>
                                        <a href="{{ route('blog.show', $post) }}" class="font-semibold text-gray-900 hover:underline">
                                            {{ $post->title }}
                                        </a>
                                        <div class="text-sm text-gray-600">{{ $post->category }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ __('Created') }}: {{ $post->created_at->diffForHumans() }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('blog.edit', $post) }}" class="text-sm text-gray-700 hover:underline">
                                            {{ __('Edit') }}
                                        </a>
                                        <form method="POST" action="{{ route('blog.destroy', $post) }}" onsubmit="return confirm('Delete this post?');">
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
                            {{ $posts->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
