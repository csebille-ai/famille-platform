<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">
                    {{ $displayName }}
                </h2>
                <div class="mt-1 text-sm text-gray-600 truncate">
                    {{ $resource->section === 'pratiques' ? 'Pratiques' : ($resource->section === 'utiles' ? 'Utiles' : 'Administratives') }}
                    <span class="text-gray-400">›</span>
                    Dossier {{ $resource->folder ?? 'A1' }}
                    <span class="text-gray-400">·</span>
                    {{ $resource->concernedUser?->name ?? 'Commun' }}
                </div>
            </div>

            <div class="shrink-0 flex items-center gap-2">
                @if ($resource->attachment_path)
                    <a href="{{ route('resources.download', $resource) }}" class="inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Télécharger
                    </a>
                @endif

                <a href="{{ route('resources.show', $resource) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Détails
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    @if (!$resource->attachment_path)
                        <div class="rounded-2xl border border-gray-200 p-5 text-sm text-gray-600">
                            Aucun fichier n’est attaché à cette ressource.
                        </div>
                    @elseif ($previewType === 'pdf')
                        <iframe
                            src="{{ route('resources.open', $resource) }}#zoom=page-width"
                            class="w-full h-[80vh] rounded-xl border border-gray-200"
                            title="{{ $displayName }}"
                        ></iframe>
                    @elseif ($previewType === 'image')
                        <img
                            src="{{ route('resources.open', $resource) }}"
                            alt="{{ $displayName }}"
                            class="w-full max-h-[75vh] object-contain rounded-xl border border-gray-200 bg-gray-50"
                        />
                    @else
                        <div class="rounded-2xl border border-gray-200 p-5 text-sm text-gray-700">
                            <div class="font-semibold text-gray-900">Aperçu indisponible</div>
                            <div class="mt-1 text-gray-600">
                                Ce type de fichier ne peut généralement pas être affiché directement dans le navigateur (ex: DOCX/XLSX).
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('resources.download', $resource) }}" class="inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                    Télécharger le fichier
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
