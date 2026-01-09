<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-5xl mx-auto px-6 py-6 space-y-4">
        @if(!empty($heroMedia))
            <a href="{{ $heroMedia['href'] }}" class="block overflow-hidden rounded-3xl bg-white shadow-sm hover:bg-slate-50">
                <div class="aspect-[16/10] bg-slate-100 overflow-hidden relative">
                    @if(!empty($heroMedia['preview_url'] ?? null))
                        <img src="{{ $heroMedia['preview_url'] }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                    @else
                        <div class="h-full w-full bg-slate-50"></div>
                    @endif

                    @if(($heroMedia['type'] ?? '') === 'video')
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="h-14 w-14 rounded-full bg-white/80 backdrop-blur border border-white/70 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-7 w-7 text-slate-900" aria-hidden="true">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="p-4">
                    <div class="text-xs font-medium text-slate-500">Dernier média</div>
                    <div class="mt-1 truncate text-base font-semibold text-slate-900">{{ $heroMedia['title'] }}</div>
                    <div class="mt-1 truncate text-sm text-slate-600">
                        {{ $heroMedia['by'] ?? 'Quelqu’un' }}
                        @if(!empty($heroMedia['at'] ?? null))
                            <span class="text-slate-400">·</span>
                            {{ optional($heroMedia['at'])->diffForHumans() }}
                        @endif
                    </div>
                </div>
            </a>
        @else
            <div class="rounded-3xl bg-white p-4 shadow-sm">
                <div class="text-sm font-medium text-slate-900">Dernier média</div>
                <div class="mt-1 text-sm text-slate-600">Aucun média récent pour le moment.</div>
            </div>
        @endif

        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-medium text-slate-900">Derniers ajouts</div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('dashboard', ['feed' => 'all']) }}" class="rounded-full border px-3 py-1 text-xs font-medium {{ ($feed ?? 'all') === 'all' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300' }}">Tous</a>
                    <a href="{{ route('dashboard', ['feed' => 'photos']) }}" class="rounded-full border px-3 py-1 text-xs font-medium {{ ($feed ?? 'all') === 'photos' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300' }}">Photos</a>
                    <a href="{{ route('dashboard', ['feed' => 'videos']) }}" class="rounded-full border px-3 py-1 text-xs font-medium {{ ($feed ?? 'all') === 'videos' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300' }}">Vidéos</a>
                    <a href="{{ route('dashboard', ['feed' => 'docs']) }}" class="rounded-full border px-3 py-1 text-xs font-medium {{ ($feed ?? 'all') === 'docs' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300' }}">Docs</a>
                </div>
            </div>

            <div class="mt-3 divide-y divide-slate-100">
                @forelse(($latestAdds ?? collect())->take(15) as $item)
                    <a href="{{ $item['href'] }}" class="flex items-center gap-3 py-3 hover:bg-slate-50">
                        @if(($item['type'] ?? '') === 'image' && !empty($item['thumb_url'] ?? null))
                            <div class="h-11 w-11 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                <img src="{{ $item['thumb_url'] }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                            </div>
                        @elseif(($item['type'] ?? '') === 'video' && !empty($item['poster_url'] ?? null))
                            <div class="h-11 w-11 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                <img src="{{ $item['poster_url'] }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                            </div>
                        @else
                            <div class="h-11 w-11 shrink-0 rounded-xl bg-slate-50"></div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-slate-900">{{ $item['title'] }}</div>
                            <div class="mt-0.5 truncate text-xs text-slate-500">Ajouté par {{ $item['by'] }}</div>
                        </div>
                        <div class="shrink-0 text-xs text-slate-500">
                            {{ optional($item['at'])->diffForHumans() }}
                        </div>
                    </a>
                @empty
                    <div class="py-6 text-sm text-slate-500">Aucun ajout pour le moment.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
 
