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
    @endphp
    <div id="{{ $idPrefix }}-cards-ui" class="space-y-4" data-tarot-cards-ui data-count="{{ $N }}" data-supports-rituel="{{ $supportsRituel ? '1' : '0' }}">
        @if(!$supportsRituel)
            <div class="fam-card p-4 text-sm text-slate-600">
                Affichage disponible uniquement pour 3 ou 5 cartes.
            </div>
        @else
            <div class="fam-card p-4">
                <div class="mt-4 -mx-4 px-4">
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
                                $kws = $normalizeKeywords($c['keywords'] ?? '');
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
                                data-kws="{{ e(json_encode($kws, JSON_UNESCAPED_UNICODE)) }}"
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
                </div>

                <div class="mt-3">
                    <button type="button" class="mx-auto block w-[clamp(240px,72vw,420px)]" data-master-btn aria-label="Ouvrir la carte en plein écran">
                        <div class="relative w-full aspect-[2/3] overflow-hidden rounded-2xl bg-white shadow-sm" style="{{ $masterMaxHeight }}">
                            <div class="absolute inset-0 flex items-center justify-center" data-master-back>
                                <div class="h-full w-full bg-gradient-to-br from-slate-50 to-slate-100"></div>
                                <div class="absolute inset-0 opacity-60" style="background-image: radial-gradient(circle at 20% 20%, rgba(14,165,160,0.18), transparent 45%), radial-gradient(circle at 80% 30%, rgba(99,102,241,0.14), transparent 50%), radial-gradient(circle at 50% 90%, rgba(244,63,94,0.10), transparent 55%);"></div>
                                <img src="/images/tarot.png" alt="" class="absolute h-12 w-12 opacity-20" loading="lazy" decoding="async" />
                            </div>
                            <img
                                src=""
                                alt=""
                                class="absolute inset-0 h-full w-full object-contain bg-transparent opacity-0 transition-opacity duration-200"
                                data-master-img
                                loading="eager"
                                decoding="async"
                            />
                        </div>
                    </button>
                </div>

                <div class="fixed inset-0 z-[70] hidden" data-zoom-modal aria-hidden="true">
                    <div class="absolute inset-0 bg-black/70" data-zoom-backdrop></div>
                    <div class="absolute inset-0 flex items-center justify-center p-4">
                        <div class="relative w-full max-w-[92vw] max-h-[92vh] rounded-2xl bg-white shadow-2xl overflow-hidden">
                            <button type="button" class="absolute right-2 top-2 z-10 rounded-full bg-black/60 text-white px-3 py-1.5 text-xs" data-zoom-close>Fermer</button>
                            <div class="w-full h-full p-3">
                                <img
                                    src=""
                                    alt=""
                                    class="h-full w-full object-contain bg-transparent"
                                    data-zoom-img
                                    loading="eager"
                                    decoding="async"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-xs font-medium text-slate-600" data-card-reminder></div>

                <div class="mt-2">
                    <div class="flex items-center gap-2">
                        <div class="text-base font-semibold text-slate-900 truncate" data-active-name></div>
                        <span class="hidden fam-chip text-xs" data-active-reversed>Renversée</span>
                    </div>
                    <div class="mt-2 flex items-center gap-2 flex-wrap" data-active-kws></div>
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

        const getCardBtnByIndex = (selector, idx) => root.querySelector(`${selector}[data-index="${idx}"]`);

        const masterBtn = root.querySelector('[data-master-btn]');
        const masterImg = root.querySelector('[data-master-img]');
        const masterBack = root.querySelector('[data-master-back]');
        const thumbs = Array.from(root.querySelectorAll('[data-thumb]'));
        const thumbsStrip = root.querySelector('[data-thumbs-strip]');
        const reminderEl = root.querySelector('[data-card-reminder]');
        const reversedBadge = root.querySelector('[data-active-reversed]');

        const zoomModal = root.querySelector('[data-zoom-modal]');
        const zoomBackdrop = root.querySelector('[data-zoom-backdrop]');
        const zoomClose = root.querySelector('[data-zoom-close]');
        const zoomImg = root.querySelector('[data-zoom-img]');

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

            const orientationLower = (orientation || '').toLowerCase();
            const isReversed = (anyBtn.getAttribute('data-reversed') === '1') || (orientationLower === 'reversed');
            if (reversedBadge) {
                reversedBadge.classList.toggle('hidden', !isReversed);
            }

            const roleFull = anyBtn.getAttribute('data-role') || '';
            if (reminderEl) {
                reminderEl.textContent = roleFull ? `Carte ${activeIndex + 1}/${count} — ${roleFull}` : `Carte ${activeIndex + 1}/${count}`;
            }

            // Master image (no crop).
            const img = anyBtn.getAttribute('data-img') || '';
            if (masterImg) {
                if (img) {
                    masterImg.src = img;
                    masterImg.style.transform = isReversed ? 'rotate(180deg)' : 'none';
                    masterImg.style.opacity = '1';
                } else {
                    masterImg.removeAttribute('src');
                    masterImg.style.opacity = '0';
                }
            }
            if (masterBack) {
                masterBack.style.display = masterImg && masterImg.style.opacity === '1' ? 'none' : 'flex';
            }
        };

        const setActiveIndex = (idx) => {
            if (!Number.isFinite(idx)) return;
            const next = Math.max(0, Math.min(count - 1, idx));
            activeIndex = next;
            syncActiveCardPanels();
            applyThumbActiveStyles();
            scrollThumbIntoView();
        };

        const applyThumbActiveStyles = () => {
            thumbs.forEach((btn) => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                const isActive = idx === activeIndex;
                const label = btn.querySelector('[data-role-label]');
                const box = btn.querySelector('[data-thumb-box]');

                btn.style.transform = isActive ? 'translateY(-1px) scale(1.02)' : 'none';
                if (box) {
                    box.style.boxShadow = isActive
                        ? '0 14px 28px rgba(15,23,42,0.14)'
                        : '0 6px 16px rgba(15,23,42,0.10)';
                }
                if (label) {
                    label.classList.toggle('text-slate-500', !isActive);
                    label.classList.toggle('text-slate-800', isActive);
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

        const openZoom = () => {
            if (!zoomModal || !zoomImg) return;
            const btn = getCardBtnByIndex('[data-thumb]', activeIndex);
            if (!btn) return;

            const img = btn.getAttribute('data-img') || '';
            const orientation = (btn.getAttribute('data-orientation') || '').toLowerCase();
            const reversed = (btn.getAttribute('data-reversed') === '1') || (orientation === 'reversed');

            zoomImg.src = img;
            zoomImg.style.transform = reversed ? 'rotate(180deg)' : 'none';

            zoomModal.classList.remove('hidden');
            zoomModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        };

        const closeZoom = () => {
            if (!zoomModal) return;
            zoomModal.classList.add('hidden');
            zoomModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        // Thumbnails: tap to set active.
        thumbs.forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-index') || '0');
                setActiveIndex(idx);
            });
        });

        // Master: swipe left/right changes active; tap opens zoom.
        if (masterBtn) {
            let startX = null;
            let startY = null;
            masterBtn.addEventListener('pointerdown', (e) => {
                startX = e.clientX;
                startY = e.clientY;
            });
            masterBtn.addEventListener('pointerup', (e) => {
                if (startX == null || startY == null) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                startX = null;
                startY = null;

                if (Math.abs(dx) >= 34 && Math.abs(dx) > Math.abs(dy)) {
                    setActiveIndex(activeIndex + (dx < 0 ? 1 : -1));
                }
            });
            masterBtn.addEventListener('click', () => {
                openZoom();
            });
        }

        if (zoomBackdrop) zoomBackdrop.addEventListener('click', closeZoom);
        if (zoomClose) zoomClose.addEventListener('click', closeZoom);
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeZoom();
        });

        const ro = (window.ResizeObserver && thumbsStrip) ? new ResizeObserver(() => {
            scrollThumbIntoView(true);
        }) : null;
        if (ro && thumbsStrip) ro.observe(thumbsStrip);

        const reveal = () => {
            // Light intro: fade the master card in, then the thumbs.
            activeIndex = 0;
            syncActiveCardPanels();
            applyThumbActiveStyles();
            scrollThumbIntoView(true);

            if (prefersReducedMotion) {
                if (masterImg) masterImg.style.opacity = '1';
                thumbs.forEach((t) => { t.style.opacity = '1'; });
                return;
            }

            thumbs.forEach((t) => { t.style.opacity = '0'; });
            if (masterImg) masterImg.style.opacity = '0';
            if (masterBack) masterBack.style.display = 'flex';

            setTimeout(() => {
                if (masterBack) masterBack.style.display = 'none';
                if (masterImg) masterImg.style.opacity = '1';
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
