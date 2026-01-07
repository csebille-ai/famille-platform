<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $resource->title }}
            </h2>

            <div class="flex items-center gap-3">
                <a href="{{ route('resources.edit', $resource) }}" class="text-sm text-gray-700 hover:underline">
                    {{ __('Edit') }}
                </a>
                <a href="{{ route('resources.index') }}" class="text-sm text-gray-700 hover:underline">
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
                        <div class="text-xs text-gray-500">Organisation</div>
                        <div class="mt-1 inline-flex items-center gap-2">
                            <span class="text-xs px-2.5 py-1.5 rounded-full border border-gray-200 bg-gray-50 text-gray-800">
                                {{ $resource->section === 'pratiques' ? '2 — Pratiques' : ($resource->section === 'utiles' ? '3 — Utiles' : '1 — Administratives') }}
                            </span>
                            <span class="text-xs px-2.5 py-1.5 rounded-full border border-gray-200 bg-white text-gray-800">
                                Dossier {{ $resource->folder ?? 'A1' }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-gray-500">Concerne</div>
                        @php
                            $u = $resource->concernedUser;
                            $pill = $u ? $u->uiColor()['soft'] : 'bg-gray-50 text-gray-700 border-gray-200';
                        @endphp
                        <div class="mt-1">
                            <span class="inline-flex items-center gap-2 text-xs px-2.5 py-1.5 rounded-full border {{ $pill }}">
                                <span class="h-5 w-5 rounded-full inline-flex items-center justify-center text-[10px] font-semibold {{ $u ? $u->uiColor()['solid'] : 'bg-gray-600 text-white' }}">
                                    {{ $u ? $u->initials() : 'C' }}
                                </span>
                                <span>{{ $u?->name ?? 'Commun (tout le monde)' }}</span>
                            </span>
                        </div>
                    </div>

                    @if ($resource->attachment_path)
                        <div>
                            <div class="text-xs text-gray-500">Fichier</div>

                            <div class="mt-1 flex items-center justify-between gap-4">
                                <div class="text-sm text-gray-900 font-medium">
                                    {{ $resource->attachment_name ?? 'Télécharger' }}
                                </div>
                                <a href="{{ route('resources.download', $resource) }}" class="inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                    Télécharger
                                </a>
                            </div>

                            @if (is_string($resource->attachment_mime) && str_starts_with($resource->attachment_mime, 'image/'))
                                <div class="mt-4 rounded-2xl border border-gray-200 overflow-hidden bg-gray-50">
                                    <img src="{{ route('resources.download', $resource) }}" alt="{{ $resource->attachment_name ?? '' }}" class="w-full max-h-[520px] object-contain" />
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($resource->content)
                        <div>
                            <div class="text-xs text-gray-500">{{ __('Content') }}</div>
                            <div class="text-gray-900 whitespace-pre-wrap">{{ $resource->content }}</div>
                        </div>
                    @endif

                    <div class="text-xs text-gray-500">
                        {{ __('Created') }}: {{ $resource->created_at->toDayDateTimeString() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
