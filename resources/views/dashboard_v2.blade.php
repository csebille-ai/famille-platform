<x-app-layout pageBgClass="bg-[#F6F7F9]">
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
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-4 space-y-3">
        @if(!empty($heroMedia))
            <a href="{{ $heroMedia['href'] }}" class="block overflow-hidden rounded-2xl border border-[#EEF0F4] bg-white">
                <div class="aspect-video bg-[#F6F7F9] overflow-hidden relative">
                    @if(!empty($heroMedia['preview_url'] ?? null))
                        <img src="{{ $heroMedia['preview_url'] }}" alt="" class="h-full w-full object-cover" style="object-position: 50% 35%;" loading="lazy" />
                    @else
                        <div class="h-full w-full bg-[#F6F7F9]"></div>
                    @endif

                    <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent"></div>

                    <div class="absolute bottom-0 left-0 right-0 p-3">
                        <div class="text-xs font-medium text-white/80">Dernier média</div>
                        <div class="mt-1 truncate text-base font-semibold text-white">{{ $heroMedia['title'] }}</div>
                    </div>
                </div>
            </a>
        @else
            <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3">
                <div class="text-sm font-semibold text-[#0F172A]">Dernier média</div>
                <div class="mt-1 text-sm text-[#64748B]">Aucun média récent pour le moment.</div>
            </div>
        @endif

        <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3">
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-semibold text-[#0F172A]">Photos récentes</div>
                <a href="{{ route('media.index', ['tab' => 'photos']) }}" class="text-sm font-semibold text-[#0F172A] hover:underline">Voir tout</a>
            </div>

            @php $photos = ($latestImages ?? collect())->take(6); @endphp

            @if($photos->count())
                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach($photos as $img)
                        <a href="{{ route('images.open', $img) }}" class="block" aria-label="Ouvrir photo">
                            <div class="aspect-square overflow-hidden rounded-2xl bg-[#F6F7F9]">
                                <img src="{{ route('images.view', $img) }}" alt="" class="block h-full w-full object-cover" style="object-position: 50% 35%;" loading="lazy" />
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="mt-2 text-sm text-[#64748B]">Aucune photo pour l’instant.</div>
            @endif
        </div>

        <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3">
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-semibold text-[#0F172A]">Vidéos récentes</div>
                <a href="{{ route('mediatheque.index') }}" class="text-sm font-semibold text-[#0F172A] hover:underline">Voir tout</a>
            </div>

            @php $videos = ($latestVideos ?? collect())->take(6); @endphp

            @if($videos->count())
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach($videos as $v)
                        @php $dur = $fmtDuration($v->duration_seconds ?? null); @endphp
                        <a href="{{ route('videos.show', $v) }}" class="block" aria-label="Ouvrir vidéo">
                            <div class="relative aspect-video overflow-hidden rounded-2xl bg-[#F6F7F9]">
                                <img src="{{ route('videos.poster', $v) }}" alt="" class="block h-full w-full object-cover" style="object-position: 50% 35%;" loading="lazy" />

                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/45">
                                        <i class="ph ph-play text-white text-[20px]" aria-hidden="true"></i>
                                    </span>
                                </div>

                                @if($dur !== '')
                                    <div class="absolute top-2 right-2 rounded-xl bg-black/55 px-2 py-1 text-[0.7rem] font-semibold text-white">{{ $dur }}</div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="mt-2 text-sm text-[#64748B]">Aucune vidéo pour l’instant.</div>
            @endif
        </div>
    </div>
</x-app-layout>
 
