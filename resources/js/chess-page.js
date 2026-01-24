import { csrfToken } from './csrf'
import { Chess } from 'chess.js'

function $(sel) {
    return document.querySelector(sel)
}

function postJson(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify(body ?? {}),
    })
}

function setText(el, value) {
    if (!el) return
    el.textContent = value
}

function showToast(message) {
    const wrap = $('#chess-toast')
    const msg = $('#chess-toast-msg')
    if (!wrap || !msg) return
    msg.textContent = message
    wrap.classList.remove('hidden')
    window.clearTimeout(showToast._t)
    showToast._t = window.setTimeout(() => wrap.classList.add('hidden'), 3500)
}

const PIECES_UNICODE = {
    p: '♟', r: '♜', n: '♞', b: '♝', q: '♛', k: '♚',
    P: '♙', R: '♖', N: '♘', B: '♗', Q: '♕', K: '♔',
}

function squareColor(square) {
    // a1 is dark.
    const file = square.charCodeAt(0) - 96 // 'a' => 1
    const rank = parseInt(square[1], 10)
    const isDark = ((file + rank) % 2) === 0
    return isDark ? 'dark' : 'light'
}

function pieceSvg(piece) {
    const glyph = PIECES_UNICODE[piece] || ''
    if (!glyph) return ''
    // SVG using a text node is lightweight and scales well.
    return `
<svg viewBox="0 0 100 100" width="100%" height="100%" aria-hidden="true" focusable="false">
  <text x="50" y="68" text-anchor="middle" font-size="72" font-family="system-ui, -apple-system, Segoe UI, Roboto, Arial">${glyph}</text>
</svg>`.trim()
}

function parseLastMoveFromUci(uci) {
    const s = String(uci || '').trim().toLowerCase()
    if (!/^[a-h][1-8][a-h][1-8][qrbn]?$/.test(s)) return null
    return { from: s.slice(0, 2), to: s.slice(2, 4) }
}

function setMyTeamLabel(myTeam) {
    const el = $('#chess-my-team')
    if (!el) return
    if (myTeam === 'w') el.textContent = 'Équipe Blancs'
    else if (myTeam === 'b') el.textContent = 'Équipe Noirs'
    else el.textContent = 'Spectateur'
}

function setTurnLabel(turn) {
    const el = $('#chess-turn')
    if (!el) return
    el.textContent = turn === 'w' ? 'Au tour des Blancs' : 'Au tour des Noirs'
}

function buildLegalMoveIndex(movesVerbose) {
    const byTo = new Map()
    for (const m of movesVerbose || []) {
        const to = m.to
        if (!byTo.has(to)) byTo.set(to, [])
        byTo.get(to).push(m)
    }
    return byTo
}

function renderBoard(root) {
    const el = $('#chess-board')
    if (!el) return

    const fen = root.state?.fen || ''
    let chess
    try {
        chess = new Chess(fen)
    } catch {
        chess = new Chess()
    }

    const board = {}
    for (const sq of chess.SQUARES) {
        const p = chess.get(sq)
        if (p) {
            const letter = p.color === 'w' ? p.type.toUpperCase() : p.type
            board[sq] = letter
        }
    }

    const selected = root.state?.selectedFrom || null
    const legalMoves = root.state?.legalMoves || []
    const legalIndex = buildLegalMoveIndex(legalMoves)
    const lastMove = root.state?.lastMove || null

    // Check indicator: outline the king square.
    let kingInCheckSquare = null
    try {
        if (chess.inCheck()) {
            const color = chess.turn()
            // Find the king of the side to move.
            for (const sq of chess.SQUARES) {
                const p = chess.get(sq)
                if (p && p.type === 'k' && p.color === color) {
                    kingInCheckSquare = sq
                    break
                }
            }
        }
    } catch {
        // ignore
    }

    el.innerHTML = ''

    for (let rank = 8; rank >= 1; rank--) {
        for (let f = 0; f < 8; f++) {
            const file = String.fromCharCode('a'.charCodeAt(0) + f)
            const square = `${file}${rank}`
            const piece = board[square]

            const btn = document.createElement('button')
            btn.type = 'button'
            btn.dataset.square = square
            btn.className = [
                'relative',
                'aspect-square',
                'w-full',
                'flex items-center justify-center',
                'leading-none',
                squareColor(square) === 'dark' ? 'bg-slate-200' : 'bg-white',
                'hover:brightness-95',
                'focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25',
                selected === square ? 'ring-2 ring-[color:var(--fam-primary)]' : '',
                (lastMove && (lastMove.from === square || lastMove.to === square)) ? 'outline outline-2 outline-amber-300' : '',
                (kingInCheckSquare === square) ? 'outline outline-2 outline-rose-400' : '',
            ].filter(Boolean).join(' ')

            // Piece
            const pieceWrap = document.createElement('div')
            pieceWrap.className = 'h-[85%] w-[85%] flex items-center justify-center'
            pieceWrap.innerHTML = piece ? pieceSvg(piece) : ''
            btn.appendChild(pieceWrap)

            // Legal move overlay
            const candidates = legalIndex.get(square) || []
            if (candidates.length) {
                const anyCapture = candidates.some(m => m.captured)
                const overlay = document.createElement('div')
                overlay.className = anyCapture
                    ? 'absolute inset-1 rounded-xl ring-2 ring-rose-400/70'
                    : 'absolute inset-0 flex items-center justify-center'

                if (!anyCapture) {
                    const dot = document.createElement('div')
                    dot.className = 'h-2.5 w-2.5 rounded-full bg-[color:var(--fam-primary)]/35'
                    overlay.appendChild(dot)
                }

                btn.appendChild(overlay)
            }

            btn.setAttribute('aria-label', square)
            el.appendChild(btn)
        }
    }
}

function formatMembers(members, team) {
    const list = (members || []).filter(m => m.team === team)
    const names = list.map(m => m.name || m.user?.name).filter(Boolean)
    return names.length ? names.join(', ') : '—'
}

function renderRecentMoves(moves) {
    const recent = $('#chess-recent')
    if (!recent) return
    recent.innerHTML = ''

    ;(moves || []).slice(0, 12).forEach(m => {
        const li = document.createElement('li')
        const who = m.team === 'w' ? 'Blancs' : 'Noirs'
        const by = m.played_by ? ` (${m.played_by})` : ''
        li.textContent = `${m.move_number}. ${who}${by}: ${m.san || m.uci}`
        recent.appendChild(li)
    })
}

function canInteract(root) {
    return !!root.state?.canMove
}

function clearSelection(root) {
    root.state.selectedFrom = null
    root.state.legalMoves = []
    renderBoard(root)
}

function computeLegalMoves(fen, from) {
    const chess = new Chess(fen)
    return chess.moves({ square: from, verbose: true })
}

function openPromotionModal(root, moveCandidates, from, to) {
    const modal = $('#chess-promo')
    if (!modal) return
    root.state.promo = { from, to, candidates: moveCandidates }
    modal.classList.remove('hidden')
}

function closePromotionModal(root) {
    const modal = $('#chess-promo')
    if (!modal) return
    modal.classList.add('hidden')
    root.state.promo = null
}

async function loadState(root) {
    const stateUrl = root.dataset.stateUrl
    const res = await fetch(stateUrl, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('STATE_FETCH_FAILED')
    const data = await res.json()

    const fen = data?.game?.fen || ''
    const turn = data?.game?.turn || 'w'

    root.state.fen = fen
    root.state.turn = turn
    root.state.myTeam = data?.my_team || 'spectator'
    root.state.canMove = !!data?.can_move

    // Last move highlight from server.
    const last = (data?.moves || []).slice(-1)[0]
    root.state.lastMove = last ? parseLastMoveFromUci(last.uci) : null

    root.dataset.currentFen = fen

    setText($('#chess-fen'), fen || '—')
    setText($('#chess-team-w'), formatMembers(data.members, 'w'))
    setText($('#chess-team-b'), formatMembers(data.members, 'b'))
    setText($('#chess-pgn'), data?.game?.pgn || '—')

    setTurnLabel(turn)
    setMyTeamLabel(root.state.myTeam)

    // If the game advanced, selection is no longer reliable.
    root.state.selectedFrom = null
    root.state.legalMoves = []
    renderBoard(root)

    const canMoveText = data.can_move
        ? `Tu peux jouer (tour ${turn === 'w' ? 'Blancs' : 'Noirs'}).`
        : `Tu ne peux pas jouer (tour ${turn === 'w' ? 'Blancs' : 'Noirs'}).`
    setText($('#chess-can-move'), canMoveText)

    renderRecentMoves(data.moves)

    return data
}

async function joinTeam(root, team) {
    const joinUrl = root.dataset.joinUrl
    const res = await postJson(joinUrl, { team })
    const data = await res.json().catch(() => ({}))

    if (!res.ok) {
        showToast(data.message || 'Impossible de rejoindre cette équipe.')
        return
    }

    showToast(team === 'w' ? 'Tu es dans l’équipe Blancs.' : 'Tu es dans l’équipe Noirs.')
    await loadState(root)
}

async function playMove(root) {
    return playMoveUci(root, null)
}

async function playMoveUci(root, uciOverride) {
    const moveUrl = root.dataset.moveUrl
    const uciInput = $('#chess-uci')
    const uci = String(uciOverride || (uciInput?.value || '')).trim().toLowerCase()

    if (!uci) {
        showToast('Entre un coup (ex: e2e4).')
        return
    }

    if (!canInteract(root)) {
        showToast('Tu ne peux pas jouer maintenant.')
        return
    }

    const expectedFen = root.dataset.currentFen

    const res = await postJson(moveUrl, { uci, expected_fen: expectedFen })
    const data = await res.json().catch(() => ({}))

    if (res.status === 409) {
        root.dataset.currentFen = data.current_fen || expectedFen
        setText($('#chess-fen'), root.dataset.currentFen)
        showToast('Position mise à jour (conflit). Réessaie ton coup.')
        await loadState(root)
        return
    }

    if (!res.ok) {
        showToast(data.message || 'Coup refusé.')
        return
    }

    if (uciInput) uciInput.value = ''
    showToast(`Coup joué: ${data.san || uci}`)

    try {
        if (navigator.vibrate) navigator.vibrate(12)
    } catch {}

    await loadState(root)
}

async function onBoardClick(root, square) {
    const fen = root.state?.fen || root.dataset.currentFen || ''

    // Spectators can still explore selection, but can't submit.
    const selected = root.state?.selectedFrom || null

    // Selecting a piece
    if (!selected) {
        try {
            const legal = computeLegalMoves(fen, square)
            root.state.selectedFrom = square
            root.state.legalMoves = legal
            renderBoard(root)
        } catch {
            root.state.selectedFrom = square
            root.state.legalMoves = []
            renderBoard(root)
        }
        return
    }

    // Toggle selection
    if (selected === square) {
        clearSelection(root)
        return
    }

    // Attempt a move
    const legalIndex = buildLegalMoveIndex(root.state?.legalMoves || [])
    const candidates = legalIndex.get(square) || []

    if (!candidates.length) {
        // New selection
        try {
            const legal = computeLegalMoves(fen, square)
            root.state.selectedFrom = square
            root.state.legalMoves = legal
            renderBoard(root)
        } catch {
            root.state.selectedFrom = square
            root.state.legalMoves = []
            renderBoard(root)
        }
        return
    }

    // Promotion: multiple candidates for same to.
    const promotions = candidates.filter(m => !!m.promotion)
    if (promotions.length > 1) {
        openPromotionModal(root, promotions, selected, square)
        return
    }

    const move = candidates[0]
    const uci = `${move.from}${move.to}${move.promotion || ''}`

    clearSelection(root)
    await playMoveUci(root, uci)
}

function init() {
    const root = document.getElementById('chess-show')
    if (!root) return

    root.state = {
        fen: '',
        turn: 'w',
        myTeam: 'spectator',
        canMove: false,
        selectedFrom: null,
        legalMoves: [],
        lastMove: null,
        promo: null,
    }

    $('#chess-refresh')?.addEventListener('click', () => {
        loadState(root).catch(() => showToast('Erreur de chargement.'))
    })

    $('#chess-cancel')?.addEventListener('click', () => clearSelection(root))

    $('#chess-join-w')?.addEventListener('click', () => joinTeam(root, 'w'))
    $('#chess-join-b')?.addEventListener('click', () => joinTeam(root, 'b'))

    $('#chess-play')?.addEventListener('click', () => playMove(root))
    $('#chess-uci')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault()
            playMove(root)
        }
    })

    loadState(root).catch(() => showToast('Erreur de chargement.'))

    $('#chess-board')?.addEventListener('click', (e) => {
        const btn = e.target?.closest?.('button[data-square]')
        const square = btn?.dataset?.square
        if (!square) return
        onBoardClick(root, square).catch(() => showToast('Erreur.'))
    })

    // Promotion modal
    const promo = $('#chess-promo')
    promo?.addEventListener('click', (e) => {
        const t = e.target
        if (t?.dataset?.close) {
            closePromotionModal(root)
            return
        }
        const btn = t?.closest?.('.chess-promo-btn')
        const piece = btn?.dataset?.piece
        if (!piece) return
        const st = root.state?.promo
        if (!st) return
        closePromotionModal(root)
        const uci = `${st.from}${st.to}${piece}`
        clearSelection(root)
        playMoveUci(root, uci).catch(() => showToast('Erreur.'))
    })
}

document.addEventListener('DOMContentLoaded', init)
