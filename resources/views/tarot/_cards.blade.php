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
        $fanHeight = 'clamp(240px, 64vw, 280px)';
        $fanCardSize = $N === 5
            ? 'width: clamp(112px, 30vw, 130px); height: clamp(168px, 45vw, 195px);'
            : 'width: clamp(120px, 34vw, 140px); height: clamp(180px, 51vw, 210px);';
    @endphp
    <div id="{{ $idPrefix }}-cards-ui" class="space-y-4" data-tarot-cards-ui data-count="{{ $N }}" data-supports-rituel="{{ $supportsRituel ? '1' : '0' }}">
        @if(!$supportsRituel)
            <div class="fam-card p-4 text-sm text-slate-600">
                Rituel disponible uniquement pour 3 ou 5 cartes.
            </div>
        @else
            <div class="fam-card p-4">
                <div class="-mx-4">
                    <div class="w-full" style="height: {{ $fanHeight }};">
                        <div class="relative h-full w-full overflow-x-auto overflow-y-hidden rounded-2xl border border-black/10 bg-white" data-carousel-scene style="scroll-snap-type: x mandatory; overscroll-behavior-x: contain; -webkit-overflow-scrolling: touch;">
                            <div class="h-full w-max min-w-full flex items-center justify-center px-4" data-carousel-track>

                            @foreach($cards as $i => $c)
                                @php
                                    $file = (string) ($c['file'] ?? '');
                                    $name = (string) ($c['name'] ?? '');
                                    $slug = (string) ($c['slug'] ?? '');
                                    $n = isset($c['n']) ? (int) $c['n'] : null;
                                    $reversed = !empty($c['reversed']);
                                    $orientation = (string) ($c['orientation'] ?? ($reversed ? 'reversed' : 'upright'));
                                    $img = $file !== '' ? ('https://opanoma.fr/tarot/' . ltrim($file, '/')) : '';
                                    $kws = $normalizeKeywords($c['keywords'] ?? '');
                                @endphp
                                <button
                                    type="button"
                                    class="relative shrink-0 snap-center rounded-2xl bg-transparent shadow-sm transition-[transform,filter,box-shadow,opacity] duration-300 ease-out"
                                    style="{{ $fanCardSize }}; opacity: 0; {{ $i > 0 ? 'margin-left: -22px;' : '' }}"
                                    data-card-carousel
                                    data-index="{{ (int) $i }}"
                                    data-name="{{ e($name) }}"
                                    data-slug="{{ e($slug) }}"
                                    data-n="{{ $n === null ? '' : (string) $n }}"
                                    data-orientation="{{ e($orientation) }}"
                                    data-reversed="{{ $reversed ? '1' : '0' }}"
                                    data-kws="{{ e(json_encode($kws, JSON_UNESCAPED_UNICODE)) }}"
                                    aria-label="Choisir la carte {{ (int) $i + 1 }}"
                                >
                                    <div class="h-full w-full overflow-hidden rounded-2xl bg-transparent">
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
                        </div>
                    </div>
                </div>

                <div class="mt-4 fam-card-soft p-4">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-slate-900 truncate" data-active-name></div>
                        <div class="mt-2 flex items-center gap-2 flex-wrap" data-active-kws></div>
                        <div class="mt-3 flex items-center gap-2 flex-wrap" data-active-attrs></div>
                    </div>
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
            (kws || []).slice(0, 3).forEach((k) => {
                const chip = document.createElement('span');
                chip.className = 'fam-chip text-xs';
                chip.textContent = k;
                container.appendChild(chip);
            });
        };

        const renderAttrs = (container, attrs) => {
            if (!container) return;
            container.innerHTML = '';
            const items = [];

            const n = attrs?.n;
            const orientation = (attrs?.orientation || '').toLowerCase();
            const slug = attrs?.slug || '';

            if (Number.isFinite(n)) items.push(`Arcane #${n}`);
            if (orientation) {
                items.push(orientation === 'reversed' ? 'Renversée' : 'Droite');
            }
            if (slug) items.push(slug);

            items.forEach((t) => {
                const chip = document.createElement('span');
                chip.className = 'fam-chip text-xs';
                chip.textContent = t;
                container.appendChild(chip);
            });
        };

        const getCardBtnByIndex = (selector, idx) => root.querySelector(`${selector}[data-index="${idx}"]`);

        const syncActiveCardPanels = () => {
            const anyBtn = getCardBtnByIndex('[data-card-carousel]', activeIndex);
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
            root.querySelectorAll('[data-active-attrs]').forEach((el) => renderAttrs(el, { n, orientation, slug }));
        };

        const setActiveIndex = (idx) => {
            if (!Number.isFinite(idx)) return;
            const next = Math.max(0, Math.min(count - 1, idx));
            activeIndex = next;
            syncActiveCardPanels();
            applyActiveStyles();
            scrollActiveIntoView();
        };

        const scene = root.querySelector('[data-carousel-scene]');
        const track = root.querySelector('[data-carousel-track]');
        const cardButtons = Array.from(root.querySelectorAll('[data-card-carousel]'));

        const applyActiveStyles = () => {
            cardButtons.forEach((btn) => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                const isActive = idx === activeIndex;

                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
                btn.style.zIndex = String(isActive ? 20 : 10);
                btn.style.filter = isActive ? 'none' : 'saturate(0.94) contrast(0.99)';
                btn.style.transformOrigin = '50% 60%';
                btn.style.transform = isActive ? 'translateY(-8px) rotate(0deg) scale(1.06)' : 'translateY(0px) rotate(0deg) scale(1)';
                btn.style.boxShadow = isActive ? '0 16px 40px rgba(15,23,42,0.20)' : '0 6px 16px rgba(15,23,42,0.10)';
            });
        };

        const scrollActiveIntoView = (instant = false) => {
            const btn = getCardBtnByIndex('[data-card-carousel]', activeIndex);
            if (!btn) return;
            // If scroll snapping is supported, this is enough to perfectly center.
            try {
                btn.scrollIntoView({ behavior: instant ? 'auto' : 'smooth', inline: 'center', block: 'nearest' });
            } catch (e) {}
        };

        const updateActiveFromScroll = () => {
            if (!scene) return;
            const sceneRect = scene.getBoundingClientRect();
            const centerX = sceneRect.left + (sceneRect.width / 2);

            let bestIdx = activeIndex;
            let bestDist = Infinity;
            cardButtons.forEach((btn) => {
                const rect = btn.getBoundingClientRect();
                const btnCenterX = rect.left + (rect.width / 2);
                const dist = Math.abs(btnCenterX - centerX);
                if (dist < bestDist) {
                    bestDist = dist;
                    bestIdx = Number(btn.getAttribute('data-index') || '0');
                }
            });

            if (bestIdx !== activeIndex) {
                activeIndex = bestIdx;
                syncActiveCardPanels();
                applyActiveStyles();
            }
        };

        // Tap any card to focus it.
        cardButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                setActiveIndex(idx);
            });
        });

        // Keep active card in sync with scroll position.
        let scrollRaf = null;
        if (scene) {
            scene.addEventListener('scroll', () => {
                if (scrollRaf) return;
                scrollRaf = requestAnimationFrame(() => {
                    scrollRaf = null;
                    updateActiveFromScroll();
                });
            }, { passive: true });
        }

        const ro = (window.ResizeObserver && scene) ? new ResizeObserver(() => {
            applyActiveStyles();
            scrollActiveIntoView(true);
        }) : null;
        if (ro && scene) ro.observe(scene);

        window.addEventListener('resize', () => {
            applyActiveStyles();
            scrollActiveIntoView(true);
        });

        const revealCards = () => {
            const finalCenter = Math.floor(count / 2);

            // Start centered on the middle card.
            activeIndex = finalCenter;
            syncActiveCardPanels();
            applyActiveStyles();
            scrollActiveIntoView(true);

            if (prefersReducedMotion) {
                cardButtons.forEach((btn) => { btn.style.opacity = '1'; });
                return;
            }

            cardButtons.forEach((btn, i) => {
                btn.style.opacity = '0';
                setTimeout(() => {
                    btn.style.opacity = '1';
                }, 90 + i * 70);
            });
        };

        // Init
        requestAnimationFrame(() => revealCards());
    })();
    </script>
@endif
