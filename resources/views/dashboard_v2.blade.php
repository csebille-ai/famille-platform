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

        $photos = ($latestImages ?? collect())->values();
        $videosPerso = ($personalVideos ?? collect());
        if (!($videosPerso instanceof \Illuminate\Support\Collection)) {
            $videosPerso = collect($videosPerso);
        }
        if ($videosPerso->count() === 0) {
            $videosPerso = ($latestVideos ?? collect())->values();
        }

        $chunk6 = function ($items) {
            if (!($items instanceof \Illuminate\Support\Collection)) {
                $items = collect($items);
            }
            return $items->values()->chunk(6)->values();
        };

        $photoPages = $chunk6($photos);
        $videoPages = $chunk6($videosPerso);

        $films = ($libraryFilms ?? collect());
        $series = ($librarySeries ?? collect());
        if (!($films instanceof \Illuminate\Support\Collection)) $films = collect($films);
        if (!($series instanceof \Illuminate\Support\Collection)) $series = collect($series);
        if ($films->count() === 0 && $series->count() === 0) {
            $films = ($latestVideos ?? collect())->filter(fn ($v) => ($v->category ?? null) === 'films')->values();
            $series = ($latestVideos ?? collect())->filter(fn ($v) => ($v->category ?? null) === 'series')->values();
        }
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-4 space-y-3">
        <div
            class="rounded-2xl border border-[#EEF0F4] bg-white p-3"
            x-data="{
                tab: 'photos',
                addOpen: false,
                page: 0,
                pagesCountPhotos: {{ $photoPages->count() }},
                pagesCountVideos: {{ $videoPages->count() }},
                get pagesCount() { return this.tab === 'photos' ? this.pagesCountPhotos : this.pagesCountVideos },
                scrollTo(i) {
                    const el = this.$refs.scroller;
                    if (!el) return;
                    const width = el.clientWidth;
                    el.scrollTo({ left: i * width, behavior: 'smooth' });
                },
                onScroll() {
                    const el = this.$refs.scroller;
                    if (!el) return;
                    const width = el.clientWidth || 1;
                    this.page = Math.round(el.scrollLeft / width);
                },
                closeAll() { this.addOpen = false; }
            }"
            @click.outside="closeAll()"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="text-base font-semibold text-[#0F172A]">Médias</div>

                <div class="flex items-center gap-2">
                    <div class="inline-flex items-center rounded-2xl border border-[#EEF0F4] bg-white p-1">
                        <button
                            type="button"
                            class="rounded-2xl px-3 py-2 text-[0.78rem] font-semibold transition"
                            :class="tab === 'photos' ? 'bg-[#0B1220] text-white' : 'text-[#64748B] hover:bg-[#F6F7F9]'"
                            @click="tab = 'photos'; $nextTick(() => { page = 0; $refs.scroller.scrollLeft = 0; })"
                        >
                            Photos
                        </button>
                        <button
                            type="button"
                            class="rounded-2xl px-3 py-2 text-[0.78rem] font-semibold transition"
                            :class="tab === 'videos' ? 'bg-[#0B1220] text-white' : 'text-[#64748B] hover:bg-[#F6F7F9]'"
                            @click="tab = 'videos'; $nextTick(() => { page = 0; $refs.scroller.scrollLeft = 0; })"
                        >
                            Vidéos
                        </button>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-[#EEF0F4] bg-white text-[#0F172A] hover:bg-[#F6F7F9]"
                            @click="addOpen = !addOpen"
                            aria-haspopup="menu"
                            aria-label="Ajouter"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                <path d="M12 5v14" />
                                <path d="M5 12h14" />
                            </svg>
                        </button>

                        <div
                            x-show="addOpen"
                            x-cloak
                            class="absolute right-0 mt-2 w-44 rounded-2xl border border-[#EEF0F4] bg-white p-2 shadow-sm"
                            role="menu"
                        >
                            <a href="{{ route('images.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-[#0F172A] hover:bg-[#F6F7F9]" role="menuitem">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-[#EEF0F4] bg-[#F6F7F9]">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#64748B]" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2" />
                                        <path d="M8 13l2.5-2.5L14 14l2-2 3 3" />
                                        <path d="M8.5 10.5h.01" />
                                    </svg>
                                </span>
                                <span>Photo</span>
                            </a>
                            <a href="{{ route('videos.create', ['category' => 'docs']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-[#0F172A] hover:bg-[#F6F7F9]" role="menuitem">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-[#EEF0F4] bg-[#F6F7F9]">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#64748B]" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2" />
                                        <path d="M10 9l5 3-5 3V9z" />
                                    </svg>
                                </span>
                                <span>Vidéo perso</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-2">
                <div
                    class="overflow-hidden rounded-2xl"
                >
                    <div
                        x-ref="scroller"
                        @scroll.passive="onScroll()"
                        class="flex overflow-x-auto snap-x snap-mandatory scroll-smooth"
                        style="scrollbar-width: none; -ms-overflow-style: none;"
                    >
                        <template x-if="tab === 'photos'">
                            <div class="flex w-full">
                                @foreach($photoPages as $pi => $pageItems)
                                    <div class="w-full shrink-0 snap-start">
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach($pageItems as $img)
                                                <a href="{{ route('images.open', ['node' => $img, 'return' => route('dashboard')]) }}" class="block" aria-label="Ouvrir photo">
                                                    <div class="aspect-square overflow-hidden rounded-2xl bg-[#F6F7F9]">
                                                        <img
                                                            src="{{ route('images.view', $img) }}"
                                                            alt=""
                                                            class="block h-full w-full object-cover"
                                                            style="object-position: 50% 35%;"
                                                            loading="lazy"
                                                        />
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </template>

                        <template x-if="tab === 'videos'">
                            <div class="flex w-full">
                                @foreach($videoPages as $vi => $pageItems)
                                    <div class="w-full shrink-0 snap-start">
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach($pageItems as $v)
                                                @php $dur = $fmtDuration($v->duration_seconds ?? null); @endphp
                                                <a href="{{ route('videos.show', $v) }}" class="block" aria-label="Ouvrir vidéo">
                                                    <div class="relative aspect-square overflow-hidden rounded-2xl bg-[#F6F7F9]">
                                                        <img
                                                            src="{{ route('videos.poster', $v) }}"
                                                            alt=""
                                                            class="block h-full w-full object-cover"
                                                            style="object-position: 50% 35%;"
                                                            loading="lazy"
                                                        />

                                                        <div class="absolute inset-0 flex items-center justify-center">
                                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/45">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" class="h-5 w-5" aria-hidden="true">
                                                                    <path d="M8 5v14l11-7z" />
                                                                </svg>
                                                            </span>
                                                        </div>

                                                        @if($dur !== '')
                                                            <div class="absolute top-2 right-2 rounded-xl bg-black/55 px-2 py-1 text-[0.7rem] font-semibold text-white">
                                                                {{ $dur }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </template>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-center gap-1.5">
                    <template x-for="i in pagesCount" :key="'dot_' + i">
                        <button
                            type="button"
                            class="h-1.5 w-1.5 rounded-full"
                            :class="(i - 1) === page ? 'bg-[#0B1220]' : 'bg-[#E6E8EE]'"
                            @click="scrollTo(i - 1)"
                            aria-label="Aller à la page"
                        ></button>
                    </template>
                </div>
            </div>
        </div>

        <div
            class="rounded-2xl border border-[#EEF0F4] bg-white p-3"
            x-data="{ tab: 'films', addOpen: false, closeAll() { this.addOpen = false; } }"
            @click.outside="closeAll()"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="text-base font-semibold text-[#0F172A]">Médiathèque</div>

                <div class="flex items-center gap-2">
                    <div class="inline-flex items-center rounded-2xl border border-[#EEF0F4] bg-white p-1">
                        <button
                            type="button"
                            class="rounded-2xl px-3 py-2 text-[0.78rem] font-semibold transition"
                            :class="tab === 'films' ? 'bg-[#0B1220] text-white' : 'text-[#64748B] hover:bg-[#F6F7F9]'"
                            @click="tab = 'films'"
                        >
                            Films
                        </button>
                        <button
                            type="button"
                            class="rounded-2xl px-3 py-2 text-[0.78rem] font-semibold transition"
                            :class="tab === 'series' ? 'bg-[#0B1220] text-white' : 'text-[#64748B] hover:bg-[#F6F7F9]'"
                            @click="tab = 'series'"
                        >
                            Séries
                        </button>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-2xl bg-[#0B1220] px-4 text-[0.82rem] font-semibold text-white"
                            @click="addOpen = !addOpen"
                            aria-haspopup="menu"
                        >
                            + Ajouter
                        </button>

                        <div
                            x-show="addOpen"
                            x-cloak
                            class="absolute right-0 mt-2 w-52 rounded-2xl border border-[#EEF0F4] bg-white p-2 shadow-sm"
                            role="menu"
                        >
                            <a href="{{ route('images.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-[#0F172A] hover:bg-[#F6F7F9]" role="menuitem">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-[#EEF0F4] bg-[#F6F7F9]">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#64748B]" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2" />
                                        <path d="M8 13l2.5-2.5L14 14l2-2 3 3" />
                                        <path d="M8.5 10.5h.01" />
                                    </svg>
                                </span>
                                <span>Photo</span>
                            </a>
                            <a href="{{ route('videos.create', ['category' => 'docs']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-[#0F172A] hover:bg-[#F6F7F9]" role="menuitem">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-[#EEF0F4] bg-[#F6F7F9]">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#64748B]" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2" />
                                        <path d="M10 9l5 3-5 3V9z" />
                                    </svg>
                                </span>
                                <span>Vidéo perso</span>
                            </a>
                            <a href="{{ route('videos.create', ['category' => 'films']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-[#0F172A] hover:bg-[#F6F7F9]" role="menuitem">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-[#EEF0F4] bg-[#F6F7F9]">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#64748B]" aria-hidden="true">
                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                        <path d="M7 7h0" />
                                        <path d="M7 11h0" />
                                        <path d="M7 15h0" />
                                        <path d="M11 7h0" />
                                        <path d="M11 11h0" />
                                        <path d="M11 15h0" />
                                        <path d="M15 7h0" />
                                        <path d="M15 11h0" />
                                        <path d="M15 15h0" />
                                    </svg>
                                </span>
                                <span>Ajouter un film</span>
                            </a>
                            <a href="{{ route('videos.create', ['category' => 'series']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-[#0F172A] hover:bg-[#F6F7F9]" role="menuitem">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-[#EEF0F4] bg-[#F6F7F9]">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#64748B]" aria-hidden="true">
                                        <path d="M4 7a2 2 0 012-2h5l2 2h7a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V7z" />
                                    </svg>
                                </span>
                                <span>Ajouter une série</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-2">
                <div class="grid grid-cols-2 gap-3">
                    <template x-if="tab === 'films'">
                        <div class="contents">
                            @foreach($films->take(6) as $v)
                                @php $dur = $fmtDuration($v->duration_seconds ?? null); @endphp
                                <a href="{{ route('videos.show', $v) }}" class="block" aria-label="Ouvrir film">
                                    <div class="relative overflow-hidden rounded-2xl border border-[#EEF0F4] bg-[#F6F7F9] aspect-[3/4]">
                                        <img src="{{ route('videos.poster', $v) }}" alt="" class="block h-full w-full object-cover" loading="lazy" />

                                        @if($dur !== '')
                                            <div class="absolute top-2 right-2 rounded-xl bg-black/55 px-2 py-1 text-[0.7rem] font-semibold text-white">{{ $dur }}</div>
                                        @endif

                                        <div class="absolute bottom-2 left-2 rounded-xl bg-black/55 px-2 py-1 text-[0.7rem] font-semibold text-white">Films</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </template>

                    <template x-if="tab === 'series'">
                        <div class="contents">
                            @foreach($series->take(6) as $v)
                                @php $dur = $fmtDuration($v->duration_seconds ?? null); @endphp
                                <a href="{{ route('videos.show', $v) }}" class="block" aria-label="Ouvrir série">
                                    <div class="relative overflow-hidden rounded-2xl border border-[#EEF0F4] bg-[#F6F7F9] aspect-[3/4]">
                                        <img src="{{ route('videos.poster', $v) }}" alt="" class="block h-full w-full object-cover" loading="lazy" />

                                        @if($dur !== '')
                                            <div class="absolute top-2 right-2 rounded-xl bg-black/55 px-2 py-1 text-[0.7rem] font-semibold text-white">{{ $dur }}</div>
                                        @endif

                                        <div class="absolute bottom-2 left-2 rounded-xl bg-black/55 px-2 py-1 text-[0.7rem] font-semibold text-white">Séries</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
 
