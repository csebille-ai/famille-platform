<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight break-words">
                    {{ $node->name }}
                </h2>
                <div class="mt-1 text-sm text-gray-600">
                    {{ $node->size_human ?? '—' }}
                    @if ($node->mime)
                        · {{ $node->mime }}
                    @endif
                    · {{ __('Uploaded') }}: {{ $node->created_at->toDayDateTimeString() }}
                    · {{ __('By') }}: {{ $node->uploader?->name ?? '—' }}
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('cloud.index', $node->parent_id ? ['folder' => $node->parent_id] : []) }}" class="text-sm text-gray-700 hover:underline">
                    {{ __('Back') }}
                </a>
                <span x-data="{ copied: false }">
                    <button type="button"
                        class="text-sm text-gray-700 hover:underline"
                        @click="navigator.clipboard.writeText(@js(route('cloud.files.preview', $node))); copied = true; setTimeout(() => copied = false, 1200);">
                        <span x-show="!copied">{{ __('Copy link') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </button>
                </span>
                <a href="{{ route('cloud.files.download', $node) }}" class="text-sm text-gray-700 hover:underline">
                    {{ __('Download') }}
                </a>
                <form method="POST" action="{{ route('cloud.nodes.destroy', $node) }}" onsubmit="return confirm('Delete this file?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-600 hover:underline">
                        {{ __('Delete') }}
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <a href="{{ route('cloud.index', $node->parent_id ? ['folder' => $node->parent_id] : []) }}" class="text-sm text-gray-700 hover:underline">
                            {{ __('Back') }}
                        </a>
                    </div>

                    @php
                        $mime = $node->mime ?? '';
                        $isImage = str_starts_with($mime, 'image/');
                        $isPdf = $mime === 'application/pdf';
                    @endphp

                    @if ($isImage)
                        <img src="{{ route('cloud.files.view', $node) }}" alt="{{ $node->name }}" class="max-w-full h-auto rounded" />
                    @elseif ($isPdf)
                        <iframe src="{{ route('cloud.files.view', $node) }}" class="w-full" style="height: 80vh;"></iframe>
                    @else
                        <div class="text-gray-700">
                            {{ __('Preview not available, please download.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
