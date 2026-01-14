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

                $c1 = $cards[0] ?? null;
            <h1 class="text-2xl font-bold text-gray-900">Bienvenue, {{ $firstName }}</h1>
            <div class="microcopy text-sm text-slate-500 mt-1">Accès rapide aux contenus de la famille</div>
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
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Rien à signaler aujourd’hui — juste nous.
                    </div>
                @endif
            </div>
                                </div>
                            </a>
                        @endif
                    @else
                        <div class="mt-2 text-sm text-slate-600">Aucun média récent.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex items-end justify-between gap-4">
                    <div class="text-base font-semibold text-gray-900">Dernières photos</div>
                    <a href="{{ route('media.index', ['tab' => 'photos']) }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Voir tout ›</a>
                </div>

                <div class="mt-4">
                    @if(($latestImages ?? collect())->count())
                        <div class="grid grid-cols-3 gap-3">
                            @foreach(($latestImages ?? collect())->take(3) as $img)
                                <a href="{{ route('media.photos.show', ['node' => $img, 'return' => request()->getRequestUri()]) }}" class="block" aria-label="Ouvrir photo">
                                    <div class="rounded-xl overflow-hidden aspect-video bg-slate-100">
                                        <img
                                            src="{{ route('images.view', $img) }}"
                                            alt="{{ $img->name ?? 'photo' }}"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        />
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500 truncate">
                                        {{ $img->uploader?->name ?? 'Quelqu’un' }}
                                        <span class="text-slate-400">·</span>
                                        {{ $img->created_at?->diffForHumans() }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Aucune photo pour l’instant.
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex items-end justify-between gap-4">
                    <div class="text-base font-semibold text-gray-900">Dernières vidéos</div>
                    <a href="{{ route('videos.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Voir tout ›</a>
                </div>

                <div class="mt-4">
                    @if(($latestVideos ?? collect())->count())
                        <div class="grid grid-cols-3 gap-3">
                            @foreach(($latestVideos ?? collect())->take(3) as $v)
                                <a href="{{ route('videos.show', $v) }}" class="block" aria-label="Ouvrir vidéo">
                                    <div class="rounded-xl overflow-hidden aspect-video bg-slate-100 flex items-center justify-center">
                                        @if (!empty($v->poster_path))
                                            <img src="{{ route('videos.poster', $v) }}" alt="{{ $v->title }}" class="w-full h-full object-cover" loading="lazy" />
                                        @else
                                            <i class="ph ph-video text-slate-400" style="font-size:24px" aria-hidden="true"></i>
                                        @endif
                                    </div>
                                    <div class="mt-2 text-sm font-semibold text-gray-900 truncate">{{ $v->title }}</div>
                                    <div class="mt-1 text-xs text-slate-500 truncate">
                                        {{ $v->creator?->name ?? 'Quelqu’un' }}
                                        <span class="text-slate-400">·</span>
                                        {{ $v->created_at?->diffForHumans() }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
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

            
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="text-base font-semibold text-gray-900">Le petit moment de la famille</div>
            <div class="microcopy text-sm text-slate-500 mt-1">Un mini clin d’œil du jour, rien de plus.</div>

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
