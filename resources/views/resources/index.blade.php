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

                                                    $displayName = $resource->attachment_name ?: $resource->title;
                                                    $mime = (string) ($resource->attachment_mime ?: '');
                                                    $ext = strtolower(pathinfo($displayName ?? '', PATHINFO_EXTENSION));
                                                    $type = 'DOC';
                                                    if ($mime === 'application/pdf' || $ext === 'pdf') {
                                                        $type = 'PDF';
                                                    } elseif (in_array($ext, ['doc', 'docx'], true)) {
                                                        $type = strtoupper($ext);
                                                    } elseif (in_array($ext, ['xls', 'xlsx'], true)) {
                                                        $type = strtoupper($ext);
                                                    } elseif (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'], true)) {
                                                        $type = 'IMG';
                                                    } elseif (str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'm4v', 'webm'], true)) {
                                                        $type = 'VID';
                                                    }
                                                @endphp
                                                <div class="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 hover:border-gray-300 hover:shadow-sm hover:bg-gray-50">
                                                    <div class="flex items-start gap-3">
                                                        <div class="shrink-0 h-10 w-10 rounded-xl border border-gray-200 bg-gray-50 text-gray-700 flex items-center justify-center">
                                                            <span class="text-[10px] font-semibold tracking-wide">{{ $type }}</span>
                                                        </div>

                                                        <div class="min-w-0 flex-1">
                                                            <a href="{{ route('resources.show', $resource) }}"
                                                               class="block rounded-md text-sm font-semibold text-gray-900 truncate hover:underline focus:outline-none focus:ring-2 focus:ring-gray-900/20">
                                                                {{ $resource->title }}
                                                            </a>

                                                            @if ($resource->attachment_path)
                                                                <a href="{{ route('resources.preview', $resource) }}"
                                                                   class="mt-1 inline-flex max-w-full items-center gap-2 rounded-md text-sm font-semibold text-gray-900 truncate hover:underline focus:outline-none focus:ring-2 focus:ring-gray-900/20">
                                                                    <span class="truncate">{{ $displayName }}</span>
                                                                    <span class="shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-full border border-gray-200 bg-white text-gray-700">{{ $type }}</span>
                                                                </a>
                                                            @else
                                                                <div class="mt-1 text-sm text-gray-500">Aucun fichier</div>
                                                            @endif

                                                            <div class="mt-1 text-xs text-gray-600 truncate">
                                                                {{ $resource->section === 'pratiques' ? 'Pratiques' : ($resource->section === 'utiles' ? 'Utiles' : 'Administratives') }}
                                                                <span class="text-gray-400">›</span>
                                                                Dossier {{ $resource->folder ?? 'A1' }}
                                                                <span class="text-gray-400">·</span>
                                                                {{ $u?->name ?? 'Commun' }}
                                                            </div>
                                                        </div>

                                                        <div class="shrink-0">
                                                            <a href="{{ $resource->attachment_path ? route('resources.download', $resource) : route('resources.show', $resource) }}"
                                                               class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                                               title="{{ $resource->attachment_path ? 'Télécharger' : 'Ouvrir' }}">
                                                                @if ($resource->attachment_path)
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                                                        <path d="M12 3v10" />
                                                                        <path d="M7 11l5 5 5-5" />
                                                                        <path d="M5 21h14" />
                                                                    </svg>
                                                                @else
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                                                        <path d="M15 3h6v6" />
                                                                        <path d="M10 14L21 3" />
                                                                        <path d="M21 14v7H3V3h7" />
                                                                    </svg>
                                                                @endif
                                                                <span class="sr-only">{{ $resource->attachment_path ? 'Télécharger' : 'Ouvrir' }}</span>
                                                            </a>
                                                        </div>
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
                            <div class="mt-1 text-sm text-gray-600">Accès rapide par personne (inclut aussi “Commun”).</div>
                        </div>
                    </div>

                    @php $sel = (string) ($selectedUser ?? ''); @endphp

                    <form method="GET" action="{{ route('resources.index') }}" class="mt-5 sm:hidden">
                        <label for="user" class="block text-sm font-medium text-gray-700">Utilisateur</label>
                        <select id="user" name="user" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" onchange="this.form.submit()">
                            <option value="" @selected((string) $sel === '')>— Choisir —</option>
                            <option value="all" @selected((string) $sel === 'all')>Tous</option>
                            <option value="common" @selected((string) $sel === 'common')>Commun (tout le monde)</option>
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

                        <a href="{{ route('resources.index', ['user' => 'common']) }}"
                           class="shrink-0 inline-flex items-center gap-2 text-xs px-3 py-2 rounded-full border {{ $sel === 'common' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200' }}">
                            <span class="h-5 w-5 rounded-full bg-gray-600 text-white inline-flex items-center justify-center text-[10px] font-semibold">C</span>
                            <span>Commun</span>
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

                                            $displayName = $r->attachment_name ?: $r->title;
                                            $mime = (string) ($r->attachment_mime ?: '');
                                            $ext = strtolower(pathinfo($displayName ?? '', PATHINFO_EXTENSION));
                                            $type = 'DOC';
                                            if ($mime === 'application/pdf' || $ext === 'pdf') {
                                                $type = 'PDF';
                                            } elseif (in_array($ext, ['doc', 'docx'], true)) {
                                                $type = strtoupper($ext);
                                            } elseif (in_array($ext, ['xls', 'xlsx'], true)) {
                                                $type = strtoupper($ext);
                                            } elseif (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'], true)) {
                                                $type = 'IMG';
                                            } elseif (str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'm4v', 'webm'], true)) {
                                                $type = 'VID';
                                            }
                                        @endphp
                                        <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 hover:border-gray-300 hover:shadow-sm hover:bg-gray-50">
                                            <div class="flex items-start gap-3">
                                                <div class="shrink-0 h-10 w-10 rounded-xl border border-gray-200 bg-gray-50 text-gray-700 flex items-center justify-center">
                                                    <span class="text-[10px] font-semibold tracking-wide">{{ $type }}</span>
                                                </div>

                                                <div class="min-w-0 flex-1">
                                                    <a href="{{ route('resources.show', $r) }}"
                                                       class="block rounded-md text-sm font-semibold text-gray-900 truncate hover:underline focus:outline-none focus:ring-2 focus:ring-gray-900/20">
                                                        {{ $r->title }}
                                                    </a>

                                                    @if ($r->attachment_path)
                                                        <a href="{{ route('resources.preview', $r) }}"
                                                           class="mt-1 inline-flex max-w-full items-center gap-2 rounded-md text-sm font-semibold text-gray-900 truncate hover:underline focus:outline-none focus:ring-2 focus:ring-gray-900/20">
                                                            <span class="truncate">{{ $displayName }}</span>
                                                            <span class="shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-full border border-gray-200 bg-white text-gray-700">{{ $type }}</span>
                                                        </a>
                                                    @else
                                                        <div class="mt-1 text-sm text-gray-500">Aucun fichier</div>
                                                    @endif

                                                    <div class="mt-1 text-xs text-gray-600">
                                                        {{ $r->section === 'pratiques' ? 'Pratiques' : ($r->section === 'utiles' ? 'Utiles' : 'Administratives') }}
                                                        <span class="text-gray-400">›</span>
                                                        Dossier {{ $r->folder ?? 'A1' }}
                                                        <span class="text-gray-400">·</span>
                                                        {{ $u?->name ?? 'Commun' }}
                                                    </div>
                                                </div>

                                                <div class="shrink-0">
                                                    <a href="{{ $r->attachment_path ? route('resources.download', $r) : route('resources.show', $r) }}"
                                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                                       title="{{ $r->attachment_path ? 'Télécharger' : 'Ouvrir' }}">
                                                        @if ($r->attachment_path)
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                                                <path d="M12 3v10" />
                                                                <path d="M7 11l5 5 5-5" />
                                                                <path d="M5 21h14" />
                                                            </svg>
                                                        @else
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                                                <path d="M15 3h6v6" />
                                                                <path d="M10 14L21 3" />
                                                                <path d="M21 14v7H3V3h7" />
                                                            </svg>
                                                        @endif
                                                        <span class="sr-only">{{ $r->attachment_path ? 'Télécharger' : 'Ouvrir' }}</span>
                                                    </a>
                                                </div>
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
