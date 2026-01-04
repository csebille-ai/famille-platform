<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Cloud trash') }}
                </h2>
                <div class="mt-1 text-sm text-gray-600">
                    <a href="{{ route('cloud.index') }}" class="hover:underline">{{ __('Back to Cloud') }}</a>
                </div>
            </div>
        </div>
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

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($nodes->count() === 0)
                        <p class="text-gray-700">{{ __('Trash is empty.') }}</p>
                    @else
                        <div class="space-y-4">
                            @foreach ($nodes as $node)
                                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-900 break-words">
                                            {{ $node->name }}
                                        </div>

                                        <div class="text-sm text-gray-600">
                                            {{ $node->isFolder() ? __('Folder') : ($node->size_human ?? '—') }}
                                            @if (!$node->isFolder() && $node->mime)
                                                · {{ $node->mime }}
                                            @endif
                                        </div>

                                        <div class="text-xs text-gray-500">
                                            {{ __('Deleted') }}: {{ optional($node->deleted_at)->diffForHumans() ?? '—' }}
                                            · {{ __('By') }}: {{ $node->uploader?->name ?? '—' }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 shrink-0">
                                        <form method="POST" action="{{ route('cloud.trash.restore', $node->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-sm text-gray-700 hover:underline">
                                                {{ __('Restore') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('cloud.trash.purge', $node->id) }}" onsubmit="return confirm('Permanently delete this item?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:underline">
                                                {{ __('Purge') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $nodes->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
