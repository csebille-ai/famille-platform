<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Ressources') }}
            </h2>

            <a href="{{ route('resources.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Ajouter
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        @php
            $sections = [
                'administratives' => ['label' => '1 — Administratives', 'accent' => 'bg-indigo-50 border-indigo-200 text-indigo-900', 'badge' => 'bg-indigo-600 text-white'],
                'pratiques' => ['label' => '2 — Pratiques', 'accent' => 'bg-emerald-50 border-emerald-200 text-emerald-900', 'badge' => 'bg-emerald-600 text-white'],
                'utiles' => ['label' => '3 — Utiles', 'accent' => 'bg-amber-50 border-amber-200 text-amber-950', 'badge' => 'bg-amber-600 text-white'],
            ];
            $folders = ['A1', 'A2', 'A3'];

            $all = $resources instanceof \Illuminate\Pagination\AbstractPaginator
                ? $resources->getCollection()
                : $resources;

            $grouped = $all
                ->groupBy(fn($r) => $r->section ?: 'administratives')
                ->map(fn($bySection) => $bySection->groupBy(fn($r) => $r->folder ?: 'A1'));

            $commonPill = 'bg-gray-50 text-gray-700 border-gray-200';
        @endphp

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @php
                $fileTypeFor = function (?string $name, ?string $mime): string {
                    $name = (string) ($name ?? '');
                    $mime = (string) ($mime ?? '');
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                    if ($mime === 'application/pdf' || $ext === 'pdf') {
                        return 'PDF';
                    }
                    if (in_array($ext, ['doc', 'docx'], true)) {
                        return strtoupper($ext);
                    }
                    if (in_array($ext, ['xls', 'xlsx'], true)) {
                        return strtoupper($ext);
                    }
                    if (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'], true)) {
                        return 'IMG';
                    }
                    if (str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'm4v', 'webm'], true)) {
                        return 'VID';
                    }
                    return 'DOC';
                };

                $formatSize = function ($bytes): string {
                    $bytes = is_numeric($bytes) ? (float) $bytes : 0.0;
                    if ($bytes <= 0) {
                        return '';
                    }
                    $units = ['o', 'Ko', 'Mo', 'Go'];
                    $i = 0;
                    while ($bytes >= 1024 && $i < count($units) - 1) {
                        $bytes /= 1024;
                        $i++;
                    }
                    $value = $i === 0 ? (string) (int) $bytes : number_format($bytes, 1, ',', '');
                    return $value . ' ' . $units[$i];
                };
            @endphp

            <div class="grid gap-6 lg:grid-cols-3">
                @foreach ($sections as $sectionKey => $meta)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="mt-1 text-lg font-semibold {{ $meta['accent'] }} inline-flex items-center gap-2 rounded-full border px-3 py-1">
                                        <span class="h-6 w-6 rounded-full inline-flex items-center justify-center text-xs font-semibold {{ $meta['badge'] }}">
                                            {{ str_starts_with($meta['label'], '1') ? '1' : (str_starts_with($meta['label'], '2') ? '2' : '3') }}
                                        </span>
                                        <span>{{ $meta['label'] }}</span>
                                    </div>
                                </div>

                            </div>

                            <div class="mt-5 space-y-3">
                                @php
                                    $sectionFolders = $grouped->get($sectionKey) ?? collect();
                                    $visibleFolders = collect($folders)
                                        ->filter(fn($f) => ($sectionFolders->get($f) ?? collect())->count() > 0)
                                        ->values();
                                @endphp

                                @if ($visibleFolders->count() === 0)
                                    <div class="rounded-2xl border border-gray-200 p-4 text-sm text-gray-500">Aucune ressource.</div>
                                @else
                                    @foreach ($visibleFolders as $folder)
                                    @php
                                        $items = $grouped->get($sectionKey)?->get($folder) ?? collect();
                                    @endphp

                                    <details class="rounded-2xl border border-gray-200 p-4">
                                        <summary class="cursor-pointer list-none">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="inline-flex items-center gap-2">
                                                    <span class="h-7 w-7 rounded-lg bg-gray-900 text-white inline-flex items-center justify-center text-xs font-semibold">{{ $folder }}</span>
                                                    <div class="text-sm font-semibold text-gray-900">Dossier {{ $folder }}</div>
                                                </div>

                                                <div class="text-xs text-gray-500">{{ $items->count() }} élément(s)</div>
                                            </div>
                                        </summary>

                                        <div class="mt-3 space-y-2">
                                            @foreach ($items->sortByDesc('created_at')->take(8) as $resource)
                                                @php
                                                    $u = $resource->concernedUser;
                                                    $pill = $u ? $u->uiColor()['soft'] : $commonPill;

                                                    $files = $resource->displayFiles();
                                                    $fileCount = $files->count();
                                                    $filesPreview = $files->take(3);
                                                @endphp
                                                <div class="rounded-2xl border border-gray-200 bg-white p-4 hover:border-gray-300 hover:shadow-sm">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <a href="{{ route('resources.show', $resource) }}"
                                                               class="block rounded-md text-sm font-semibold text-gray-900 truncate hover:underline focus:outline-none focus:ring-2 focus:ring-gray-900/20">
                                                                {{ $resource->title }}
                                                            </a>

                                                            <div class="mt-1 text-xs text-gray-600 truncate">
                                                                {{ $resource->section === 'pratiques' ? 'Pratiques' : ($resource->section === 'utiles' ? 'Utiles' : 'Administratives') }}
                                                                <span class="text-gray-400">›</span>
                                                                Dossier {{ $resource->folder ?? 'A1' }}
                                                                <span class="text-gray-400">·</span>
                                                                @if ($u)
                                                                    {{ $u->name }}
                                                                @else
                                                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $commonPill }}">Commun</span>
                                                                @endif
                                                                <span class="text-gray-400">·</span>
                                                                {{ $resource->creator?->name ?? 'Quelqu’un' }}
                                                                <span class="text-gray-400">·</span>
                                                                <span class="font-semibold text-gray-700">{{ $fileCount }} fichier{{ $fileCount > 1 ? 's' : '' }}</span>
                                                                <span class="text-gray-400">·</span>
                                                                {{ $resource->created_at?->diffForHumans() }}
                                                            </div>
                                                        </div>

                                                        <a href="{{ route('resources.show', $resource) }}"
                                                           class="shrink-0 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                                           title="Actions">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                                                                <circle cx="12" cy="5" r="1.6" />
                                                                <circle cx="12" cy="12" r="1.6" />
                                                                <circle cx="12" cy="19" r="1.6" />
                                                            </svg>
                                                            <span class="sr-only">Actions</span>
                                                        </a>
                                                    </div>

                                                    <div class="mt-3 space-y-2">
                                                        @forelse ($filesPreview as $f)
                                                            @php
                                                                $fname = (string) ($f->name ?? '');
                                                                $fmime = (string) ($f->mime ?? '');
                                                                $ftype = $fileTypeFor($fname, $fmime);
                                                                $isReal = !empty($f->id);

                                                                $openHref = $isReal
                                                                    ? route('resources.files.open', [$resource, $f])
                                                                    : route('resources.open', $resource);
                                                                $previewHref = $isReal
                                                                    ? route('resources.files.preview', [$resource, $f])
                                                                    : route('resources.preview', $resource);
                                                                $downloadHref = $isReal
                                                                    ? route('resources.files.download', [$resource, $f])
                                                                    : route('resources.download', $resource);

                                                                $primaryHref = in_array($ftype, ['PDF', 'IMG'], true) ? $openHref : $previewHref;
                                                            @endphp

                                                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 hover:bg-gray-50">
                                                                <a href="{{ $primaryHref }}" class="min-w-0 flex items-center gap-3 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-900/20">
                                                                    <div class="shrink-0 h-10 w-10 rounded-xl border border-gray-200 bg-gray-50 text-gray-700 flex items-center justify-center">
                                                                        <span class="text-[10px] font-semibold tracking-wide">{{ $ftype }}</span>
                                                                    </div>
                                                                    <div class="min-w-0">
                                                                        <div class="text-sm font-semibold text-gray-900 truncate">{{ $fname }}</div>
                                                                        <div class="mt-0.5 text-xs text-gray-500">
                                                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border border-gray-200 bg-white text-gray-700">{{ $ftype }}</span>
                                                                            @php $size = $formatSize($f->size ?? null); @endphp
                                                                            @if ($size)
                                                                                <span class="ml-2">{{ $size }}</span>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </a>

                                                                <a href="{{ $downloadHref }}"
                                                                   class="shrink-0 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                                                   title="Télécharger">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                                                        <path d="M12 3v10" />
                                                                        <path d="M7 11l5 5 5-5" />
                                                                        <path d="M5 21h14" />
                                                                    </svg>
                                                                    <span class="sr-only">Télécharger</span>
                                                                </a>
                                                            </div>
                                                        @empty
                                                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-600">Aucun fichier.</div>
                                                        @endforelse

                                                        @if ($fileCount > 3)
                                                            <a href="{{ route('resources.show', $resource) }}" class="inline-flex items-center text-sm font-semibold text-gray-700 hover:underline">
                                                                Afficher tout ({{ $fileCount }})
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">Documents par utilisateur</div>
                            <div class="mt-1 text-sm text-gray-600">Accès rapide par personne.</div>
                        </div>
                    </div>

                    @php $sel = (string) ($selectedUser ?? ''); @endphp

                    <form method="GET" action="{{ route('resources.index') }}" class="mt-5 sm:hidden">
                        <label for="user" class="block text-sm font-medium text-gray-700">Utilisateur</label>
                        <select id="user" name="user" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" onchange="this.form.submit()">
                            <option value="" @selected((string) $sel === '')>— Choisir —</option>
                            <option value="all" @selected((string) $sel === 'all')>Tous</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected((string) $sel === (string) $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>

                        <noscript>
                            <button type="submit" class="mt-3 inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Afficher</button>
                        </noscript>
                    </form>

                    <div class="mt-5 hidden sm:flex gap-2 overflow-x-auto pb-1">
                        <a href="{{ route('resources.index') }}"
                           class="shrink-0 inline-flex items-center gap-2 text-xs px-3 py-2 rounded-full border {{ $sel === '' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200' }}">
                            <span class="h-5 w-5 rounded-full bg-gray-600 text-white inline-flex items-center justify-center text-[10px] font-semibold">×</span>
                            <span>Effacer</span>
                        </a>

                        <a href="{{ route('resources.index', ['user' => 'all']) }}"
                           class="shrink-0 inline-flex items-center gap-2 text-xs px-3 py-2 rounded-full border {{ $sel === 'all' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200' }}">
                            <span class="h-5 w-5 rounded-full bg-gray-600 text-white inline-flex items-center justify-center text-[10px] font-semibold">*</span>
                            <span>Tous</span>
                        </a>

                        @foreach ($users as $u)
                            @php
                                $isActive = (string) $sel === (string) $u->id;
                                $soft = $u->uiColor()['soft'];
                                $solid = $u->uiColor()['solid'];
                            @endphp
                            <a href="{{ route('resources.index', ['user' => $u->id]) }}"
                               class="shrink-0 inline-flex items-center gap-2 text-xs px-3 py-2 rounded-full border {{ $isActive ? 'bg-gray-900 text-white border-gray-900' : $soft }}">
                                <span class="h-5 w-5 rounded-full inline-flex items-center justify-center text-[10px] font-semibold {{ $isActive ? 'bg-white text-gray-900' : $solid }}">{{ $u->initials() }}</span>
                                <span class="max-w-[10rem] truncate">{{ $u->name }}</span>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        @php
                            $items = $resourcesForUser ?? collect();
                        @endphp

                        @if ((string) $sel === '')
                            {{-- No results shown until a filter is chosen. --}}
                        @elseif ($items->count() === 0)
                            <div class="rounded-2xl border border-gray-200 p-5 text-sm text-gray-600">Aucun document pour ce filtre.</div>
                        @else
                            <div class="space-y-3">
                                @foreach ($items as $r)
                                        @php
                                            $u = $r->concernedUser;
                                            $pill = $u ? $u->uiColor()['soft'] : $commonPill;

                                            $files = $r->displayFiles();
                                            $fileCount = $files->count();
                                            $filesPreview = $files->take(3);
                                        @endphp
                                        <div class="rounded-2xl border border-gray-200 bg-white p-4 hover:border-gray-300 hover:shadow-sm">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <a href="{{ route('resources.show', $r) }}"
                                                       class="block rounded-md text-sm font-semibold text-gray-900 truncate hover:underline focus:outline-none focus:ring-2 focus:ring-gray-900/20">
                                                        {{ $r->title }}
                                                    </a>

                                                    <div class="mt-1 text-xs text-gray-600 truncate">
                                                        {{ $r->section === 'pratiques' ? 'Pratiques' : ($r->section === 'utiles' ? 'Utiles' : 'Administratives') }}
                                                        <span class="text-gray-400">›</span>
                                                        Dossier {{ $r->folder ?? 'A1' }}
                                                        <span class="text-gray-400">·</span>
                                                        @if ($u)
                                                            {{ $u->name }}
                                                        @else
                                                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $commonPill }}">Commun</span>
                                                        @endif
                                                        <span class="text-gray-400">·</span>
                                                        <span class="font-semibold text-gray-700">{{ $fileCount }} fichier{{ $fileCount > 1 ? 's' : '' }}</span>
                                                    </div>
                                                </div>

                                                <a href="{{ route('resources.show', $r) }}"
                                                   class="shrink-0 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                                   title="Actions">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                                                        <circle cx="12" cy="5" r="1.6" />
                                                        <circle cx="12" cy="12" r="1.6" />
                                                        <circle cx="12" cy="19" r="1.6" />
                                                    </svg>
                                                    <span class="sr-only">Actions</span>
                                                </a>
                                            </div>

                                            <div class="mt-3 space-y-2">
                                                @forelse ($filesPreview as $f)
                                                    @php
                                                        $fname = (string) ($f->name ?? '');
                                                        $fmime = (string) ($f->mime ?? '');
                                                        $ftype = $fileTypeFor($fname, $fmime);
                                                        $isReal = !empty($f->id);

                                                        $openHref = $isReal
                                                            ? route('resources.files.open', [$r, $f])
                                                            : route('resources.open', $r);
                                                        $previewHref = $isReal
                                                            ? route('resources.files.preview', [$r, $f])
                                                            : route('resources.preview', $r);
                                                        $downloadHref = $isReal
                                                            ? route('resources.files.download', [$r, $f])
                                                            : route('resources.download', $r);

                                                        $primaryHref = in_array($ftype, ['PDF', 'IMG'], true) ? $openHref : $previewHref;
                                                    @endphp

                                                    <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 hover:bg-gray-50">
                                                        <a href="{{ $primaryHref }}" class="min-w-0 flex items-center gap-3 rounded-md focus:outline-none focus:ring-2 focus:ring-gray-900/20">
                                                            <div class="shrink-0 h-10 w-10 rounded-xl border border-gray-200 bg-gray-50 text-gray-700 flex items-center justify-center">
                                                                <span class="text-[10px] font-semibold tracking-wide">{{ $ftype }}</span>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <div class="text-sm font-semibold text-gray-900 truncate">{{ $fname }}</div>
                                                                <div class="mt-0.5 text-xs text-gray-500">
                                                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border border-gray-200 bg-white text-gray-700">{{ $ftype }}</span>
                                                                    @php $size = $formatSize($f->size ?? null); @endphp
                                                                    @if ($size)
                                                                        <span class="ml-2">{{ $size }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </a>

                                                        <a href="{{ $downloadHref }}"
                                                           class="shrink-0 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                                           title="Télécharger">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                                                <path d="M12 3v10" />
                                                                <path d="M7 11l5 5 5-5" />
                                                                <path d="M5 21h14" />
                                                            </svg>
                                                            <span class="sr-only">Télécharger</span>
                                                        </a>
                                                    </div>
                                                @empty
                                                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-600">Aucun fichier.</div>
                                                @endforelse

                                                @if ($fileCount > 3)
                                                    <a href="{{ route('resources.show', $r) }}" class="inline-flex items-center text-sm font-semibold text-gray-700 hover:underline">
                                                        Afficher tout ({{ $fileCount }})
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($resources instanceof \Illuminate\Pagination\AbstractPaginator)
                <div class="mt-6">
                    {{ $resources->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
