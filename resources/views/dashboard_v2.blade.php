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

    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-3">
        @php
            $nextBirthday = $nextBirthday ?? null;
            $bdDays = is_array($nextBirthday) ? (int) ($nextBirthday['days_remaining'] ?? -1) : -1;
            $bdDate = is_array($nextBirthday) ? ($nextBirthday['next_date'] ?? null) : null;
            $bdDateLabel = $bdDate ? $bdDate->locale(app()->getLocale())->translatedFormat('d M') : '';
            $bdName = is_array($nextBirthday) ? (string) ($nextBirthday['name'] ?? '') : '';
            $bdInitials = is_array($nextBirthday) ? (string) ($nextBirthday['initials'] ?? '?') : '?';
            $bdAge = is_array($nextBirthday) ? ($nextBirthday['turning_age'] ?? null) : null;
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
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
            </div>

            <div class="md:col-span-1">
                <div class="rounded-2xl border border-[#EEF0F4] bg-white p-4 md:sticky md:top-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-[#0F172A]">Prochain anniversaire</div>
                        <a href="{{ route('birthdays.index') }}" class="text-xs font-semibold text-[#0F172A] hover:underline">Voir tout</a>
                    </div>

                    @if(is_array($nextBirthday) && $bdDays >= 0 && $bdName !== '')
                        <div class="mt-3 flex items-start gap-3">
                            <div class="h-11 w-11 rounded-full bg-[#0F172A] text-white flex items-center justify-center text-sm font-bold">
                                {{ $bdInitials }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-base font-semibold text-[#0F172A] truncate">{{ $bdName }}</div>
                                <div class="mt-0.5 text-sm text-[#64748B]">
                                    @if($bdDays === 0)
                                        🎉 Aujourd’hui !
                                    @else
                                        {{ $bdDateLabel }}
                                    @endif
                                    @if(is_int($bdAge))
                                        <span class="text-[#94A3B8]">·</span> {{ $bdAge }} ans
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 rounded-2xl bg-[#F8FAFC] border border-[#EEF0F4] p-3">
                            <div class="text-[11px] font-semibold text-[#64748B]">Compteur</div>
                            <div class="mt-1 text-2xl font-extrabold tracking-tight text-[#0F172A]">
                                @if($bdDays === 0)
                                    Aujourd’hui
                                @else
                                    J-{{ $bdDays }}
                                @endif
                            </div>
                            @if($bdDays > 0)
                                <div class="mt-0.5 text-sm text-[#64748B]">dans {{ $bdDays }} jour{{ $bdDays > 1 ? 's' : '' }}</div>
                            @endif
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <a href="{{ route('birthdays.index') }}" class="inline-flex items-center justify-center rounded-xl border border-[#EEF0F4] bg-white px-3 py-2 text-sm font-semibold text-[#0F172A]">Voir les anniversaires</a>
                            @if($bdDays === 0)
                                <a href="{{ route('chat.index') }}" class="inline-flex items-center justify-center rounded-xl bg-[#0F172A] px-3 py-2 text-sm font-semibold text-white">Envoyer un message</a>
                            @endif
                        </div>
                    @else
                        <div class="mt-2 text-sm text-[#64748B]">Ajoute les dates de naissance pour afficher le prochain anniversaire.</div>
                        <div class="mt-3">
                            <a href="{{ route('birthdays.index') }}" class="inline-flex items-center justify-center rounded-xl border border-[#EEF0F4] bg-white px-3 py-2 text-sm font-semibold text-[#0F172A]">Voir les anniversaires</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3">
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-semibold text-[#0F172A]">Photos récentes</div>
                <a href="{{ route('media.index', ['tab' => 'photos']) }}" class="text-sm font-semibold text-[#0F172A] hover:underline">Voir tout</a>
            </div>

            @php $photos = ($latestImages ?? collect())->take(6); @endphp

            @if($photos->count())
                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach($photos as $img)
                        <a href="{{ route('media.photos.show', ['node' => $img, 'return' => request()->getRequestUri()]) }}" class="block" aria-label="Ouvrir photo">
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
 
