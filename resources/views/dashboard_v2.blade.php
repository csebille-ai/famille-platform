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

    @verbatim
    <style>
        @media (prefers-reduced-motion: no-preference) {
            .dash-fade { animation: dashFadeIn 180ms ease-out both; }
            @keyframes dashFadeIn { from { opacity: 0; transform: translateY(2px); } to { opacity: 1; transform: translateY(0); } }
        }

        [data-skel="img"][data-loaded="0"] { position: relative; overflow: hidden; }
        [data-skel="img"][data-loaded="0"]::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(246,247,249,1) 0%, rgba(238,240,244,1) 50%, rgba(246,247,249,1) 100%);
            background-size: 200% 100%;
            animation: dashShimmer 900ms linear infinite;
        }
        @keyframes dashShimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
    </style>
    @endverbatim

    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-3">
        @php
            $nextBirthday = $nextBirthday ?? null;
            $bdDays = is_array($nextBirthday) ? (int) ($nextBirthday['days_remaining'] ?? -1) : -1;
            $bdDate = is_array($nextBirthday) ? ($nextBirthday['next_date'] ?? null) : null;
            $bdDateLabel = $bdDate ? $bdDate->locale(app()->getLocale())->translatedFormat('d M') : '';
            $bdName = is_array($nextBirthday) ? (string) ($nextBirthday['name'] ?? '') : '';
            $bdInitials = is_array($nextBirthday) ? (string) ($nextBirthday['initials'] ?? '?') : '?';
            $bdAge = is_array($nextBirthday) ? ($nextBirthday['turning_age'] ?? null) : null;

            $familyActivity = $familyActivity ?? [];
            $activityItems = is_array($familyActivity) ? array_slice($familyActivity, 0, 3) : [];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 dash-fade">
            <div class="md:col-span-1 md:col-start-3 md:row-span-3">
                <div class="rounded-2xl bg-white p-4 md:sticky md:top-4 ring-1 ring-black/5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="text-sm font-semibold text-[#0F172A] truncate">🎂 Prochain anniversaire</div>
                            @if($bdDays === 0)
                                <span class="inline-flex items-center rounded-full bg-[#0F172A] px-2 py-0.5 text-[11px] font-semibold text-white">Aujourd’hui</span>
                            @elseif($bdDays > 0 && $bdDays <= 7)
                                <span class="inline-flex items-center rounded-full bg-[#0F172A]/10 px-2 py-0.5 text-[11px] font-semibold text-[#0F172A]">Bientôt</span>
                            @endif
                        </div>

                        <a href="{{ route('birthdays.index') }}" class="-mr-2 inline-flex items-center rounded-xl px-2 py-1 text-xs font-semibold text-[#0F172A] hover:bg-[#F8FAFC] active:bg-[#EEF0F4]">Voir tout</a>
                    </div>

                    @if(is_array($nextBirthday) && $bdDays >= 0 && $bdName !== '')
                        <div class="mt-3 flex items-start gap-3">
                            <div class="h-10 w-10 rounded-full bg-[#0F172A] text-white flex items-center justify-center text-sm font-bold">
                                {{ $bdInitials }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-base font-semibold text-[#0F172A] truncate">{{ $bdName }}</div>
                                <div class="mt-0.5 text-sm text-[#64748B]">{{ $bdDateLabel }}@if(is_int($bdAge)) <span class="text-[#94A3B8]">·</span> {{ $bdAge }} ans@endif</div>
                            </div>
                        </div>

                        <div class="mt-3 rounded-2xl bg-[#F8FAFC] p-3 ring-1 ring-black/5">
                            <div class="text-3xl font-extrabold tracking-tight text-[#0F172A]">
                                @if($bdDays === 0)
                                    Aujourd’hui 🎉
                                @else
                                    J-{{ $bdDays }}
                                @endif
                            </div>
                            @if($bdDays > 0)
                                <div class="mt-1 text-sm text-[#64748B]">dans {{ $bdDays }} jour{{ $bdDays > 1 ? 's' : '' }}</div>
                            @endif
                        </div>

                        @if($bdDays === 0)
                            <div class="mt-3">
                                <a href="{{ route('chat.index') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-[#0F172A] px-3 py-2 text-sm font-semibold text-white active:scale-[0.99] transition-transform">Envoyer un message</a>
                            </div>
                        @endif
                    @else
                        <div class="mt-2 text-sm text-[#64748B]">Ajoute les dates de naissance pour afficher le prochain anniversaire.</div>
                        <div class="mt-2">
                            <a href="{{ route('birthdays.index') }}" class="inline-flex items-center justify-center rounded-xl border border-[#EEF0F4] bg-white px-3 py-2 text-sm font-semibold text-[#0F172A]">Voir les anniversaires</a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3 md:col-span-2">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-[#0F172A]">Actu famille</div>
                    <a href="{{ route('moments.index') }}" class="-mr-2 inline-flex items-center rounded-xl px-2 py-1 text-sm font-semibold text-[#0F172A]/70 hover:bg-[#F8FAFC] active:bg-[#EEF0F4]">Voir tout</a>
                </div>

                @if(count($activityItems))
                    <div class="mt-2 space-y-2">
                        @foreach($activityItems as $it)
                            @php
                                $kind = (string) ($it['kind'] ?? '');
                                $href = (string) ($it['href'] ?? '#');
                                $sentence = (string) ($it['sentence'] ?? '');
                                $at = $it['at'] ?? null;
                                $when = '';
                                try {
                                    if ($at instanceof \Carbon\CarbonInterface) {
                                        $when = $at->copy()->locale(app()->getLocale())->diffForHumans();
                                    }
                                } catch (\Throwable $e) {
                                    $when = '';
                                }

                                $dot = match ($kind) {
                                    'chat' => 'bg-[#0F172A]',
                                    'event' => 'bg-[#2563EB]',
                                    'actu' => 'bg-[#16A34A]',
                                    default => 'bg-[#94A3B8]',
                                };
                            @endphp

                            <a href="{{ $href }}" class="block">
                                <div class="group rounded-2xl bg-[#F8FAFC] p-3 ring-1 ring-black/5 hover:bg-white hover:shadow-sm transition active:scale-[0.995]">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-2 h-2.5 w-2.5 rounded-full {{ $dot }}"></div>

                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-semibold text-[#0F172A] leading-snug">
                                                {{ $sentence }}
                                            </div>
                                            @if($when !== '')
                                                <div class="mt-1 text-xs font-semibold text-[#64748B]">{{ $when }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-3">
                        <a href="{{ route('moments.index') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-[#EEF0F4] bg-white px-3 py-2 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC] active:scale-[0.99] transition">Voir tout</a>
                    </div>
                @else
                    <div class="mt-2 text-sm text-[#64748B]">Rien de neuf pour l’instant.</div>
                @endif
            </div>

            <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3 md:col-span-2">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-[#0F172A]">Photos récentes</div>
                    <a href="{{ route('media.index', ['tab' => 'photos']) }}" class="-mr-2 inline-flex items-center rounded-xl px-2 py-1 text-sm font-semibold text-[#0F172A]/70 hover:bg-[#F8FAFC] active:bg-[#EEF0F4]">Voir tout</a>
                </div>

                @php $photos = ($latestImages ?? collect())->take(6); @endphp

                @if($photos->count())
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        @foreach($photos as $img)
                            <a href="{{ route('media.photos.show', ['node' => $img, 'return' => request()->getRequestUri()]) }}" class="block active:scale-[0.99] transition-transform" aria-label="Ouvrir photo">
                                <div class="aspect-square overflow-hidden rounded-2xl bg-[#F6F7F9] ring-1 ring-black/5" data-skel="img" data-loaded="0">
                                    <img src="{{ route('images.view', $img) }}" alt="" class="block h-full w-full object-cover opacity-0 transition-opacity duration-200" style="object-position: 50% 35%;" loading="lazy" onload="try{const w=this.closest('[data-skel=img]');if(w){w.dataset.loaded='1';this.style.opacity='1';}}catch(e){}" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mt-2 text-sm text-[#64748B]">Aucune photo pour l’instant.</div>
                @endif
            </div>

            <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3 md:col-span-2">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-[#0F172A]">Vidéos récentes</div>
                    <a href="{{ route('mediatheque.index') }}" class="-mr-2 inline-flex items-center rounded-xl px-2 py-1 text-sm font-semibold text-[#0F172A]/70 hover:bg-[#F8FAFC] active:bg-[#EEF0F4]">Voir tout</a>
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
    </div>
</x-app-layout>
 
