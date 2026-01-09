<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-5xl mx-auto px-6 py-6 space-y-4">
        @if(!empty($heroMedia))
            @php
                $fmtDuration = function (?int $seconds): string {
                    $s = (int) ($seconds ?? 0);
                    if ($s <= 0) return '';
                    $h = intdiv($s, 3600);
                    $m = intdiv($s % 3600, 60);
                    $sec = $s % 60;
                    if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $sec);
                    return sprintf('%d:%02d', $m, $sec);
                };
                $heroIsVideo = (($heroMedia['type'] ?? '') === 'video');
                $heroDuration = $heroIsVideo ? $fmtDuration($heroMedia['duration_seconds'] ?? null) : '';
            @endphp

            <a href="{{ $heroMedia['href'] }}" class="block overflow-hidden rounded-3xl bg-slate-900/5 hover:bg-slate-900/10">
                <div class="aspect-[16/10] bg-slate-100 overflow-hidden relative">
                    @if(!empty($heroMedia['preview_url'] ?? null))
                        <img src="{{ $heroMedia['preview_url'] }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                    @else
                        <div class="h-full w-full bg-slate-50"></div>
                    @endif

                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>

                    <div class="absolute bottom-0 left-0 right-0 p-4">
                        <div class="text-xs font-medium text-white/80">Dernier média</div>
                        <div class="mt-1 truncate text-base font-semibold text-white">{{ $heroMedia['title'] }}</div>
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
                <div class="w-full max-w-xs">
                    <div class="grid grid-cols-3 rounded-xl border border-slate-200 bg-white p-1">
                        <a
                            href="{{ route('dashboard', ['feed' => 'all']) }}"
                            class="rounded-lg px-2 py-2 text-center text-[0.72rem] font-semibold transition {{ ($feed ?? 'all') === 'all' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50' }}"
                        >
                            Tous
                        </a>
                        <a
                            href="{{ route('dashboard', ['feed' => 'photos']) }}"
                            class="rounded-lg px-2 py-2 text-center text-[0.72rem] font-semibold transition {{ ($feed ?? 'all') === 'photos' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50' }}"
                        >
                            Photos
                        </a>
                        <a
                            href="{{ route('dashboard', ['feed' => 'videos']) }}"
                            class="rounded-lg px-2 py-2 text-center text-[0.72rem] font-semibold transition {{ ($feed ?? 'all') === 'videos' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50' }}"
                        >
                            Vidéos
                        </a>
                    </div>
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
                            <div class="mt-0.5 truncate text-xs text-slate-500">
                                Ajouté par {{ $item['by'] }}
                                <span class="text-slate-400">·</span>
                                {{ optional($item['at'])->diffForHumans() }}
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="py-6 text-sm text-slate-500">Aucun ajout pour le moment.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
 
