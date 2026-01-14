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

            $familyActivity = $familyActivity ?? [];
            $activityItems = is_array($familyActivity) ? array_slice($familyActivity, 0, 5) : [];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-1 md:col-start-3 md:row-span-3">
                <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3 md:sticky md:top-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-[#0F172A]">Prochain anniversaire</div>
                        <a href="{{ route('birthdays.index') }}" class="text-xs font-semibold text-[#0F172A] hover:underline">Voir tout</a>
                    </div>

                    @if(is_array($nextBirthday) && $bdDays >= 0 && $bdName !== '')
                        <div class="mt-2 flex items-start gap-2">
                            <div class="h-9 w-9 rounded-full bg-[#0F172A] text-white flex items-center justify-center text-xs font-bold">
                                {{ $bdInitials }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-[#0F172A] truncate">{{ $bdName }}</div>
                                <div class="mt-0.5 text-xs text-[#64748B]">
                                    @if($bdDays === 0)
                                        Aujourd’hui
                                    @else
                                        {{ $bdDateLabel }}
                                    @endif
                                    @if(is_int($bdAge))
                                        <span class="text-[#94A3B8]">·</span> {{ $bdAge }} ans
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 rounded-2xl bg-[#F8FAFC] border border-[#EEF0F4] p-2">
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="text-[10px] font-semibold text-[#64748B]">Compteur</div>
                                @if($bdDays > 0)
                                    <div class="text-[10px] font-semibold text-[#94A3B8]">dans {{ $bdDays }} jour{{ $bdDays > 1 ? 's' : '' }}</div>
                                @endif
                            </div>
                            <div class="mt-1 text-xl font-extrabold tracking-tight text-[#0F172A]">
                                @if($bdDays === 0)
                                    Aujourd’hui
                                @else
                                    J-{{ $bdDays }}
                                @endif
                            </div>
                        </div>

                        @if($bdDays === 0)
                            <div class="mt-2">
                                <a href="{{ route('chat.index') }}" class="inline-flex items-center justify-center rounded-xl bg-[#0F172A] px-3 py-2 text-sm font-semibold text-white">Envoyer un message</a>
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
                    <a href="{{ route('moments.index') }}" class="text-sm font-semibold text-[#0F172A] hover:underline">Voir tout</a>
                </div>

                @if(count($activityItems))
                    <div class="mt-2 divide-y divide-[#EEF0F4]">
                        @foreach($activityItems as $it)
                            @php
                                $kind = (string) ($it['kind'] ?? '');
                                $icon = match ($kind) {
                                    'media' => 'ph-image',
                                    'chat' => 'ph-chat-text',
                                    'event' => 'ph-calendar-blank',
                                    'actu' => 'ph-newspaper',
                                    default => 'ph-sparkle',
                                };
                                $href = (string) ($it['href'] ?? '#');
                                $title = (string) ($it['title'] ?? '');
                                $text = (string) ($it['text'] ?? '');
                            @endphp

                            <a href="{{ $href }}" class="block py-2 first:pt-0 last:pb-0">
                                <div class="flex items-start gap-3 rounded-xl px-2 py-2 -mx-2 hover:bg-[#F8FAFC]">
                                    <div class="mt-0.5 h-9 w-9 rounded-xl bg-[#F8FAFC] border border-[#EEF0F4] flex items-center justify-center text-[#0F172A]">
                                        <i class="ph {{ $icon }} text-[18px]" aria-hidden="true"></i>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-[#0F172A] truncate">{{ $title }}</div>
                                        <div class="mt-0.5 text-sm text-[#64748B] truncate">{{ $text }}</div>
                                    </div>

                                    <div class="mt-1 text-[#94A3B8]">
                                        <i class="ph ph-caret-right text-[16px]" aria-hidden="true"></i>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mt-2 text-sm text-[#64748B]">Rien de neuf pour l’instant.</div>
                @endif
            </div>

            <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3 md:col-span-2">
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

            <div class="rounded-2xl border border-[#EEF0F4] bg-white p-3 md:col-span-2">
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
    </div>
</x-app-layout>
 
