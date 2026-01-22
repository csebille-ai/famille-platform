<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="flex items-start justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Cloud') }}
                </h2>

                @can('manage-cloud')
                    <div class="flex items-center gap-4 shrink-0">
                        <a href="{{ route('cloud.audit') }}" class="text-sm text-gray-700 hover:underline">
                            {{ __('Audit') }}
                        </a>
                        <a href="{{ route('cloud.trash') }}" class="text-sm text-gray-700 hover:underline">
                            {{ __('Trash') }}
                        </a>
                    </div>
                @endcan
            </div>

            <div class="mt-1 text-sm text-gray-600 flex flex-wrap items-center gap-1">
                @foreach ($breadcrumb as $crumb)
                    <a href="{{ route('cloud.index', $crumb->name === '/' ? [] : ['folder' => $crumb->id]) }}" class="hover:underline">
                        {{ $crumb->name === '/' ? '/' : $crumb->name }}
                    </a>
                    @if (! $loop->last)
                        <span>/</span>
                    @endif
                @endforeach
            </div>

            <div class="mt-1 text-sm text-gray-600">
                @if (!empty($quotaHuman))
                    {{ __('Utilisé: :used / :quota', ['used' => $usedHuman, 'quota' => $quotaHuman]) }}
                    @if (!is_null($usagePercent))
                        ({{ $usagePercent }}%)
                    @endif
                @else
                    {{ __('Utilisé: :used', ['used' => $usedHuman]) }}
                @endif
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

            @if (session('error'))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-red-600">
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 grid gap-6 lg:grid-cols-2">
                    @can('cloud-write')
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ __('New folder') }}</h3>
                            <form method="POST" action="{{ route('cloud.folders.store') }}" class="mt-4 flex items-end gap-3">
                                @csrf
                                <input type="hidden" name="parent_id" value="{{ $currentFolder->id }}" />
                                <div class="flex-1">
                                    <x-input-label for="folder_name" :value="__('Name')" />
                                    <x-text-input id="folder_name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                                </div>
                                <x-primary-button>
                                    {{ __('Create') }}
                                </x-primary-button>
                            </form>
                        </div>

                        <div>
                            <h3 class="font-semibold text-gray-900">{{ __('Upload file') }}</h3>
                            <form method="POST" action="{{ route('cloud.files.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                                @csrf
                                <input type="hidden" name="parent_id" value="{{ $currentFolder->id }}" />

                                <div>
                                    <x-input-label for="file" :value="__('File')" />
                                    <input id="file" name="file" type="file" class="mt-1 block w-full" required />
                                    @if (!empty($maxUploadMb))
                                        <div class="mt-2 text-sm text-gray-600">
                                            {{ __('Taille max : :mb MB', ['mb' => $maxUploadMb]) }}
                                        </div>
                                    @endif
                                    <x-input-error class="mt-2" :messages="$errors->get('file')" />
                                </div>

                                <div class="flex items-center justify-end">
                                    <x-primary-button>
                                        {{ __('Upload') }}
                                    </x-primary-button>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="lg:col-span-2 text-sm text-gray-600">
                            {{ __('Read-only access.') }}
                        </div>
                    @endcan
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="GET" action="{{ route('cloud.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
                        @if ($currentFolder->name !== '/')
                            <input type="hidden" name="folder" value="{{ $currentFolder->id }}" />
                        @endif

                        <div class="flex-1 min-w-[220px]">
                            <x-input-label for="q" :value="__('Search')" />
                            <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$search" placeholder="{{ __('Search by name…') }}" />
                        </div>

                        <x-primary-button>
                            {{ __('Search') }}
                        </x-primary-button>

                        @if (!empty($search))
                            <a href="{{ route('cloud.index', $currentFolder->name === '/' ? [] : ['folder' => $currentFolder->id]) }}" class="text-sm text-gray-700 hover:underline">
                                {{ __('Clear') }}
                            </a>
                        @endif
                    </form>

                    @if ($nodes->count() === 0)
                        <p class="text-gray-700">{{ __('Empty folder.') }}</p>
                    @else
                        <div class="space-y-4">
                            @foreach ($nodes as $node)
                                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-900 break-words">
                                            @if ($node->isFolder())
                                                <a href="{{ route('cloud.index', ['folder' => $node->id]) }}" class="hover:underline">
                                                    {{ $node->name }}
                                                </a>
                                            @else
                                                {{ $node->name }}
                                            @endif
                                        </div>

                                        <div class="text-sm text-gray-600">
                                            @if ($node->isFolder())
                                                {{ __('Folder') }}
                                            @else
                                                {{ $node->size_human ?? '—' }}
                                                @if ($node->mime)
                                                    · {{ $node->mime }}
                                                @endif
                                            @endif
                                        </div>

                                        <div class="text-xs text-gray-500">
                                            {{ __('Created') }}: {{ $node->created_at->diffForHumans() }}
                                            · {{ __('By') }}: {{ $node->uploader?->name ?? '—' }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 shrink-0">
                                        @if ($node->isFolder())
                                            <a href="{{ route('cloud.index', ['folder' => $node->id]) }}" class="text-sm text-gray-700 hover:underline">
                                                {{ __('Open') }}
                                            </a>
                                        @else
                                            @php
                                                $mime = $node->mime ?? '';
                                                $isVideo = str_starts_with($mime, 'video/');
                                                $videoId = $isVideo ? (int) ($classifiedVideosByNodeId[$node->id] ?? 0) : 0;
                                                $canView = str_starts_with($mime, 'image/') || $mime === 'application/pdf';
                                                $copyLink = $canView
                                                    ? route('cloud.files.preview', $node)
                                                    : route('cloud.files.download', $node);
                                            @endphp

                                            @if ($isVideo)
                                                @if ($videoId > 0)
                                                    <a href="{{ route('videos.show', ['video' => $videoId]) }}" class="text-sm text-gray-700 hover:underline">
                                                        Ouvrir
                                                    </a>
                                                @else
                                                    @can('cloud-write')

                                                    @endcan
                                                @endif
                                            @endif

                                            @if ($canView)
                                                <a href="{{ route('cloud.files.preview', $node) }}" class="text-sm text-gray-700 hover:underline">
                                                    {{ __('Preview') }}
                                                </a>
                                            @endif

                                            <a href="{{ route('cloud.files.download', $node) }}" class="text-sm text-gray-700 hover:underline">
                                                {{ __('Download') }}
                                            </a>

                                            <span x-data="{ copied: false }">
                                                <button type="button"
                                                    class="text-sm text-gray-700 hover:underline"
                                                    @click="navigator.clipboard.writeText(@js($copyLink)); copied = true; setTimeout(() => copied = false, 1200);">
                                                    <span x-show="!copied">{{ __('Copy link') }}</span>
                                                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                                                </button>
                                            </span>
                                        @endif

                                        @can('manage-cloud')
                                            <form method="POST" action="{{ route('cloud.nodes.rename', $node) }}" class="flex items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <input type="text" name="name" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" value="{{ $node->name }}" />
                                                <button type="submit" class="text-sm text-gray-700 hover:underline">
                                                    {{ __('Rename') }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('cloud.nodes.move') }}" class="flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="node_id" value="{{ $node->id }}" />
                                                <select name="new_parent_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                                    @foreach ($folderOptions as $folderId => $folderLabel)
                                                        <option value="{{ $folderId }}" @selected($folderId === $currentFolder->id)>
                                                            {{ $folderLabel }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="text-sm text-gray-700 hover:underline">
                                                    {{ __('Move') }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('cloud.nodes.destroy', $node) }}" onsubmit="return confirm('Delete this item?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:underline">
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        @endcan
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
