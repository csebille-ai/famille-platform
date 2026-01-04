<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $post->title }}
            </h2>

            <div class="flex items-center gap-3">
                <a href="{{ route('blog.edit', $post) }}" class="text-sm text-gray-700 hover:underline">
                    {{ __('Edit') }}
                </a>
                <a href="{{ route('blog.index') }}" class="text-sm text-gray-700 hover:underline">
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div>
                        <div class="text-xs text-gray-500">{{ __('Category') }}</div>
                        <div class="text-gray-900">{{ $post->category }}</div>
                    </div>

                    @if ($post->content)
                        <div>
                            <div class="text-xs text-gray-500">{{ __('Content') }}</div>
                            <div class="text-gray-900 whitespace-pre-wrap">{{ $post->content }}</div>
                        </div>
                    @endif

                    <div class="text-xs text-gray-500">
                        {{ __('Created') }}: {{ $post->created_at->toDayDateTimeString() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
