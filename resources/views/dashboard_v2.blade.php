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
                <div class="text-sm font-medium text-slate-900">Photos récentes</div>
                <a href="{{ route('media.index', ['tab' => 'photos']) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-900">Voir tout</a>
            </div>

            @php
                $photos = ($latestImages ?? collect())->take(6);
            @endphp

            @if($photos->count())
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach($photos as $img)
                        <a href="{{ route('images.open', $img) }}" class="block overflow-hidden rounded-xl bg-slate-100" aria-label="Ouvrir photo">
                            <div class="aspect-square">
                                <img src="{{ route('images.view', $img) }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="mt-3 text-sm text-slate-500">Aucune photo pour l’instant.</div>
            @endif
        </div>
    </div>
</x-app-layout>
 
