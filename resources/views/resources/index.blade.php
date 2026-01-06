<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Ressources') }}
            </h2>

            <a href="{{ route('resources.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Create') }}
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
        @endphp

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-3">
                @foreach ($sections as $sectionKey => $meta)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-xs text-gray-500">Organisation</div>
                                    <div class="mt-1 text-lg font-semibold {{ $meta['accent'] }} inline-flex items-center gap-2 rounded-full border px-3 py-1">
                                        <span class="h-6 w-6 rounded-full inline-flex items-center justify-center text-xs font-semibold {{ $meta['badge'] }}">
                                            {{ str_starts_with($meta['label'], '1') ? '1' : (str_starts_with($meta['label'], '2') ? '2' : '3') }}
                                        </span>
                                        <span>{{ $meta['label'] }}</span>
                                    </div>
                                </div>

                                <a href="{{ route('resources.create') }}" class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    {{ __('Create') }}
                                </a>
                            </div>

                            <div class="mt-5 space-y-3">
                                @foreach ($folders as $folder)
                                    @php
                                        $items = $grouped->get($sectionKey)?->get($folder) ?? collect();
                                    @endphp

                                    <div class="rounded-2xl border border-gray-200 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="inline-flex items-center gap-2">
                                                <span class="h-7 w-7 rounded-lg bg-gray-900 text-white inline-flex items-center justify-center text-xs font-semibold">{{ $folder }}</span>
                                                <div class="text-sm font-semibold text-gray-900">Dossier {{ $folder }}</div>
                                            </div>

                                            <div class="text-xs text-gray-500">{{ $items->count() }} élément(s)</div>
                                        </div>

                                        <div class="mt-3 space-y-2">
                                            @if ($items->count() === 0)
                                                <div class="text-sm text-gray-500">Aucune ressource.</div>
                                            @else
                                                @foreach ($items->sortByDesc('created_at')->take(8) as $resource)
                                                    <div class="flex items-start justify-between gap-3">
                                                        <a href="{{ route('resources.show', $resource) }}" class="text-sm font-medium text-gray-900 hover:underline">
                                                            {{ $resource->title }}
                                                        </a>
                                                        <div class="text-xs text-gray-500">{{ $resource->created_at->diffForHumans() }}</div>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs text-gray-500">Filtrer</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">Documents par utilisateur</div>
                            <div class="mt-1 text-sm text-gray-600">Choisis un utilisateur pour voir les documents qui le concernent (inclut aussi “Commun”).</div>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('resources.index') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="w-full sm:max-w-sm">
                            <label for="user" class="block text-sm font-medium text-gray-700">Utilisateur</label>
                            <select id="user" name="user" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" onchange="this.form.submit()">
                                @php $sel = $selectedUser ?? ''; @endphp
                                <option value="" @selected($sel === '' || $sel === null)>— Choisir —</option>
                                <option value="all" @selected($sel === 'all')>Tous</option>
                                <option value="common" @selected($sel === 'common')>Commun (tout le monde)</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected((string) $sel === (string) $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <noscript>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Afficher</button>
                        </noscript>
                    </form>

                    <div class="mt-6">
                        @if (($selectedUser ?? '') === '' || $selectedUser === null)
                            <div class="rounded-2xl border border-gray-200 p-5 text-sm text-gray-600">Sélectionne un utilisateur ci-dessus.</div>
                        @else
                            @php
                                $items = $resourcesForUser ?? collect();
                            @endphp

                            @if ($items->count() === 0)
                                <div class="rounded-2xl border border-gray-200 p-5 text-sm text-gray-600">Aucun document pour ce filtre.</div>
                            @else
                                <div class="space-y-3">
                                    @foreach ($items as $r)
                                        <div class="rounded-2xl border border-gray-200 p-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <a href="{{ route('resources.show', $r) }}" class="text-sm font-semibold text-gray-900 hover:underline">
                                                        {{ $r->title }}
                                                    </a>

                                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                                        <span class="text-xs px-2.5 py-1.5 rounded-full border border-gray-200 bg-gray-50 text-gray-700">
                                                            {{ $r->section === 'pratiques' ? '2 — Pratiques' : ($r->section === 'utiles' ? '3 — Utiles' : '1 — Administratives') }}
                                                        </span>
                                                        <span class="text-xs px-2.5 py-1.5 rounded-full border border-gray-200 bg-white text-gray-700">
                                                            Dossier {{ $r->folder ?? 'A1' }}
                                                        </span>
                                                        <span class="text-xs px-2.5 py-1.5 rounded-full border border-gray-200 bg-white text-gray-700">
                                                            {{ $r->concernedUser?->name ?? 'Commun' }}
                                                        </span>
                                                        @if ($r->attachment_name)
                                                            <span class="text-xs px-2.5 py-1.5 rounded-full border border-gray-200 bg-white text-gray-700">
                                                                Fichier: {{ $r->attachment_name }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="text-xs text-gray-500">{{ $r->created_at->diffForHumans() }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
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
