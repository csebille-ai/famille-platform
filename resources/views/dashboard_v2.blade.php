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
    <div class="max-w-6xl mx-auto px-6 py-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Bienvenue, {{ $firstName }}</h1>
            <div class="text-sm text-slate-500 mt-1">Accès rapide aux contenus de la famille</div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <a href="{{ route('resources.create') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Ajouter un doc
                </a>
                <a href="{{ route('images.create') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Ajouter une photo
                </a>
                <a href="{{ route('videos.create') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Ajouter une vidéo
                </a>
                <a href="{{ route('actu.index') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Lire l’actu
                </a>
                <a href="{{ route('chat.index') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Écrire sur le chat
                </a>
            </div>
        </div>

        <div>
            <div class="text-base font-semibold text-gray-900">Aujourd’hui</div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="flex items-end justify-between gap-4">
                <div class="text-base font-semibold text-gray-900">Aujourd’hui dans la famille</div>
                <a href="{{ route('dashboard') }}" class="text-sm text-slate-500">Résumé</a>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">ACTU LOCALE</div>
                    @if(!empty($todayNewsItem))
                        <a href="{{ $todayNewsItem->url }}" target="_blank" rel="noopener noreferrer" class="block mt-2 text-sm font-semibold text-gray-900 hover:underline">
                            {{ $short($todayNewsItem->title ?? '', 90) }}
                        </a>
                        @if(!empty($todayNewsItem->excerpt))
                            <div class="mt-1 text-sm text-slate-600">{{ $short($todayNewsItem->excerpt ?? '', 90) }}</div>
                        @endif
                        <div class="mt-2 text-xs text-slate-500">
                            {{ $todayNewsItem->source ?? 'Source' }}
                            @if(!empty($todayNewsItem->published_at))
                                <span class="text-slate-400">·</span>
                                {{ $todayNewsItem->published_at?->diffForHumans() }}
                            @endif
                        </div>
                    @else
                        <div class="mt-2 text-sm text-slate-600">Aucune actu pour l’instant.</div>
                        <a href="{{ route('actu.index') }}" class="mt-2 inline-flex text-sm font-semibold text-indigo-600 hover:underline">Voir l’actu ›</a>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">CHAT</div>
                    @if(!empty($lastChatMessage))
                        <a href="{{ route('chat.index') }}" class="block mt-2">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ $lastChatMessage->user?->name ?? '—' }}</div>
                            <div class="mt-1 text-sm text-slate-600">{{ $short($lastChatMessage->body ?? '', 90) }}</div>
                            <div class="mt-2 text-xs text-slate-500">{{ $lastChatMessage->created_at?->diffForHumans() }}</div>
                        </a>
                    @else
                        <div class="mt-2 text-sm text-slate-600">Aucun message pour l’instant.</div>
                        <a href="{{ route('chat.index') }}" class="mt-2 inline-flex text-sm font-semibold text-indigo-600 hover:underline">Ouvrir le chat ›</a>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">MÉDIA</div>
                    @if(!empty($todayMedia) && !empty($todayMedia['type']) && !empty($todayMedia['model']))
                        @php
                            $t = (string) $todayMedia['type'];
                            $m = $todayMedia['model'];
                        @endphp

                        @if($t === 'image')
                            <a href="{{ route('images.open', $m) }}" class="block mt-2">
                                <div class="rounded-xl overflow-hidden aspect-video bg-slate-100">
                                    <img src="{{ route('images.view', $m) }}" alt="" class="w-full h-full object-cover" loading="lazy" />
                                </div>
                                <div class="mt-2 text-sm font-semibold text-gray-900 truncate">Photo</div>
                                <div class="mt-1 text-xs text-slate-500 truncate">
                                    {{ $m->uploader?->name ?? 'Quelqu’un' }}
                                    <span class="text-slate-400">·</span>
                                    {{ $m->created_at?->diffForHumans() }}
                                </div>
                            </a>
                        @elseif($t === 'video')
                            <a href="{{ route('videos.show', $m) }}" class="block mt-2">
                                <div class="rounded-xl overflow-hidden aspect-video bg-slate-100 flex items-center justify-center">
                                    @if (!empty($m->poster_path))
                                        <img src="{{ route('videos.poster', $m) }}" alt="" class="w-full h-full object-cover" loading="lazy" />
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-slate-400" aria-hidden="true">
                                            <rect x="3" y="5" width="18" height="14" rx="2" />
                                            <path d="M10 9l5 3-5 3V9z" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="mt-2 text-sm font-semibold text-gray-900 truncate">{{ $m->title }}</div>
                                <div class="mt-1 text-xs text-slate-500 truncate">
                                    {{ $m->creator?->name ?? 'Quelqu’un' }}
                                    <span class="text-slate-400">·</span>
                                    {{ $m->created_at?->diffForHumans() }}
                                </div>
                            </a>
                        @else
                            <a href="{{ route('resources.show', $m) }}" class="block mt-2">
                                <div class="text-sm font-semibold text-gray-900 truncate">{{ $m->title }}</div>
                                <div class="mt-1 text-sm text-slate-600">Document</div>
                                <div class="mt-2 text-xs text-slate-500 truncate">
                                    {{ $m->creator?->name ?? 'Quelqu’un' }}
                                    <span class="text-slate-400">·</span>
                                    {{ $m->created_at?->diffForHumans() }}
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
                    <a href="{{ route('images.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Voir tout ›</a>
                </div>

                <div class="mt-4">
                    @if(($latestImages ?? collect())->count())
                        <div class="grid grid-cols-3 gap-3">
                            @foreach(($latestImages ?? collect())->take(3) as $img)
                                <a href="{{ route('images.open', $img) }}" class="block" aria-label="Ouvrir photo">
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
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-slate-400" aria-hidden="true">
                                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                                <path d="M10 9l5 3-5 3V9z" />
                                            </svg>
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

            <div class="bg-white rounded-2xl shadow-sm p-6">
                <div class="flex items-end justify-between gap-4">
                    <div class="text-base font-semibold text-gray-900">Chat</div>
                    <a href="{{ route('chat.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Ouvrir ›</a>
                </div>

                <div class="mt-2 text-sm text-slate-500">
                    <span class="text-emerald-600">●</span>
                    <span class="font-semibold text-gray-900">{{ (int) ($chatOnlineCount ?? 0) }}</span>
                    connectés
                </div>

                <div class="mt-4">
                    @if(!empty($lastChatMessage))
                        <a href="{{ route('chat.index') }}" class="block rounded-xl border border-slate-200 bg-white px-4 py-3 hover:bg-slate-50">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ $lastChatMessage->user?->name ?? '—' }}</div>
                            <div class="mt-1 text-sm text-slate-600">{{ $short($lastChatMessage->body ?? '', 90) }}</div>
                            <div class="mt-2 text-xs text-slate-500">{{ $lastChatMessage->created_at?->diffForHumans() }}</div>
                        </a>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Aucun message pour l’instant.
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
