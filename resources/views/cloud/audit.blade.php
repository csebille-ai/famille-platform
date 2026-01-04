<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Cloud audit log') }}
                </h2>
                <div class="mt-1 text-sm text-gray-600">
                    <a href="{{ route('cloud.index') }}" class="hover:underline">{{ __('Back to Cloud') }}</a>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($logs->count() === 0)
                        <p class="text-gray-700">{{ __('No audit entries yet.') }}</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($logs as $log)
                                <div class="border-b border-gray-200 pb-3">
                                    <div class="text-sm text-gray-900">
                                        <span class="font-semibold">{{ $log->action }}</span>
                                        @if ($log->node)
                                            · <span class="text-gray-700">#{{ $log->node->id }}</span>
                                            · <span class="text-gray-700">{{ $log->node->name }}</span>
                                        @else
                                            · <span class="text-gray-700">(node deleted)</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $log->created_at->toDayDateTimeString() }}
                                        · {{ __('By') }}: {{ $log->actor?->name ?? '—' }}
                                        @if (!empty($log->meta))
                                            · {{ json_encode($log->meta) }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
