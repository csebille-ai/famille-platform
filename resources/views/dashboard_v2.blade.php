<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-5xl mx-auto px-6 py-6 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-900">Accueil</h1>
            <a href="{{ route('chat.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 hover:text-slate-900">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                <span>Chat</span>
                <span class="text-slate-500">({{ (int) ($chatOnlineCount ?? 0) }})</span>
            </a>
        </div>

        <div class="rounded-2xl bg-white px-4 py-3 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-slate-900">
                        @if(($todayNewsItem ?? null))
                            Actu du jour disponible
                        @else
                            Rien de nouveau aujourd’hui
                        @endif
                    </div>
                </div>
                <div class="shrink-0 text-xs text-slate-500">{{ now()->format('d/m') }}</div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <a href="{{ route('actu.index') }}" class="rounded-2xl bg-white p-4 shadow-sm hover:bg-slate-50">
                <div class="text-sm font-medium text-slate-900">Actu locale</div>
                <div class="mt-1 text-sm text-slate-600">{{ $todayNewsItem?->title ?? 'Voir les dernières actus' }}</div>
            </a>

            <a href="{{ route('tarot.index') }}" class="rounded-2xl bg-white p-4 shadow-sm hover:bg-slate-50">
                <div class="text-sm font-medium text-slate-900">Tarot</div>
                <div class="mt-1 text-sm text-slate-600">Tirer une carte</div>
            </a>
        </div>

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
 
