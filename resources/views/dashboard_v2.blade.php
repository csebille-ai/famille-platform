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
@php
    $userName = Auth::user()->name ?? '';
    $firstName = trim(explode(' ', trim($userName))[0] ?? $userName);

    $short = function (?string $s, int $max = 80): string {
        $s = trim((string) $s);
        if ($s === '') return '';
        if (mb_strlen($s) <= $max) return $s;
        return mb_substr($s, 0, $max - 1) . '…';
    };
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div class="py-6">
        <div class="max-w-6xl mx-auto px-6 space-y-6">
            <h1 class="text-2xl font-bold text-gray-900">Bienvenue, {{ $firstName }}</h1>
            <div class="text-sm text-slate-500 mt-1">Accès rapide aux contenus de la famille</div>
            <div class="mt-4">
                @if(!empty($c1))
                    @php
                        $kind = (string) ($c1['kind'] ?? '');
                        $href = (string) ($c1['href'] ?? '#');
                        $cta = (string) ($c1['cta'] ?? 'Voir');
                        $img = (string) ($c1['image_url'] ?? '');
                        $title = (string) ($c1['title'] ?? '');
                        $text = (string) ($c1['text'] ?? '');
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
                            <div class="text-sm font-semibold text-gray-900 leading-snug">{{ $title }}</div>
                            @if($text !== '')
                                <div class="mt-1 text-sm text-slate-600">{{ $text }}</div>
                            @endif

                            @if($cta !== '')
                                <div class="mt-3 inline-flex items-center text-sm font-semibold text-indigo-600">
                                    {{ $cta }} ›
                                </div>
                            @endif
                        </div>
                    </a>
                @else
                        <div class="mx-auto max-w-5xl space-y-4">
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
                                            @if($todayNewsItem)
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
                            Aucune vidéo pour l’instant.
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="text-base font-semibold text-gray-900">Chat</div>
                        @php $online = (int) ($chatOnlineCount ?? 0); @endphp
                        <div class="text-sm text-slate-600 inline-flex items-center gap-2">
                            <span class="{{ $online > 0 ? 'text-emerald-600' : 'text-slate-400' }}">●</span>
                            <span class="font-semibold text-gray-900">{{ $online }}</span>
                            <span class="whitespace-nowrap">connectés</span>
                        </div>
                    </div>
                    <a href="{{ route('chat.index') }}" class="shrink-0 text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Ouvrir ›</a>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex items-end justify-between gap-4">
                    <div class="text-base font-semibold text-gray-900">Derniers docs</div>
                    <a href="{{ route('resources.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Voir tout ›</a>
                </div>

                <div class="mt-4 space-y-3">
                    @if(($latestDocs ?? collect())->count())
                        @foreach(($latestDocs ?? collect())->take(3) as $r)
                            <a href="{{ route('resources.show', $r) }}" class="block rounded-xl border border-slate-200 bg-white px-4 py-3 hover:bg-slate-50">
                                <div class="text-sm font-semibold text-gray-900 truncate">{{ $r->title }}</div>
                                <div class="mt-1 text-xs text-slate-500 truncate">
                                    {{ $r->category ?: 'Document' }}
                                    <span class="text-slate-400">·</span>
                                    {{ $r->creator?->name ?? 'Quelqu’un' }}
                                    <span class="text-slate-400">·</span>
                                    {{ $r->created_at?->diffForHumans() }}
                                </div>
                            </a>
                        @endforeach
                    @else
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Aucun document pour l’instant.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2">
            <div class="overflow-x-auto">
                <div class="flex items-center gap-2 md:gap-3 min-w-max">
                    @foreach(($communLinks ?? []) as $link)
                        @php
                            $label = (string) ($link['label'] ?? '');
                            $category = (string) ($link['category'] ?? '');
                        @endphp

                        <a
                            href="{{ route('resources.index', ['user' => 'common', 'category' => $category]) }}"
                            class="h-11 px-3 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 inline-flex items-center gap-2 whitespace-nowrap"
                        >
                            @if($label === 'Urgences')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-slate-500" aria-hidden="true">
                                    <path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2z" />
                                </svg>
                            @elseif($label === 'Maison')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-slate-500" aria-hidden="true">
                                    <path d="M3 10.5L12 3l9 7.5" />
                                    <path d="M5 10v10h14V10" />
                                </svg>
                            @elseif($label === 'Voyages')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-slate-500" aria-hidden="true">
                                    <path d="M2 16l20-8-8 20-2-8-8-2z" />
                                </svg>
                            @elseif($label === 'École' || $label === 'Ecole')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-slate-500" aria-hidden="true">
                                    <path d="M3 7l9-4 9 4-9 4-9-4z" />
                                    <path d="M21 10v6" />
                                    <path d="M5 9v6c0 2 4 4 7 4s7-2 7-4V9" />
                                </svg>
                            @elseif($label === 'Administratif')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-slate-500" aria-hidden="true">
                                    <rect x="6" y="4" width="12" height="16" rx="2" />
                                    <path d="M9 8h6" />
                                    <path d="M9 12h6" />
                                    <path d="M9 16h4" />
                                </svg>
                            @elseif($label === 'Recettes')
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-slate-500" aria-hidden="true">
                                    <path d="M7 3v6a5 5 0 005 5h0a5 5 0 005-5V3" />
                                    <path d="M12 14v7" />
                                    <path d="M8 21h8" />
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-slate-500" aria-hidden="true">
                                    <path d="M4 6h16" />
                                    <path d="M4 12h16" />
                                    <path d="M4 18h16" />
                                </svg>
                            @endif

                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="text-base font-semibold text-gray-900">Le petit moment de la famille</div>
            <div class="text-sm text-slate-500 mt-1">Un mini clin d’œil du jour, rien de plus.</div>

            @php
                $cards = (array) ($familyMoments ?? []);
                $c1 = $cards[0] ?? null;
                $c2 = $cards[1] ?? null;
                $c3 = $cards[2] ?? null;
            @endphp

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                @if(!empty($c1))
                    @php
                        $kind = (string) ($c1['kind'] ?? '');
                        $href = (string) ($c1['href'] ?? '#');
                        $cta = (string) ($c1['cta'] ?? 'Voir');
                        $img = (string) ($c1['image_url'] ?? '');
                        $title = (string) ($c1['title'] ?? '');
                        $text = (string) ($c1['text'] ?? '');
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
