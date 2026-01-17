<x-app-layout pageBgClass="fam-page-bg">
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

        $fmtRelative = function ($at): string {
            if (!($at instanceof \Carbon\CarbonInterface)) {
                return '';
            }

            try {
                $at = $at->copy()->locale(app()->getLocale());
            } catch (\Throwable $e) {
                // ignore
            }

            $now = now();
            $isFuture = $at->greaterThan($now);

            $diffMinutes = $isFuture ? $now->diffInMinutes($at) : $at->diffInMinutes($now);
            $diffHours = $isFuture ? $now->diffInHours($at) : $at->diffInHours($now);
            $diffDays = $isFuture ? $now->diffInDays($at) : $at->diffInDays($now);

            $prefix = $isFuture ? 'dans ' : 'il y a ';

            if ($diffMinutes < 60) {
                $n = max(1, (int) $diffMinutes);
                return $prefix . $n . ' min';
            }

            if ($diffHours < 48) {
                $n = max(1, (int) $diffHours);
                return $prefix . $n . ' h';
            }

            if ($diffDays < 14) {
                $n = max(1, (int) $diffDays);
                return $prefix . $n . ' j';
            }

            try {
                return $at->translatedFormat('d M');
            } catch (\Throwable $e) {
                return '';
            }
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

        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    @endverbatim

    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-3">
        @php
            $familyActivity = $familyActivity ?? [];
            $activityItems = is_array($familyActivity) ? array_slice($familyActivity, 0, 3) : [];

            /** @var array<int,array{name:string,initials:string,birthday_date:\Carbon\CarbonImmutable,days_until:int,date_label:string,age_label:string|null,profile_url:string,avatar_url:string|null}> $todayBirthdays */
            $todayBirthdays = is_array($todayBirthdays ?? null) ? $todayBirthdays : [];

            /** @var array<int,array{name:string,initials:string,birthday_date:\Carbon\CarbonImmutable,days_until:int,date_label:string,age_label:string|null,profile_url:string,avatar_url:string|null}> $upcomingBirthdays */
            $upcomingBirthdays = is_array($upcomingBirthdays ?? null) ? $upcomingBirthdays : [];

            $todayCount = count($todayBirthdays);
            $upcomingCount = count($upcomingBirthdays);
        @endphp

        @if($upcomingCount > 0)
            <section class="dash-fade">
                <div class="rounded-2xl bg-[color:var(--fam-surface)] px-3 py-3 border border-[color:var(--fam-border)] shadow-[0_1px_1px_rgba(15,23,42,0.03),0_10px_30px_rgba(15,23,42,0.06)]">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-[color:var(--fam-text)]">Anniversaires</div>
                        <a href="{{ route('birthdays.index') }}" class="-mr-1 inline-flex items-center rounded-xl px-2 py-1 text-xs font-semibold text-[color:var(--fam-primary)] hover:bg-[color:var(--fam-surface-2)] hover:text-[color:var(--fam-primary-hover)] active:bg-[color:var(--fam-tint)]">Voir tout</a>
                    </div>

                    <div class="mt-2 relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-[color:var(--fam-surface)] to-transparent"></div>
                        <div class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-[color:var(--fam-surface)] to-transparent"></div>

                        <div class="no-scrollbar overflow-x-auto snap-x snap-mandatory">
                            <div class="flex gap-2 pr-2">
                                @foreach($upcomingBirthdays as $b)
                                    @php
                                        $name = (string) ($b['name'] ?? 'Quelqu’un');
                                        $initials = (string) ($b['initials'] ?? '?');
                                        $href = (string) ($b['profile_url'] ?? route('family.index'));
                                        $avatarUrl = $b['avatar_url'] ?? null;
                                        $days = (int) ($b['days_until'] ?? 0);
                                        $dateLabel = (string) ($b['date_label'] ?? '');
                                        $ageLabel = $b['age_label'] ?? null;
                                    @endphp

                                    <a href="{{ $href }}" class="snap-start shrink-0 w-[240px] rounded-2xl bg-[color:var(--fam-surface-2)] border border-[color:var(--fam-border)] px-3 py-2.5 hover:bg-[color:var(--fam-surface)] hover:shadow-[0_1px_1px_rgba(15,23,42,0.03),0_10px_30px_rgba(15,23,42,0.06)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25" aria-label="Anniversaire de {{ $name }} dans {{ $days }} jour{{ $days > 1 ? 's' : '' }}">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="min-w-0 flex items-center gap-3">
                                                <div class="relative h-9 w-9 shrink-0">
                                                    @if(is_string($avatarUrl) && trim($avatarUrl) !== '')
                                                        <img
                                                            src="{{ $avatarUrl }}"
                                                            alt=""
                                                            class="h-9 w-9 rounded-full bg-black/5 border border-[color:var(--fam-border)] object-cover"
                                                            loading="lazy"
                                                            onerror="this.style.display='none';var fb=this.parentElement.querySelector('[data-fallback]');if(fb){fb.style.display='flex';}"
                                                        />
                                                    @endif
                                                    <div data-fallback class="h-9 w-9 rounded-full bg-[color:var(--fam-primary)] text-white flex items-center justify-center" style="{{ (is_string($avatarUrl) && trim($avatarUrl) !== '') ? 'display:none' : 'display:flex' }}">
                                                        <i class="ph ph-user text-[18px]" aria-hidden="true"></i>
                                                        <span class="sr-only">{{ $initials }}</span>
                                                    </div>
                                                </div>

                                                <div class="min-w-0">
                                                    <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $name }}</div>
                                                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)] truncate">
                                                        {{ $dateLabel }}
                                                        @if(is_string($ageLabel) && trim($ageLabel) !== '')
                                                            <span class="text-[color:var(--fam-muted)]/60">·</span> {{ $ageLabel }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="shrink-0 text-right">
                                                <div class="text-lg font-extrabold tracking-tight leading-none text-[color:var(--fam-text)]">J-{{ $days }}</div>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 dash-fade">

            <div class="rounded-2xl bg-[color:var(--fam-surface)] p-3 border border-[color:var(--fam-border)] shadow-[0_1px_1px_rgba(15,23,42,0.03),0_10px_30px_rgba(15,23,42,0.06)]">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Actu famille</div>
                    <a href="{{ route('moments.index') }}" class="-mr-2 inline-flex items-center rounded-xl px-2 py-1 text-sm font-semibold text-[color:var(--fam-primary)] hover:bg-[color:var(--fam-surface-2)] hover:text-[color:var(--fam-primary-hover)] active:bg-[color:var(--fam-tint)]">Voir tout</a>
                </div>

                @if(count($activityItems))
                    <div class="mt-2 space-y-2">
                        @foreach($activityItems as $it)
                            @php
                                $kind = (string) ($it['kind'] ?? '');
                                $href = (string) ($it['href'] ?? '#');
                                $sentence = (string) ($it['sentence'] ?? '');
                                $at = $it['at'] ?? null;
                                $when = $fmtRelative($at);

                                // Keep the UI warm: teal + neutrals only (coral reserved for urgent/notif).
                                $dot = match ($kind) {
                                    'chat' => 'bg-[color:var(--fam-primary)]',
                                    'event' => 'bg-[color:var(--fam-primary-hover)]',
                                    'actu' => 'bg-[color:var(--fam-primary)]',
                                    default => 'bg-[color:var(--fam-border)]',
                                };
                            @endphp

                            <a href="{{ $href }}" class="block">
                                <div class="group rounded-2xl bg-[color:var(--fam-surface-2)] px-3 py-2.5 border border-[color:var(--fam-border)] hover:bg-[color:var(--fam-surface)] hover:shadow-[0_1px_1px_rgba(15,23,42,0.03),0_10px_30px_rgba(15,23,42,0.06)] transition active:scale-[0.995]">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-2 h-2.5 w-2.5 rounded-full {{ $dot }}"></div>

                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-semibold text-[color:var(--fam-text)] leading-snug">
                                                {{ $sentence }}
                                            </div>
                                            @if($when !== '')
                                                <div class="mt-1 text-xs font-semibold text-[color:var(--fam-muted)]">{{ $when }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mt-2 text-sm text-[color:var(--fam-muted)]">Rien de neuf pour l’instant.</div>
                @endif
            </div>

            <div class="rounded-2xl bg-[color:var(--fam-surface)] p-3 border border-[color:var(--fam-border)] shadow-[0_1px_1px_rgba(15,23,42,0.03),0_10px_30px_rgba(15,23,42,0.06)]">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Photos récentes</div>
                    <a href="{{ route('media.index', ['tab' => 'photos']) }}" class="-mr-2 inline-flex items-center rounded-xl px-2 py-1 text-sm font-semibold text-[color:var(--fam-primary)] hover:bg-[color:var(--fam-surface-2)] hover:text-[color:var(--fam-primary-hover)] active:bg-[color:var(--fam-tint)]">Voir tout</a>
                </div>

                @php $photos = ($latestImages ?? collect())->take(6); @endphp

                @if($photos->count())
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        @foreach($photos as $img)
                            <a
                                href="{{ route('media.photos.show', ['node' => $img, 'return' => request()->getRequestUri()]) }}"
                                class="block active:scale-[0.99] transition-transform"
                                aria-label="Ouvrir photo"
                                data-shared-id="media:{{ (int) $img->id }}"
                                data-shared-src="{{ route('images.view', $img) }}"
                            >
                                <div class="aspect-square overflow-hidden rounded-2xl bg-[color:var(--fam-surface-2)] border border-[color:var(--fam-border)]" data-skel="img" data-loaded="0">
                                    <img src="{{ route('images.view', $img) }}" alt="" class="block h-full w-full object-cover opacity-0 transition-opacity duration-200" style="object-position: 50% 35%;" loading="lazy" onload="try{const w=this.closest('[data-skel=img]');if(w){w.dataset.loaded='1';this.style.opacity='1';}}catch(e){}" data-shared-id="media:{{ (int) $img->id }}" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mt-2 text-sm text-[color:var(--fam-muted)]">Aucune photo pour l’instant.</div>
                @endif
            </div>

            <div class="rounded-2xl bg-[color:var(--fam-surface)] p-3 md:col-span-2 border border-[color:var(--fam-border)] shadow-[0_1px_1px_rgba(15,23,42,0.03),0_10px_30px_rgba(15,23,42,0.06)]">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)]">Vidéos récentes</div>
                    <a href="{{ route('mediatheque.index') }}" class="-mr-2 inline-flex items-center rounded-xl px-2 py-1 text-sm font-semibold text-[color:var(--fam-primary)] hover:bg-[color:var(--fam-surface-2)] hover:text-[color:var(--fam-primary-hover)] active:bg-[color:var(--fam-tint)]">Voir tout</a>
                </div>

                @php $videos = ($latestVideos ?? collect())->take(6); @endphp

                @if($videos->count())
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        @foreach($videos as $v)
                            @php $dur = $fmtDuration($v->duration_seconds ?? null); @endphp
                            <a href="{{ route('videos.show', $v) }}" class="block" aria-label="Ouvrir vidéo">
                                <div class="relative aspect-video overflow-hidden rounded-2xl bg-[color:var(--fam-surface-2)] border border-[color:var(--fam-border)]">
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
 
