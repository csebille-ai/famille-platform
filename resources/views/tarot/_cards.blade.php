@php
    /**
     * Expects:
     * - $cards: array
     * - $spread: string ('three'|'five'|...)
     * - $idPrefix: string
     */
    $cards = (array) ($cards ?? []);
    $spread = (string) ($spread ?? 'three');
    $idPrefix = (string) ($idPrefix ?? 'tarot');

    $N = count($cards);
    $supportsRituel = in_array($N, [3, 5], true);

    $normalizeKeywords = function ($raw): array {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $s = trim((string) $raw);
            if ($s === '') return [];
            $items = preg_split('/\s*(?:,|;|\||•)\s*/u', $s) ?: [];
        }
        $items = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items), fn ($v) => $v !== ''));
        return array_slice($items, 0, 3);
    };
@endphp

@if($N > 0)
    @php
        $masterMaxHeight = 'max-height: 55vh;';

        $roles = $N === 3
            ? ['Passé', 'Présent', 'Tendance']
            : ['Passé', 'Présent', 'Défi', 'Conseil', 'Issue probable'];

        $deck = (array) config('tarot.cards', []);
        $deckBySlug = [];
        $deckByN = [];
        $deckByNameKey = [];
        $deckByFileKey = [];
        $nameKey = function ($v): string {
            $s = mb_strtolower(trim((string) $v));
            // Keep it simple and resilient to punctuation / apostrophes / spaces.
            $s = preg_replace("/[^\p{L}\p{N}]+/u", '', $s) ?? $s;
            return $s;
        };
        $fileKey = function ($v): string {
            $s = trim((string) $v);
            if ($s === '') return '';
            $s = str_replace('\\', '/', $s);
            $s = basename($s);
            return mb_strtolower($s);
        };
        foreach ($deck as $dc) {
            if (is_array($dc)) {
                if (!empty($dc['slug']) && is_string($dc['slug'])) {
                    $deckBySlug[$dc['slug']] = $dc;
                }
                if (isset($dc['n']) && (is_int($dc['n']) || is_numeric($dc['n']))) {
                    $deckByN[(int) $dc['n']] = $dc;
                }
                if (!empty($dc['name']) && is_string($dc['name'])) {
                    $deckByNameKey[$nameKey($dc['name'])] = $dc;
                }
                if (!empty($dc['file']) && is_string($dc['file'])) {
                    $fk = $fileKey($dc['file']);
                    if ($fk !== '') {
                        $deckByFileKey[$fk] = $dc;
                    }
                }
            }
        }
    @endphp
    <div id="{{ $idPrefix }}-cards-ui" class="space-y-4" data-tarot-cards-ui data-count="{{ $N }}" data-supports-rituel="{{ $supportsRituel ? '1' : '0' }}">
        @if(!$supportsRituel)
            <div class="fam-card p-4 text-sm text-slate-600">
                Affichage disponible uniquement pour 3 ou 5 cartes.
            </div>
        @else
            <div class="fam-card p-4">
                <div class="mt-4 -mx-4 px-4 relative">
                    <div class="flex items-start gap-3 overflow-x-auto pb-1" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;" data-thumbs-strip>
                        @foreach($cards as $i => $c)
                            @php
                                $file = (string) ($c['file'] ?? '');
                                $name = (string) ($c['name'] ?? '');
                                $slug = (string) ($c['slug'] ?? '');
                                $n = isset($c['n']) ? (int) $c['n'] : null;
                                $reversed = !empty($c['reversed']);
                                $orientation = (string) ($c['orientation'] ?? ($reversed ? 'reversed' : 'upright'));
                                $img = $file !== '' ? ('https://opanoma.fr/tarot/' . ltrim($file, '/')) : '';

                                $deckCard = null;
                                $fk = $fileKey($file);
                                if ($fk !== '' && isset($deckByFileKey[$fk]) && is_array($deckByFileKey[$fk])) {
                                    $deckCard = $deckByFileKey[$fk];
                                } elseif ($slug !== '' && isset($deckBySlug[$slug]) && is_array($deckBySlug[$slug])) {
                                    $deckCard = $deckBySlug[$slug];
                                } elseif ($n !== null && isset($deckByN[$n]) && is_array($deckByN[$n])) {
                                    $deckCard = $deckByN[$n];
                                } elseif ($name !== '') {
                                    $nk = $nameKey($name);
                                    if ($nk !== '' && isset($deckByNameKey[$nk]) && is_array($deckByNameKey[$nk])) {
                                        $deckCard = $deckByNameKey[$nk];
                                    }
                                }
                                $deckKeywordsRaw = is_array($deckCard) ? ($deckCard['keywords'] ?? '') : '';
                                $kws = $normalizeKeywords($deckKeywordsRaw !== '' ? $deckKeywordsRaw : ($c['keywords'] ?? ''));

                                $roleFull = $roles[$i] ?? '';
                                $roleShort = ($N === 5 && (int) $i === 4) ? 'Issue' : $roleFull;
                            @endphp
                            <button
                                type="button"
                                class="relative shrink-0 rounded-xl bg-transparent transition-[transform] duration-150 ease-out focus-visible:outline-none focus-visible:shadow-[0_0_0_3px_rgba(14,165,160,0.22)]"
                                style="width: 66px; scroll-snap-align: center;"
                                data-thumb
                                data-index="{{ (int) $i }}"
                                data-img="{{ e($img) }}"
                                data-name="{{ e($name) }}"
                                data-slug="{{ e($slug) }}"
                                data-n="{{ $n === null ? '' : (string) $n }}"
                                data-orientation="{{ e($orientation) }}"
                                data-reversed="{{ $reversed ? '1' : '0' }}"
                                data-kws="{{ e(json_encode($kws, JSON_UNESCAPED_UNICODE) ?: '[]') }}"
                                data-role="{{ e($roleFull) }}"
                                data-role-short="{{ e($roleShort) }}"
                                title="{{ e($roleFull) }}"
                                aria-label="Choisir la carte {{ (int) $i + 1 }} — {{ e($roleFull) }}"
                            >
                                <div class="h-4 text-[11px] leading-4 font-medium text-slate-500 whitespace-nowrap" data-role-label>{{ $roleShort }}</div>
                                <div class="mt-1 h-[96px] w-full overflow-hidden rounded-xl bg-white shadow-[0_6px_16px_rgba(15,23,42,0.10)]" data-thumb-box>
                                    @if($img !== '')
                                        <img
                                            src="{{ $img }}"
                                            alt=""
                                            class="h-full w-full object-contain bg-transparent"
                                            style="transform: {{ $reversed ? 'rotate(180deg) scale(1.04)' : 'scale(1.04)' }}; clip-path: inset(0% 2.8%);"
                                            loading="lazy"
                                            decoding="async"
                                        />
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>

                    <div class="pointer-events-none absolute inset-y-0 left-0 w-6" style="background: linear-gradient(to right, rgba(255,255,255,1), rgba(255,255,255,0));"></div>
                    <div class="pointer-events-none absolute inset-y-0 right-0 w-6" style="background: linear-gradient(to left, rgba(255,255,255,1), rgba(255,255,255,0));"></div>
                </div>

                <div class="mt-3 -mx-4 px-4 relative" id="tarot-cards">
                    <div class="flex items-center gap-4 overflow-x-auto py-2" style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;" data-hero-strip aria-label="Cartes (carrousel)">
                        @foreach($cards as $i => $c)
                            @php
                                $file = (string) ($c['file'] ?? '');
                                $name = (string) ($c['name'] ?? '');
                                $slug = (string) ($c['slug'] ?? '');
                                $n = isset($c['n']) ? (int) $c['n'] : null;
                                $reversed = !empty($c['reversed']);
                                $orientation = (string) ($c['orientation'] ?? ($reversed ? 'reversed' : 'upright'));
                                $img = $file !== '' ? ('https://opanoma.fr/tarot/' . ltrim($file, '/')) : '';
                                $roleFull = $roles[$i] ?? '';
                            @endphp
                            <button
                                type="button"
                                class="relative shrink-0 w-[clamp(240px,72vw,420px)] focus-visible:outline-none"
                                style="scroll-snap-align: center;"
                                data-hero-slide
                                data-index="{{ (int) $i }}"
                                aria-label="Carte {{ (int) $i + 1 }} — {{ e($roleFull) }}"
                            >
                                <div class="relative w-full aspect-[2/3] overflow-hidden rounded-2xl bg-white shadow-sm" style="{{ $masterMaxHeight }}">
                                    @if($img === '')
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <div class="h-full w-full bg-gradient-to-br from-slate-50 to-slate-100"></div>
                                            <div class="absolute inset-0 opacity-60" style="background-image: radial-gradient(circle at 20% 20%, rgba(14,165,160,0.18), transparent 45%), radial-gradient(circle at 80% 30%, rgba(99,102,241,0.14), transparent 50%), radial-gradient(circle at 50% 90%, rgba(244,63,94,0.10), transparent 55%);"></div>
                                            <img src="/images/tarot.png" alt="" class="absolute h-12 w-12 opacity-20" loading="lazy" decoding="async" />
                                        </div>
                                    @else
                                        <img
                                            src="{{ $img }}"
                                            alt=""
                                            class="absolute inset-0 h-full w-full object-contain bg-transparent"
                                            style="transform: {{ ((string) $orientation) === 'reversed' || $reversed ? 'rotate(180deg)' : 'none' }};"
                                            loading="eager"
                                            decoding="async"
                                        />
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>

                    <div class="pointer-events-none absolute inset-y-0 left-0 w-7" style="background: linear-gradient(to right, rgba(255,255,255,1), rgba(255,255,255,0));"></div>
                    <div class="pointer-events-none absolute inset-y-0 right-0 w-7" style="background: linear-gradient(to left, rgba(255,255,255,1), rgba(255,255,255,0));"></div>
                </div>

                <div class="mt-3 text-xs font-medium text-slate-600" data-card-reminder></div>

                <div class="mt-2">
                    <div class="flex items-center gap-2">
                        <div class="text-base font-semibold text-slate-900 truncate" data-active-name></div>
                        <span class="hidden fam-chip text-xs" data-active-reversed>Renversée</span>
                    </div>
                    <div class="mt-2 flex items-center gap-2 flex-wrap" data-active-kws></div>
                    <div class="hidden mt-1 text-xs text-slate-500" data-active-kws-inline></div>
                </div>
            </div>
        @endif
    </div>

    <script>
    (() => {
        const root = document.getElementById(@json($idPrefix . '-cards-ui'));
        if (!root) return;

        const supportsRituel = root.getAttribute('data-supports-rituel') === '1';
        const count = Number(root.getAttribute('data-count') || '0');
        const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Active index shared.
        let activeIndex = 0;

        if (!supportsRituel || !(count === 3 || count === 5)) return;

        const renderKeywords = (container, kws) => {
            if (!container) return;
            container.innerHTML = '';

            const all = Array.isArray(kws) ? kws.filter(Boolean) : [];
            if (all.length === 0) return;

            const limit = 3;

            all.slice(0, limit).forEach((k) => {
                const chip = document.createElement('span');
                chip.className = 'fam-chip text-xs';
                chip.textContent = k;
                container.appendChild(chip);
            });
        };

        const getCardBtnByIndex = (selector, idx) => root.querySelector(`${selector}[data-index="${idx}"]`);

        const thumbs = Array.from(root.querySelectorAll('[data-thumb]'));
        const thumbsStrip = root.querySelector('[data-thumbs-strip]');
        const heroStrip = root.querySelector('[data-hero-strip]');
        const heroSlides = Array.from(root.querySelectorAll('[data-hero-slide]'));
        const reminderEl = root.querySelector('[data-card-reminder]');
        const reversedBadge = root.querySelector('[data-active-reversed]');

        const syncActiveCardPanels = () => {
            const anyBtn = getCardBtnByIndex('[data-thumb]', activeIndex);
            if (!anyBtn) return;

            const name = anyBtn.getAttribute('data-name') || '';
            const slug = anyBtn.getAttribute('data-slug') || '';
            const orientation = anyBtn.getAttribute('data-orientation') || '';
            const nRaw = anyBtn.getAttribute('data-n');
            const n = (nRaw === null || nRaw === '') ? null : Number(nRaw);
            let kws = [];
            try {
                kws = JSON.parse(anyBtn.getAttribute('data-kws') || '[]');
            } catch (e) {}

            root.querySelectorAll('[data-active-name]').forEach((el) => { el.textContent = name; });
            root.querySelectorAll('[data-active-kws]').forEach((el) => renderKeywords(el, kws));
            root.querySelectorAll('[data-active-kws-inline]').forEach((el) => {
                const list = Array.isArray(kws) ? kws.filter(Boolean).slice(0, 3) : [];
                el.textContent = list.length ? list.join(' · ') : '';
                el.classList.toggle('hidden', list.length === 0);
            });

            const orientationLower = (orientation || '').toLowerCase();
            const isReversed = (anyBtn.getAttribute('data-reversed') === '1') || (orientationLower === 'reversed');
            if (reversedBadge) {
                reversedBadge.classList.toggle('hidden', !isReversed);
            }

            const roleFull = anyBtn.getAttribute('data-role') || '';
            if (reminderEl) {
                reminderEl.textContent = roleFull ? `Carte ${activeIndex + 1}/${count} — ${roleFull}` : `Carte ${activeIndex + 1}/${count}`;
            }
        };

        const setActiveIndex = (idx) => {
            if (!Number.isFinite(idx)) return;
            const next = Math.max(0, Math.min(count - 1, idx));
            activeIndex = next;
            syncActiveCardPanels();
            applyThumbActiveStyles();
            applyHeroActiveStyles();
            scrollThumbIntoView();
            scrollHeroIntoView();
        };

        const applyHeroActiveStyles = () => {
            heroSlides.forEach((btn) => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                const isActive = idx === activeIndex;
                btn.style.opacity = isActive ? '1' : '0.62';
                btn.style.transform = isActive ? 'scale(1)' : 'scale(0.96)';
            });
        };

        const applyThumbActiveStyles = () => {
            thumbs.forEach((btn) => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                const isActive = idx === activeIndex;
                const label = btn.querySelector('[data-role-label]');
                const box = btn.querySelector('[data-thumb-box]');

                btn.style.transform = isActive ? 'translateY(-1px) scale(1.02)' : 'none';
                btn.setAttribute('aria-current', isActive ? 'true' : 'false');
                if (box) {
                    box.style.boxShadow = isActive
                        ? '0 14px 28px rgba(15,23,42,0.14)'
                        : '0 6px 16px rgba(15,23,42,0.10)';
                }
                if (label) {
                    label.classList.toggle('text-slate-500', !isActive);
                    label.style.color = isActive ? 'var(--fam-primary)' : '';
                }
            });
        };

        const scrollThumbIntoView = (instant = false) => {
            const btn = getCardBtnByIndex('[data-thumb]', activeIndex);
            if (!btn) return;
            try {
                btn.scrollIntoView({ behavior: instant ? 'auto' : 'smooth', inline: 'center', block: 'nearest' });
            } catch (e) {}
        };

        const scrollHeroIntoView = (instant = false) => {
            const btn = getCardBtnByIndex('[data-hero-slide]', activeIndex);
            if (!btn) return;
            try {
                btn.scrollIntoView({ behavior: instant ? 'auto' : 'smooth', inline: 'center', block: 'nearest' });
            } catch (e) {}
        };

        // Thumbnails: tap to set active.
        thumbs.forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                setActiveIndex(idx);
            });
        });

        // Hero: tap to focus.
        heroSlides.forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                setActiveIndex(idx);
            });
        });

        // Hero: keep activeIndex synced with native swipe/scroll.
        if (heroStrip && heroSlides.length) {
            let scrollTimer = null;
            const updateFromScroll = () => {
                const rect = heroStrip.getBoundingClientRect();
                const mid = rect.left + rect.width / 2;

                let bestIdx = activeIndex;
                let bestDist = Infinity;
                heroSlides.forEach((btn) => {
                    const r = btn.getBoundingClientRect();
                    const c = r.left + r.width / 2;
                    const d = Math.abs(c - mid);
                    if (d < bestDist) {
                        bestDist = d;
                        bestIdx = Number(btn.getAttribute('data-index') || '0');
                    }
                });

                if (Number.isFinite(bestIdx) && bestIdx !== activeIndex) {
                    // Avoid double smooth scrolling while user swipes.
                    activeIndex = bestIdx;
                    syncActiveCardPanels();
                    applyThumbActiveStyles();
                    applyHeroActiveStyles();
                    scrollThumbIntoView(true);
                }
            };

            heroStrip.addEventListener('scroll', () => {
                if (scrollTimer) clearTimeout(scrollTimer);
                scrollTimer = setTimeout(updateFromScroll, 90);
            }, { passive: true });
        }

        const ro = (window.ResizeObserver && thumbsStrip) ? new ResizeObserver(() => {
            scrollThumbIntoView(true);
        }) : null;
        if (ro && thumbsStrip) ro.observe(thumbsStrip);

        const reveal = () => {
            activeIndex = 0;
            syncActiveCardPanels();
            applyThumbActiveStyles();
            applyHeroActiveStyles();
            scrollThumbIntoView(true);
            scrollHeroIntoView(true);

            if (prefersReducedMotion) {
                thumbs.forEach((t) => { t.style.opacity = '1'; });
                return;
            }

            thumbs.forEach((t) => { t.style.opacity = '0'; });
            heroSlides.forEach((t) => { t.style.opacity = '0'; });

            setTimeout(() => {
                heroSlides.forEach((t) => { t.style.opacity = ''; });
            }, 160);

            thumbs.forEach((t, i) => {
                setTimeout(() => {
                    t.style.opacity = '1';
                }, 260 + i * 60);
            });
        };

        // Init
        requestAnimationFrame(() => reveal());
    })();
    </script>
@endif
