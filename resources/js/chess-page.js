import { csrfToken } from './csrf'

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

const PIECES = {
    P: '♙', N: '♘', B: '♗', R: '♖', Q: '♕', K: '♔',
    p: '♟', n: '♞', b: '♝', r: '♜', q: '♛', k: '♚',
}

function squareColor(square) {
    // a1 is dark.
    const file = square.charCodeAt(0) - 96 // 'a' => 1
    const rank = parseInt(square[1], 10)
    const isDark = ((file + rank) % 2) === 0
    return isDark ? 'dark' : 'light'
}

function fenToBoardMap(fen) {
    const boardPart = String(fen || '').split(' ')[0] || ''
    const ranks = boardPart.split('/')
    if (ranks.length !== 8) return {}

    const map = {}

    for (let r = 0; r < 8; r++) {
        const rankStr = ranks[r]
        let fileIndex = 0
        for (const ch of rankStr) {
            if (/[1-8]/.test(ch)) {
                fileIndex += parseInt(ch, 10)
                continue
            }
            const file = String.fromCharCode('a'.charCodeAt(0) + fileIndex)
            const rank = 8 - r
            map[`${file}${rank}`] = ch
            fileIndex += 1
        }
    }

    return map
}

function renderBoard(root, fen, selectedFrom) {
    const el = $('#chess-board')
    if (!el) return

    const board = fenToBoardMap(fen)
    root._chessBoardMap = board

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
                'h-10 w-10 md:h-12 md:w-12',
                'flex items-center justify-center',
                'text-[22px] md:text-[26px]',
                'leading-none',
                squareColor(square) === 'dark' ? 'bg-slate-200' : 'bg-white',
                'hover:brightness-95',
                'focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--fam-primary)]/25',
                selectedFrom === square ? 'ring-2 ring-[color:var(--fam-primary)]' : '',
            ].filter(Boolean).join(' ')

            btn.textContent = piece ? (PIECES[piece] || '') : ''
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

async function loadState(root) {
    const stateUrl = root.dataset.stateUrl
    const res = await fetch(stateUrl, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('STATE_FETCH_FAILED')
    const data = await res.json()

    const fen = data?.game?.fen || ''
    const turn = data?.game?.turn || 'w'

    root.dataset.currentFen = fen

    setText($('#chess-fen'), fen || '—')
    setText($('#chess-team-w'), formatMembers(data.members, 'w'))
    setText($('#chess-team-b'), formatMembers(data.members, 'b'))
    setText($('#chess-pgn'), data?.game?.pgn || '—')

    renderBoard(root, fen, root._selectedFrom || null)

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
    await loadState(root)
}

function isPromotionMove(boardMap, from, to) {
    const piece = boardMap?.[from]
    if (!piece) return false
    const toRank = parseInt(String(to)[1], 10)
    if (piece === 'P' && toRank === 8) return true
    if (piece === 'p' && toRank === 1) return true
    return false
}

async function onBoardClick(root, square) {
    const boardMap = root._chessBoardMap || {}
    const selected = root._selectedFrom || null

    if (!selected) {
        root._selectedFrom = square
        renderBoard(root, root.dataset.currentFen || '', root._selectedFrom)
        return
    }

    if (selected === square) {
        root._selectedFrom = null
        renderBoard(root, root.dataset.currentFen || '', null)
        return
    }

    let uci = `${selected}${square}`
    if (isPromotionMove(boardMap, selected, square)) {
        const choice = (window.prompt('Promotion (q, r, b, n) ?', 'q') || 'q').trim().toLowerCase()
        const promo = ['q', 'r', 'b', 'n'].includes(choice) ? choice : 'q'
        uci += promo
    }

    root._selectedFrom = null
    renderBoard(root, root.dataset.currentFen || '', null)

    await playMoveUci(root, uci)
}

function init() {
    const root = document.getElementById('chess-show')
    if (!root) return

    $('#chess-refresh')?.addEventListener('click', () => {
        loadState(root).catch(() => showToast('Erreur de chargement.'))
    })

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
}

document.addEventListener('DOMContentLoaded', init)
