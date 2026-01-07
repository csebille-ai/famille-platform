@php
    $userName = Auth::user()->name ?? '';
    $firstName = trim(explode(' ', trim($userName))[0] ?? $userName);
    $avatar = method_exists(Auth::user(), 'initials') ? Auth::user()->initials() : strtoupper(substr($firstName, 0, 1));
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div class="md:hidden mx-auto max-w-[420px] px-4 pt-4 pb-24 space-y-6">
        <div>
            <div class="text-2xl font-bold text-gray-900">Bienvenue, {{ $firstName }}</div>
            <div class="text-sm text-slate-500 mt-1">Accès rapide et vie de famille</div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-4">
            <div class="grid grid-cols-4 gap-3">
                <a href="{{ route('images.index') }}" class="w-full aspect-square rounded-xl bg-emerald-50 flex flex-col items-center justify-center gap-2 text-center px-1" aria-label="Importer photo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-emerald-700" aria-hidden="true">
                        <path d="M4 7h3l2-2h6l2 2h3v12H4z" />
                        <circle cx="12" cy="13" r="3" />
                    </svg>
                    <div class="text-[11px] leading-tight font-medium text-gray-900">Importer photo</div>
                </a>

                <a href="{{ route('videos.create') }}" class="w-full aspect-square rounded-xl bg-sky-50 flex flex-col items-center justify-center gap-2 text-center px-1" aria-label="Importer vidéo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-sky-700" aria-hidden="true">
                        <path d="M15 10l4.5-2.5v9L15 14" />
                        <rect x="3" y="6" width="12" height="12" rx="2" />
                    </svg>
                    <div class="text-[11px] leading-tight font-medium text-gray-900">Importer vidéo</div>
                </a>

                <a href="{{ route('resources.create') }}" class="w-full aspect-square rounded-xl bg-amber-50 flex flex-col items-center justify-center gap-2 text-center px-1" aria-label="Ajouter ressource">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-amber-700" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <path d="M14 2v6h6" />
                        <path d="M12 12v6" />
                        <path d="M9 15h6" />
                    </svg>
                    <div class="text-[11px] leading-tight font-medium text-gray-900">Ajouter ressource</div>
                </a>

                <a href="{{ route('playlists.index') }}" class="w-full aspect-square rounded-xl bg-violet-50 flex flex-col items-center justify-center gap-2 text-center px-1" aria-label="Partager playlist">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-violet-700" aria-hidden="true">
                        <circle cx="18" cy="5" r="3" />
                        <circle cx="6" cy="12" r="3" />
                        <circle cx="18" cy="19" r="3" />
                        <path d="M8.6 13.5l6.8 3.9" />
                        <path d="M15.4 6.6L8.6 10.5" />
                    </svg>
                    <div class="text-[11px] leading-tight font-medium text-gray-900">Partager playlist</div>
                </a>
            </div>
        </div>

        <div class="flex items-end justify-between">
            <div class="text-base font-semibold text-gray-900">Prochains moments</div>
            <a href="{{ route('moments.index') }}" class="text-sm text-slate-500 hover:underline">Voir tout ›</a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-4">
            <div class="divide-y divide-slate-100">
                @foreach(($moments ?? collect())->take(3) as $m)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="flex items-start gap-3">
                            <div class="w-14 shrink-0 text-left">
                                <div class="text-sm font-semibold text-gray-900">{{ $m['date_day'] }}</div>
                                <div class="text-xs text-slate-500 -mt-0.5">{{ $m['date_month'] }}</div>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">{{ $m['title'] }}</div>
                                @if(!empty($m['subtitle']))
                                    <div class="text-sm text-slate-500">{{ $m['subtitle'] }}</div>
                                @endif
                                <div class="mt-2 h-1 rounded-full bg-slate-100">
                                    <div class="h-1 rounded-full bg-slate-200" style="width: 55%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-end justify-between">
            <div class="text-base font-semibold text-gray-900">Dernières photos</div>
            <a href="{{ route('images.index') }}" class="text-sm text-slate-500 hover:underline">Voir tout ›</a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-4">
            @if(($latestImages ?? collect())->count())
                <div class="grid grid-cols-3 gap-3">
                    @foreach(($latestImages ?? collect())->take(3) as $img)
                        <a href="{{ route('images.view', $img) }}" class="block rounded-xl overflow-hidden aspect-video bg-slate-100" aria-label="Ouvrir photo">
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
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                    Aucune photo pour l’instant.
                </div>
            @endif
        </div>
    </div>

    <div class="hidden md:block max-w-7xl mx-auto px-6 py-8 space-y-6">
        <div class="flex items-end justify-between">
            <div>
                <div class="text-2xl font-bold text-gray-900">Dashboard</div>
                <div class="text-sm text-slate-500 mt-1">Accès rapide et vie de famille</div>
            </div>
            <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-3">
                <div class="text-right">
                    <div class="text-sm font-semibold text-gray-900">{{ $firstName }}</div>
                    <div class="text-sm text-slate-500">Profil</div>
                </div>
                <div class="h-10 w-10 rounded-full bg-gray-900 text-white text-sm font-semibold flex items-center justify-center">
                    {{ $avatar }}
                </div>
            </a>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 lg:col-span-4 bg-white rounded-2xl shadow-sm p-5">
                <div class="text-base font-semibold text-gray-900">Actions rapides</div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <a href="{{ route('images.index') }}" class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-4">
                        <div class="text-sm font-semibold text-gray-900">Importer photo</div>
                        <div class="text-sm text-slate-500 mt-1">Galerie</div>
                    </a>
                    <a href="{{ route('videos.create') }}" class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-4">
                        <div class="text-sm font-semibold text-gray-900">Importer vidéo</div>
                        <div class="text-sm text-slate-500 mt-1">Vidéos</div>
                    </a>
                    <a href="{{ route('resources.create') }}" class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-4">
                        <div class="text-sm font-semibold text-gray-900">Ajouter ressource</div>
                        <div class="text-sm text-slate-500 mt-1">Documents</div>
                    </a>
                    <a href="{{ route('playlists.index') }}" class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-4">
                        <div class="text-sm font-semibold text-gray-900">Partager playlist</div>
                        <div class="text-sm text-slate-500 mt-1">Musique</div>
                    </a>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 bg-white rounded-2xl shadow-sm p-5">
                <div class="flex items-end justify-between">
                    <div class="text-base font-semibold text-gray-900">Prochains moments</div>
                    <a href="{{ route('moments.index') }}" class="text-sm text-slate-500 hover:underline">Voir tout ›</a>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach(($moments ?? collect())->take(3) as $m)
                        <div class="py-3 first:pt-0 last:pb-0">
                            <div class="flex items-start gap-3">
                                <div class="w-16 shrink-0">
                                    <div class="text-sm font-semibold text-gray-900">{{ $m['date_day'] }}</div>
                                    <div class="text-sm text-slate-500 -mt-0.5">{{ $m['date_month'] }}</div>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-gray-900">{{ $m['title'] }}</div>
                                    @if(!empty($m['subtitle']))
                                        <div class="text-sm text-slate-500">{{ $m['subtitle'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 bg-white rounded-2xl shadow-sm p-5">
                <div class="flex items-end justify-between">
                    <div class="text-base font-semibold text-gray-900">Dernières photos</div>
                    <a href="{{ route('images.index') }}" class="text-sm text-slate-500 hover:underline">Voir tout ›</a>
                </div>
                <div class="mt-4">
                    @if(($latestImages ?? collect())->count())
                        <div class="grid grid-cols-3 gap-3">
                            @foreach(($latestImages ?? collect())->take(3) as $img)
                                <a href="{{ route('images.view', $img) }}" class="block rounded-xl overflow-hidden aspect-video bg-slate-100" aria-label="Ouvrir photo">
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
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            Aucune photo pour l’instant.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
