<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-slate-900 leading-tight">Taquin</h2>
                <div class="mt-1 text-sm text-slate-600">Gestion des puzzles + images</div>
            </div>

            <a href="{{ route('admin.sliding-puzzles.create') }}" class="inline-flex items-center h-10 px-4 rounded-xl bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">
                Nouveau taquin
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('admin.ops._nav')

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900">
                    <div class="text-sm font-semibold">{{ __('Something went wrong') }}</div>
                    <ul class="mt-2 text-sm list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-900">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white">
                <div class="p-4 border-b border-slate-200">
                    <div class="text-sm font-semibold text-slate-900">{{ $puzzles->count() }} puzzle(s)</div>
                </div>

                @if($puzzles->count() === 0)
                    <div class="p-4 text-sm text-slate-600">
                        Aucun puzzle. Clique sur « Nouveau taquin ».
                    </div>
                @else
                    <div class="divide-y divide-slate-200">
                        @foreach($puzzles as $puzzle)
                            @php
                                $type = strtolower(trim((string) ($puzzle->image_source_type ?? '')));
                                $id = trim((string) ($puzzle->image_source_id ?? ''));
                                $thumb = null;

                                if ($type === 'media' && $id !== '' && ctype_digit($id)) {
                                    $thumb = route('images.thumb', ['node' => (int) $id]) . '?w=240&fallback=1';
                                } elseif ($type === 'external' && $id !== '') {
                                    $thumb = str_starts_with($id, '/') ? url($id) : $id;
                                }
                            @endphp
                            <div class="p-4 flex items-start gap-4">
                                <div class="w-20 h-20 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                                    @if($thumb)
                                        <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover" loading="lazy" />
                                    @else
                                        <div class="text-xs font-semibold text-slate-500">—</div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="text-sm font-semibold text-slate-900 truncate">{{ $puzzle->title }}</div>

                                        @if((bool) $puzzle->is_active)
                                            <span class="inline-flex items-center h-6 px-2 rounded-lg text-xs font-semibold bg-teal-50 text-teal-800 border border-teal-200">actif</span>
                                        @else
                                            <span class="inline-flex items-center h-6 px-2 rounded-lg text-xs font-semibold bg-slate-50 text-slate-700 border border-slate-200">inactif</span>
                                        @endif
                                    </div>

                                    <div class="mt-1 text-xs text-slate-600">
                                        {{ (int) $puzzle->grid_size }}×{{ (int) $puzzle->grid_size }}
                                        <span class="text-slate-400">•</span>
                                        <span class="font-semibold">{{ $type ?: '—' }}</span>
                                        @if($id !== '')
                                            <span class="text-slate-400">•</span>
                                            <span class="truncate">{{ $id }}</span>
                                        @endif
                                    </div>

                                    @if($puzzle->description)
                                        <div class="mt-2 text-xs text-slate-700 line-clamp-2">{{ $puzzle->description }}</div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="{{ route('admin.sliding-puzzles.edit', $puzzle) }}" class="inline-flex items-center h-9 px-3 rounded-xl text-sm font-semibold border border-black/10 bg-white text-slate-700 hover:bg-teal-50 hover:text-slate-900">
                                        Modifier
                                    </a>
                                    @if((bool) $puzzle->is_active)
                                        <a href="{{ route('games.sliding-puzzles.show', $puzzle) }}" class="inline-flex items-center h-9 px-3 rounded-xl text-sm font-semibold border border-black/10 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900">
                                            Jouer
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
</x-app-layout>
