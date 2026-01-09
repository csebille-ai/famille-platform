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
@php
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
                    <div class="overflow-x-auto -mx-1 px-1">
                        <div class="flex items-center gap-2 min-w-max">
                            <a href="{{ route('dashboard', ['feed' => 'all']) }}" class="rounded-full border px-3 py-1 text-xs font-medium {{ ($feed ?? 'all') === 'all' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300' }}">Tous</a>
                            <a href="{{ route('dashboard', ['feed' => 'photos']) }}" class="rounded-full border px-3 py-1 text-xs font-medium {{ ($feed ?? 'all') === 'photos' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300' }}">Photos</a>
                            <a href="{{ route('dashboard', ['feed' => 'videos']) }}" class="rounded-full border px-3 py-1 text-xs font-medium {{ ($feed ?? 'all') === 'videos' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300' }}">Vidéos</a>
                            <a href="{{ route('dashboard', ['feed' => 'docs']) }}" class="rounded-full border px-3 py-1 text-xs font-medium {{ ($feed ?? 'all') === 'docs' ? 'border-slate-900 text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-300' }}">Docs</a>
                        </div>
                    </div>
                </div>

                <div class="mt-3 divide-y divide-slate-100">
                    @forelse(($latestAdds ?? collect())->take(15) as $item)
                        <a href="{{ $item['href'] }}" class="flex items-center justify-between gap-3 py-3 hover:bg-slate-50">
                            <div class="min-w-0">
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
                    </a>
                @endif

                @if(!empty($c2))
                    @php
                        $kind = (string) ($c2['kind'] ?? '');
                        $href = (string) ($c2['href'] ?? '#');
                        $cta = (string) ($c2['cta'] ?? 'Voir');
                        $img = (string) ($c2['image_url'] ?? '');
                        $title = (string) ($c2['title'] ?? '');
                        $text = (string) ($c2['text'] ?? '');
                        $imgClass = $kind === 'tarot' ? 'w-full h-full object-contain bg-white' : 'w-full h-full object-cover';
                    @endphp

                    <a href="{{ $href }}" class="block rounded-2xl border border-slate-200 bg-white overflow-hidden hover:bg-slate-50">
                        @if($img !== '')
                            <div class="aspect-[16/10] bg-slate-100 overflow-hidden">
                                <img src="{{ $img }}" alt="" class="{{ $imgClass }}" loading="lazy" />
                            </div>
                        @else
                            <div class="aspect-[16/10] bg-slate-50 flex items-center justify-center">
                                <div class="h-10 w-10 rounded-2xl bg-slate-100 border border-slate-200"></div>
                            </div>
                        @endif

                        <div class="p-4">
                            <div class="text-sm font-semibold text-gray-900 leading-snug line-clamp-2">{{ $title }}</div>
                            @if($text !== '')
                                <div class="mt-1 text-sm text-slate-600 line-clamp-2">{{ $text }}</div>
                            @endif

                            <div class="mt-3 inline-flex items-center text-sm font-semibold text-indigo-600">
                                {{ $cta }} ›
                            </div>
                        </div>
                    </a>
                @endif

                @if(!empty($c3))
                    @php
                        $kind = (string) ($c3['kind'] ?? '');
                        $href = (string) ($c3['href'] ?? '#');
                        $cta = (string) ($c3['cta'] ?? 'Voir');
                        $img = (string) ($c3['image_url'] ?? '');
                        $title = (string) ($c3['title'] ?? '');
                        $text = (string) ($c3['text'] ?? '');
                        $imgClass = $kind === 'tarot' ? 'w-full h-full object-contain bg-white' : 'w-full h-full object-cover';
                    @endphp

                    <a href="{{ $href }}" class="block rounded-2xl border border-slate-200 bg-white overflow-hidden hover:bg-slate-50">
                        @if($img !== '')
                            <div class="aspect-[16/10] bg-slate-100 overflow-hidden">
                                <img src="{{ $img }}" alt="" class="{{ $imgClass }}" loading="lazy" />
                            </div>
                        @else
                            <div class="aspect-[16/10] bg-slate-50 flex items-center justify-center">
                                <div class="h-10 w-10 rounded-2xl bg-slate-100 border border-slate-200"></div>
                            </div>
                        @endif

                        <div class="p-4">
                            <div class="text-sm font-semibold text-gray-900 leading-snug line-clamp-2">{{ $title }}</div>
                            @if($text !== '')
                                <div class="mt-1 text-sm text-slate-600 line-clamp-2">{{ $text }}</div>
                            @endif

                            <div class="mt-3 inline-flex items-center text-sm font-semibold text-indigo-600">
                                {{ $cta }} ›
                            </div>
                        </div>
                    </a>
                @endif

                @if(empty($c1) && empty($c2) && empty($c3))
                    <div class="md:col-span-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Rien pour aujourd’hui. On se retrouve demain.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
