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
                <a href="{{ route('resources.index') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Ressources
                </a>
                <a href="{{ route('images.index') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Photos
                </a>
                <a href="{{ route('videos.index') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Vidéos
                </a>
                <a href="{{ route('playlists.index') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Playlists
                </a>
                <a href="{{ route('chat.index') }}" class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                    Chat
                </a>
            </div>
        </div>

        <div>
            <div class="text-base font-semibold text-gray-900">Aujourd’hui</div>
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
                                <a href="{{ route('images.open', $img) }}" class="block rounded-xl overflow-hidden aspect-video bg-slate-100" aria-label="Ouvrir photo">
                                    <img
                                        src="{{ route('images.view', $img) }}"
                                        alt="{{ $img->name ?? 'photo' }}"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                    />
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

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="text-base font-semibold text-gray-900">Commun — essentiels</div>
            <div class="text-sm text-slate-500 mt-1">Accès rapide aux dossiers Commun</div>

            <div class="mt-4 grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach(($communLinks ?? []) as $link)
                    <a href="{{ route('resources.index', ['user' => 'common', 'category' => $link['category']]) }}"
                       class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-900 flex items-center justify-center text-center">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="text-base font-semibold text-gray-900">Activité</div>
            <div class="text-sm text-slate-500 mt-1">Derniers ajouts</div>

            <div class="mt-4 space-y-2">
                @if(($activity ?? collect())->count())
                    @foreach(($activity ?? collect())->take(5) as $a)
                        <a href="{{ $a['href'] ?? '#' }}" class="block rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-gray-900 hover:bg-slate-50">
                            {{ $a['text'] ?? '' }}
                        </a>
                    @endforeach
                @else
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Aucune activité récente.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
