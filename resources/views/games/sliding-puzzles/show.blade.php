<x-app-layout pageBgClass="fam-page-bg" :pageTitle="$puzzle->title">
    <div class="max-w-2xl md:max-w-6xl mx-auto px-4 md:px-6 py-4 space-y-4">
        <div class="rounded-2xl bg-white px-3 py-3 border border-[color:var(--fam-border)] shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-[color:var(--fam-text)] truncate">{{ $puzzle->title }}</div>
                    <div class="mt-0.5 text-xs font-semibold text-[color:var(--fam-muted)]">{{ (int) $puzzle->grid_size }}×{{ (int) $puzzle->grid_size }} — glisse les tuiles jusqu’à l’image complète</div>
                </div>

                <a href="{{ route('games.sliding-puzzles.leaderboard', $puzzle) }}" class="text-xs font-semibold rounded-xl px-3 py-2 bg-[color:var(--fam-primary-100)] text-[color:var(--fam-primary)] hover:bg-[color:var(--fam-primary-100)]/75">Classement</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Temps</div>
                        <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]" id="sp-time">00:00</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Coups</div>
                        <div class="mt-1 text-sm font-semibold text-[color:var(--fam-text)]" id="sp-moves">0</div>
                    </div>
                    <button type="button" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50" id="sp-restart">Recommencer</button>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <div class="text-xs font-semibold text-[color:var(--fam-muted)]" id="sp-status">Préparation…</div>
                </div>

                <div class="mt-3">
                    <div class="w-full max-w-[420px] mx-auto">
                        <div id="sp-board" class="grid gap-1 rounded-2xl border border-[color:var(--fam-border)] bg-slate-50 p-2"></div>
                    </div>
                </div>

                <div class="mt-3 text-xs font-semibold text-[color:var(--fam-muted)]">
                    Astuce: tu peux taper sur une tuile voisine du vide.
                </div>
            </div>

            <div class="rounded-2xl bg-white p-3 border border-[color:var(--fam-border)] shadow-sm">
                <div class="text-xs font-semibold text-[color:var(--fam-muted)]">Image cible</div>

                @if($imageUrl)
                    <div class="mt-2 rounded-2xl border border-[color:var(--fam-border)] bg-slate-100 overflow-hidden">
                        <img src="{{ $imageUrl }}" alt="Image du puzzle" class="w-full h-auto block" loading="lazy" />
                    </div>
                @else
                    <div class="mt-2 rounded-2xl border border-red-200 bg-red-50 p-3 text-xs font-semibold text-red-600">
                        <i class="ph ph-warning mr-1"></i> Aucune image configurée.
                    </div>
                @endif

                @if($puzzle->description)
                    <div class="mt-3 text-xs font-semibold text-[color:var(--fam-text)]">{{ $puzzle->description }}</div>
                @endif

                <div class="mt-3">
                    <a href="{{ route('games.sliding-puzzles.index') }}" class="text-xs font-semibold rounded-xl px-3 py-2 border border-[color:var(--fam-border)] bg-white hover:bg-slate-50 inline-flex">← Tous les taquins</a>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const gridSize = Number(@json((int) $puzzle->grid_size));
                const imageUrl = @json($imageUrl);
                const startUrl = @json(route('games.sliding-puzzles.start', $puzzle));
                const finishUrlTemplate = @json(route('games.sliding-attempts.finish', ['attempt' => '__ATTEMPT__']));

                const boardEl = document.getElementById('sp-board');
                const movesEl = document.getElementById('sp-moves');
                const timeEl = document.getElementById('sp-time');
                const statusEl = document.getElementById('sp-status');
                const restartBtn = document.getElementById('sp-restart');

                const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;

                function pad2(n) { return String(n).padStart(2, '0'); }
                function formatMs(ms) {
                    const s = Math.max(0, Math.floor(ms / 1000));
                    const m = Math.floor(s / 60);
                    const r = s % 60;
                    return pad2(m) + ':' + pad2(r);
                }

                let attemptId = null;
                let startedAtMs = null;
                let timer = null;
                let moves = 0;
                let board = [];

                function solvedBoard(n) {
                    const arr = [];
                    for (let i = 1; i <= n * n - 1; i++) arr.push(i);
                    arr.push(0);
                    return arr;
                }

                function indexToRowCol(idx, n) {
                    return { r: Math.floor(idx / n), c: idx % n };
                }

                function isAdjacent(a, b, n) {
                    const A = indexToRowCol(a, n);
                    const B = indexToRowCol(b, n);
                    return (Math.abs(A.r - B.r) + Math.abs(A.c - B.c)) === 1;
                }

                function neighbors(blankIdx, n) {
                    const { r, c } = indexToRowCol(blankIdx, n);
                    const out = [];
                    if (r > 0) out.push(blankIdx - n);
                    if (r < n - 1) out.push(blankIdx + n);
                    if (c > 0) out.push(blankIdx - 1);
                    if (c < n - 1) out.push(blankIdx + 1);
                    return out;
                }

                function shuffleSolvable(n) {
                    board = solvedBoard(n);
                    let blankIdx = board.indexOf(0);
                    let prevBlankIdx = null;

                    const steps = n === 3 ? 200 : 800;
                    for (let i = 0; i < steps; i++) {
                        const opts = neighbors(blankIdx, n).filter(x => x !== prevBlankIdx);
                        const pick = opts[Math.floor(Math.random() * opts.length)];
                        prevBlankIdx = blankIdx;
                        board[blankIdx] = board[pick];
                        board[pick] = 0;
                        blankIdx = pick;
                    }

                    if (isSolved(n)) {
                        // One extra move to avoid instant win.
                        const pick = neighbors(blankIdx, n)[0];
                        board[blankIdx] = board[pick];
                        board[pick] = 0;
                    }
                }

                function isSolved(n) {
                    for (let i = 0; i < n * n - 1; i++) {
                        if (board[i] !== i + 1) return false;
                    }
                    return board[n * n - 1] === 0;
                }

                function render(n) {
                    boardEl.style.gridTemplateColumns = `repeat(${n}, minmax(0, 1fr))`;
                    boardEl.innerHTML = '';

                    for (let idx = 0; idx < board.length; idx++) {
                        const value = board[idx];
                        const tile = document.createElement('button');
                        tile.type = 'button';
                        tile.className = 'aspect-square rounded-xl border border-[color:var(--fam-border)] bg-white text-[color:var(--fam-text)] font-semibold text-sm flex items-center justify-center select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25';

                        if (value === 0) {
                            tile.disabled = true;
                            tile.className = 'aspect-square rounded-xl border border-[color:var(--fam-border)] bg-slate-100';
                        } else {
                            if (imageUrl) {
                                const origIdx = value - 1;
                                const orig = indexToRowCol(origIdx, n);
                                const x = (n === 1) ? 0 : (orig.c / (n - 1)) * 100;
                                const y = (n === 1) ? 0 : (orig.r / (n - 1)) * 100;
                                tile.style.backgroundImage = `url(${imageUrl})`;
                                tile.style.backgroundSize = `${n * 100}% ${n * 100}%`;
                                tile.style.backgroundPosition = `${x}% ${y}%`;
                                tile.textContent = '';
                            } else {
                                tile.textContent = String(value);
                            }

                            tile.addEventListener('click', () => {
                                const blankIdx = board.indexOf(0);
                                if (!isAdjacent(idx, blankIdx, n)) return;

                                board[blankIdx] = value;
                                board[idx] = 0;
                                moves++;
                                movesEl.textContent = String(moves);
                                render(n);

                                if (isSolved(n)) {
                                    onWin();
                                }
                            });
                        }

                        boardEl.appendChild(tile);
                    }
                }

                function setStatus(text) {
                    statusEl.textContent = text;
                }

                function startTimer() {
                    if (timer) clearInterval(timer);
                    timer = setInterval(() => {
                        if (!startedAtMs) return;
                        timeEl.textContent = formatMs(Date.now() - startedAtMs);
                    }, 250);
                }

                async function startAttempt() {
                    setStatus('Démarrage…');
                    attemptId = null;
                    startedAtMs = null;

                    const res = await fetch(startUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        body: new URLSearchParams({})
                    });

                    if (!res.ok) {
                        throw new Error('start_failed');
                    }

                    const data = await res.json();
                    if (!data || !data.ok || !data.attempt_id) {
                        throw new Error('start_invalid');
                    }

                    attemptId = Number(data.attempt_id);
                    startedAtMs = Date.now();

                    setStatus('Bonne chance !');
                    startTimer();
                }

                async function finishAttempt() {
                    if (!attemptId || !startedAtMs) return;

                    setStatus('Enregistrement…');

                    const durationMs = Math.max(0, Math.floor(Date.now() - startedAtMs));
                    const finishUrl = finishUrlTemplate.replace('__ATTEMPT__', String(attemptId));

                    const res = await fetch(finishUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                        },
                        body: new URLSearchParams({
                            moves_count: String(moves),
                            duration_ms: String(durationMs),
                        })
                    });

                    if (!res.ok) {
                        setStatus('Erreur lors de l’enregistrement.');
                        return;
                    }

                    const data = await res.json();
                    if (data && data.ok && data.redirect_url) {
                        window.location.href = data.redirect_url;
                        return;
                    }

                    setStatus('Terminé !');
                }

                async function resetGame() {
                    setStatus('Mélange…');
                    moves = 0;
                    movesEl.textContent = '0';
                    timeEl.textContent = '00:00';

                    shuffleSolvable(gridSize);
                    render(gridSize);

                    try {
                        await startAttempt();
                    } catch (e) {
                        setStatus('Impossible de démarrer.');
                    }
                }

                function onWin() {
                    if (timer) clearInterval(timer);
                    setStatus('Bravo !');
                    finishAttempt();
                }

                restartBtn.addEventListener('click', () => {
                    resetGame();
                });

                resetGame();
            })();
        </script>
    @endpush
</x-app-layout>
