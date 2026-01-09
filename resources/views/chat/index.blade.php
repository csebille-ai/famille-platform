<x-app-layout pageBgClass="bg-slate-50">
    @php
        $palette = [
            ['chip' => 'bg-indigo-50 text-indigo-700 border-indigo-200', 'avatar' => 'bg-indigo-600 text-white'],
            ['chip' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'avatar' => 'bg-emerald-600 text-white'],
            ['chip' => 'bg-amber-50 text-amber-800 border-amber-200', 'avatar' => 'bg-amber-600 text-white'],
            ['chip' => 'bg-rose-50 text-rose-700 border-rose-200', 'avatar' => 'bg-rose-600 text-white'],
            ['chip' => 'bg-sky-50 text-sky-700 border-sky-200', 'avatar' => 'bg-sky-600 text-white'],
            ['chip' => 'bg-violet-50 text-violet-700 border-violet-200', 'avatar' => 'bg-violet-600 text-white'],
        ];

        $paletteFor = function (?int $userId) use ($palette) {
            if (!$userId) return $palette[0];
            return $palette[$userId % count($palette)];
        };

        $initialsFor = function (?string $name) {
            $name = trim((string) $name);
            if ($name === '') return '—';
            $parts = preg_split('/\s+/', $name);
            $first = $parts[0] ?? '';
            $last = $parts[count($parts) - 1] ?? '';
            $initials = mb_substr($first, 0, 1);
            if ($last && $last !== $first) {
                $initials .= mb_substr($last, 0, 1);
            }
            return mb_strtoupper($initials);
        };

        $firstNameFor = function (?string $name) {
            $name = trim((string) $name);
            if ($name === '') return '—';
            $parts = preg_split('/\s+/', $name);
            return $parts[0] ?? $name;
        };

        $onlineList = collect($initialOnline ?? [])
            ->map(function ($u) {
                $id = data_get($u, 'id') ?? data_get($u, 'user_id') ?? data_get($u, 'user.id');
                $name = data_get($u, 'name') ?? data_get($u, 'user.name');
                return ['id' => $id ? (int) $id : null, 'name' => $name ?: '—'];
            })
            ->filter(fn ($u) => !empty($u['id']))
            ->values();

        if (auth()->check()) {
            $onlineList = $onlineList->prepend([
                'id' => (int) auth()->id(),
                'name' => auth()->user()?->name ?? 'Vous',
            ]);
        }

        $onlineList = $onlineList->unique('id')->values();
    @endphp

    <div class="max-w-6xl mx-auto px-6 py-6 space-y-6">
        @if (session('status'))
            <div class="bg-white rounded-2xl shadow-sm p-4 text-sm text-gray-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <div class="text-sm font-semibold text-red-600">Erreur</div>
                <ul class="mt-2 space-y-1 text-sm text-red-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                <div>
                    <div class="text-base font-semibold text-gray-900">💬 Chat familial</div>
                    <div class="text-sm text-slate-500">
                        <span class="text-emerald-600">●</span>
                        <span id="chatOnlineCount" class="font-semibold text-gray-900">{{ $onlineList->count() }}</span>
                        connectés
                    </div>
                </div>
                <div id="chatOnlineAvatars" class="flex items-center -space-x-2">
                    @foreach($onlineList->take(6) as $u)
                        @php
                            $uid = (int) ($u['id'] ?? 0);
                            $uname = (string) ($u['name'] ?? '—');
                            $colors = $paletteFor($uid);
                        @endphp
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold border-2 border-white {{ $colors['avatar'] }}" title="{{ $uname }}">
                            {{ $initialsFor($uname) }}
                        </div>
                    @endforeach
                </div>
            </div>

            <div id="chatScroll" class="max-h-[70vh] md:max-h-[60vh] overflow-y-auto">
                <div id="chatMessages" class="flex flex-col gap-3 p-4">
                    @if(($messages ?? collect())->count() === 0)
                        <div id="chatEmptyState" class="py-12 text-center">
                            <div class="text-base font-semibold text-gray-900">👋 Aucun message pour l’instant</div>
                            <div class="text-sm text-slate-500 mt-1">Lance la discussion !</div>
                        </div>
                    @else
                        @php
                            $prevDay = null;
                        @endphp
                        @foreach ($messages as $i => $m)
                            @php
                                $userId = (int) $m->user_id;
                                $name = $m->user?->name ?? '—';
                                $isMe = auth()->check() && (int) auth()->id() === (int) $userId;
                                $colors = $paletteFor($userId);
                                $initials = $initialsFor($name);
                                $firstName = $firstNameFor($name);

                                $prev = $messages[$i - 1] ?? null;
                                $next = $messages[$i + 1] ?? null;
                                $prevUserId = $prev ? (int) $prev->user_id : null;
                                $nextUserId = $next ? (int) $next->user_id : null;
                                $isGroupStart = $prevUserId !== $userId;
                                $isGroupEnd = $nextUserId !== $userId;

                                $dayKey = $m->created_at?->format('Y-m-d') ?? null;
                                $dayLabel = $m->created_at?->format('d/m/Y') ?? '';
                            @endphp

                            @if($dayKey && $dayKey !== $prevDay)
                                <div class="py-2 flex justify-center">
                                    <div class="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-full px-3 py-1">
                                        {{ $dayLabel }}
                                    </div>
                                </div>
                                @php
                                    $prevDay = $dayKey;
                                @endphp
                            @endif

                            <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }}" data-message-row data-user-id="{{ $userId }}" data-message-id="{{ $m->id }}" data-day-key="{{ $dayKey }}">
                                <div class="max-w-[85%] sm:max-w-[75%]">
                                    @if($isGroupStart)
                                        <div class="mb-1 text-xs text-slate-500 {{ $isMe ? 'text-right' : '' }}">
                                            {{ $firstName }} · {{ $m->created_at?->format('d/m') }}
                                        </div>
                                    @endif

                                    <div class="flex items-end gap-2 {{ $isMe ? 'flex-row-reverse' : '' }}">
                                        <div class="shrink-0 {{ $isGroupEnd ? '' : 'invisible' }}" data-avatar>
                                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center text-xs font-semibold {{ $colors['avatar'] }}">
                                                {{ $initials }}
                                            </div>
                                        </div>

                                        <div class="px-4 py-3 border {{ $isMe ? 'bg-slate-900 text-white border-slate-900 rounded-2xl rounded-br-md' : 'bg-white text-gray-900 border-slate-200 rounded-2xl rounded-bl-md' }}" data-bubble>
                                            <div class="text-sm whitespace-pre-wrap">{{ $m->body }}</div>
                                            <div class="mt-1 text-right text-xs opacity-60">{{ $m->created_at?->format('H:i') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="border-t border-slate-100 bg-white sticky bottom-0">
                <div class="px-4 py-3">
                    <form id="chatForm" method="POST" action="{{ route('chat.store') }}">
                        @csrf
                        <div id="chatGate" class="hidden mb-2 text-sm text-slate-600"></div>
                        <div id="chatVoiceStatus" class="hidden mb-2 text-sm text-slate-600"></div>

                        <div class="flex items-end gap-2">
                            <div class="flex-1 rounded-2xl border border-slate-200 bg-white px-3 py-2">
                                <textarea
                                    id="body"
                                    name="body"
                                    rows="1"
                                    class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm"
                                    placeholder="Écris ton message…"
                                    required
                                >{{ old('body') }}</textarea>
                            </div>

                            <button
                                type="button"
                                id="chatVoiceBtn"
                                class="border border-slate-200 bg-white text-slate-700 rounded-2xl px-4 py-3 text-sm font-semibold"
                                aria-label="Dicter le message"
                                title="Dicter le message"
                            >
                                🎙️
                            </button>

                            <button
                                type="submit"
                                class="bg-slate-900 text-white rounded-2xl px-4 py-3 text-sm font-semibold"
                                aria-label="Envoyer"
                            >
                                ➤
                            </button>
                        </div>

                        <x-input-error class="mt-2" :messages="$errors->get('body')" />
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const scrollEl = document.getElementById('chatScroll');
            const messagesEl = document.getElementById('chatMessages');
            const emptyEl = document.getElementById('chatEmptyState');
            const onlineCountEl = document.getElementById('chatOnlineCount');
            const onlineAvatarsEl = document.getElementById('chatOnlineAvatars');
            const formEl = document.getElementById('chatForm');
            const textareaEl = document.getElementById('body');
            const currentUserId = @json(auth()->id());
            const currentUserName = @json(auth()->user()?->name);
            const pollUrl = @json(route('chat.poll'));
            let lastMessageId = @json($lastMessageId ?? 0);
            const initialOnline = @json($initialOnline ?? []);

            const gateEl = document.getElementById('chatGate');
            const voiceStatusEl = document.getElementById('chatVoiceStatus');
            const voiceBtn = document.getElementById('chatVoiceBtn');
            const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
            let recognition = null;
            let dictationActive = false;
            let dictationBase = '';
            let dictationInterim = '';

            function setVoiceStatus(message, options) {
                if (!voiceStatusEl) return;
                const msg = String(message || '').trim();
                if (!msg) {
                    voiceStatusEl.textContent = '';
                    voiceStatusEl.className = 'hidden mb-2 text-sm text-slate-600';
                    return;
                }
                voiceStatusEl.textContent = msg;
                voiceStatusEl.className = 'mb-2 text-sm text-slate-600';

                const autoHideMs = Number(options?.autoHideMs ?? 0);
                if (autoHideMs > 0) {
                    setTimeout(() => {
                        if (voiceStatusEl.textContent === msg) {
                            setVoiceStatus('');
                        }
                    }, autoHideMs);
                }
            }

            function setDictationUi(active) {
                dictationActive = !!active;
                if (!voiceBtn) return;
                voiceBtn.setAttribute('aria-pressed', dictationActive ? 'true' : 'false');
                voiceBtn.classList.toggle('bg-slate-900', dictationActive);
                voiceBtn.classList.toggle('text-white', dictationActive);
                voiceBtn.classList.toggle('border-slate-900', dictationActive);
                voiceBtn.classList.toggle('bg-white', !dictationActive);
                voiceBtn.classList.toggle('text-slate-700', !dictationActive);
                voiceBtn.classList.toggle('border-slate-200', !dictationActive);
                setVoiceStatus(dictationActive ? '🎙️ Dictée en cours…' : '');
            }

            function syncVoiceAvailability() {
                if (!voiceBtn) return;
                const supported = !!SpeechRecognitionCtor;
                const allowedByGate = !textareaEl?.disabled;

                voiceBtn.disabled = !supported || !allowedByGate;
                voiceBtn.classList.toggle('opacity-50', voiceBtn.disabled);
                voiceBtn.classList.toggle('cursor-not-allowed', voiceBtn.disabled);
                voiceBtn.title = !supported
                    ? 'Dictée vocale non supportée par ce navigateur'
                    : (allowedByGate ? 'Dicter le message' : 'Chat désactivé (dictée indisponible)');

                if (!supported) {
                    setVoiceStatus('');
                }
            }

            const palette = [
                { chip: 'bg-indigo-50 text-indigo-700 border-indigo-200', avatar: 'bg-indigo-600 text-white' },
                { chip: 'bg-emerald-50 text-emerald-700 border-emerald-200', avatar: 'bg-emerald-600 text-white' },
                { chip: 'bg-amber-50 text-amber-800 border-amber-200', avatar: 'bg-amber-600 text-white' },
                { chip: 'bg-rose-50 text-rose-700 border-rose-200', avatar: 'bg-rose-600 text-white' },
                { chip: 'bg-sky-50 text-sky-700 border-sky-200', avatar: 'bg-sky-600 text-white' },
                { chip: 'bg-violet-50 text-violet-700 border-violet-200', avatar: 'bg-violet-600 text-white' },
            ];

            function paletteFor(userId) {
                const id = Number(userId ?? 0);
                return palette[Math.abs(id) % palette.length] ?? palette[0];
            }

            function initialsFor(name) {
                const n = String(name ?? '').trim();
                if (!n) return '—';
                const parts = n.split(/\s+/).filter(Boolean);
                const first = parts[0] ?? '';
                const last = parts[parts.length - 1] ?? '';
                let ini = first ? first.slice(0, 1) : '';
                if (last && last !== first) ini += last.slice(0, 1);
                return ini.toUpperCase();
            }

            function focusLastMessage() {
                const lastRow = messagesEl?.querySelector('[data-message-row]:last-child');
                if (!lastRow) return;

                // iOS Safari can be finicky with programmatic scrolling; do both.
                const run = () => {
                    try {
                        lastRow.scrollIntoView({ block: 'end' });
                    } catch {
                        // ignore
                    }
                    if (scrollEl) {
                        scrollEl.scrollTop = scrollEl.scrollHeight;
                    }
                };

                requestAnimationFrame(run);
                setTimeout(run, 80);
            }

            focusLastMessage();

            function hideEmptyState() {
                if (!emptyEl) return;
                emptyEl.classList.add('hidden');
            }

            function renderOnline(users) {
                let list = Array.isArray(users) ? [...users] : [];

                if (currentUserId) {
                    const hasMe = list.some(u => {
                        const id = userId(u);
                        return id != null && Number(id) === Number(currentUserId);
                    });

                    if (!hasMe) {
                        list.unshift({ id: currentUserId, name: currentUserName || 'Vous' });
                    }
                }

                const count = list.length;

                if (onlineCountEl) {
                    onlineCountEl.textContent = String(count);
                }

                if (!onlineAvatarsEl) return;
                onlineAvatarsEl.innerHTML = '';
                if (count === 0) {
                    return;
                }

                list.slice(0, 6).forEach(u => {
                    const id = userId(u);
                    const name = userName(u);
                    const colors = paletteFor(id);

                    const dot = document.createElement('div');
                    dot.className = `w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold border-2 border-white ${colors.avatar}`;
                    dot.title = name;
                    dot.textContent = initialsFor(name);
                    onlineAvatarsEl.appendChild(dot);
                });
            }

            function updateGate(onlineCount) {
                const count = Number(onlineCount ?? 0);
                const ok = count >= 2;

                if (textareaEl) {
                    textareaEl.disabled = !ok;
                    textareaEl.placeholder = ok
                        ? 'Écris ton message…'
                        : 'Attends qu’au moins 2 personnes soient connectées…';
                }

                const btn = formEl?.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = !ok;
                    btn.classList.toggle('opacity-50', !ok);
                    btn.classList.toggle('cursor-not-allowed', !ok);
                }

                if (gateEl) {
                    gateEl.textContent = ok ? '' : 'Chat désactivé : il faut au moins 2 connectés.';
                    gateEl.className = ok ? 'hidden mb-2 text-sm text-slate-600' : 'mb-2 text-sm text-slate-600';
                }

                syncVoiceAvailability();
            }

            function dayKeyFromISO(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return '';
                return d.toISOString().slice(0, 10);
            }

            function dayLabelFromISO(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return '';
                return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }

            function timeLabelFromISO(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return '';
                return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
            }

            function firstName(name) {
                const n = String(name ?? '').trim();
                if (!n) return '—';
                return n.split(/\s+/)[0] || n;
            }

            function shortDay(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return '';
                return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
            }

            let lastDayKey = (function initLastDayFromDom() {
                if (!messagesEl) return '';
                const rows = messagesEl.querySelectorAll('[data-message-row]');
                const last = rows[rows.length - 1];
                return last?.dataset?.dayKey || '';
            })();

            function appendDaySeparator(dayKey, label) {
                if (!messagesEl || !dayKey || dayKey === lastDayKey) return;

                const sep = document.createElement('div');
                sep.className = 'py-2 flex justify-center';
                sep.dataset.daySeparator = '1';
                sep.dataset.dayKey = dayKey;

                const pill = document.createElement('div');
                pill.className = 'text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-full px-3 py-1';
                pill.textContent = label;

                sep.appendChild(pill);
                messagesEl.appendChild(sep);
                lastDayKey = dayKey;
            }

            function appendMessage(payload) {
                if (!messagesEl) return;
                const id = payload?.id ?? null;
                if (id != null && messagesEl.querySelector(`[data-message-id="${id}"]`)) {
                    return false;
                }
                const uid = payload?.user?.id ?? payload?.user_id ?? null;
                const name = payload?.user?.name ?? '—';
                const body = payload?.body ?? '';
                const createdISO = payload?.created_at ?? null;
                const whenTime = createdISO ? timeLabelFromISO(createdISO) : '';

                const dk = createdISO ? dayKeyFromISO(createdISO) : '';
                const dl = createdISO ? dayLabelFromISO(createdISO) : '';
                appendDaySeparator(dk, dl);

                const isMe = currentUserId && uid && Number(uid) === Number(currentUserId);
                const colors = paletteFor(uid);
                const initials = initialsFor(name);

                hideEmptyState();

                // Grouping: same author as previous message => hide meta + move avatar to new last message.
                const rows = messagesEl.querySelectorAll('[data-message-row]');
                const lastRow = rows[rows.length - 1] || null;
                const lastUserId = lastRow ? Number(lastRow.dataset.userId || 0) : null;
                const sameAuthorAsPrev = lastRow && uid != null && Number(uid) === Number(lastUserId);

                if (sameAuthorAsPrev) {
                    const lastAvatar = lastRow.querySelector('[data-avatar]');
                    if (lastAvatar) {
                        lastAvatar.classList.add('invisible');
                    }
                }

                const outer = document.createElement('div');
                outer.className = `flex ${isMe ? 'justify-end' : 'justify-start'}`;
                outer.dataset.messageRow = '1';
                outer.dataset.userId = uid != null ? String(uid) : '';
                outer.dataset.dayKey = dk;
                if (id != null) outer.dataset.messageId = String(id);

                const width = document.createElement('div');
                width.className = 'max-w-[85%] sm:max-w-[75%]';

                if (!sameAuthorAsPrev) {
                    const meta = document.createElement('div');
                    meta.className = `mb-1 text-xs text-slate-500 ${isMe ? 'text-right' : ''}`;
                    meta.textContent = `${firstName(name)} · ${createdISO ? shortDay(createdISO) : ''}`;
                    width.appendChild(meta);
                }

                const row = document.createElement('div');
                row.className = `flex items-end gap-2 ${isMe ? 'flex-row-reverse' : ''}`;

                const avatarWrap = document.createElement('div');
                avatarWrap.className = 'shrink-0';
                avatarWrap.dataset.avatar = '1';
                const avatar = document.createElement('div');
                avatar.className = `w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center text-xs font-semibold ${colors.avatar}`;
                avatar.textContent = initials;
                avatarWrap.appendChild(avatar);

                const wrapper = document.createElement('div');
                wrapper.className = `px-4 py-3 border ${isMe ? 'bg-slate-900 text-white border-slate-900 rounded-2xl rounded-br-md' : 'bg-white text-gray-900 border-slate-200 rounded-2xl rounded-bl-md'}`;

                const bodyEl = document.createElement('div');
                bodyEl.className = 'text-sm whitespace-pre-wrap';
                bodyEl.textContent = body;

                const timeEl = document.createElement('div');
                timeEl.className = 'mt-1 text-right text-xs opacity-60';
                timeEl.textContent = whenTime;

                wrapper.appendChild(bodyEl);
                wrapper.appendChild(timeEl);

                row.appendChild(avatarWrap);
                row.appendChild(wrapper);
                width.appendChild(row);
                outer.appendChild(width);
                messagesEl.appendChild(outer);
                focusLastMessage();
                return true;
            }

            const online = new Map();

            function normalizeUsers(users) {
                if (!users) return [];
                if (Array.isArray(users)) return users;
                if (typeof users === 'object') return Object.values(users);
                return [];
            }

            function userId(u) {
                return u?.id ?? u?.user_id ?? u?.user?.id ?? null;
            }

            function userName(u) {
                return u?.name ?? u?.user?.name ?? '—';
            }

            let realtimeStarted = false;

            function startRealtime() {
                if (realtimeStarted) return;
                if (!window.Echo) return;

                realtimeStarted = true;
                console.log('[chat] Echo ready, joining presence channel chat');

                window.Echo.join('chat')
                    .here((users) => {
                        console.log('[chat] here(users)=', users);
                        online.clear();
                        normalizeUsers(users).forEach(u => {
                            const id = userId(u);
                            if (id != null) {
                                online.set(id, { id, name: userName(u) });
                            }
                        });
                        renderOnline(Array.from(online.values()));
                        updateGate(online.size);
                    })
                    .joining((user) => {
                        console.log('[chat] joining(user)=', user);
                        const id = userId(user);
                        if (id != null) online.set(id, { id, name: userName(user) });
                        renderOnline(Array.from(online.values()));
                        updateGate(online.size);
                    })
                    .leaving((user) => {
                        console.log('[chat] leaving(user)=', user);
                        const id = userId(user);
                        if (id != null) online.delete(id);
                        renderOnline(Array.from(online.values()));
                        updateGate(online.size);
                    })
                    .listen('.message.sent', (e) => {
                        console.log('[chat] message.sent', e);
                        const appended = appendMessage(e);
                        if (e?.id) lastMessageId = Math.max(lastMessageId, Number(e.id));
                    });
            }

            let pollingTimer = null;
            let pollingInFlight = false;

            async function pollOnce() {
                if (pollingInFlight) return;
                pollingInFlight = true;

                try {
                    const url = new URL(pollUrl, window.location.origin);
                    if (lastMessageId) url.searchParams.set('since_id', String(lastMessageId));

                    const res = await fetch(url.toString(), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;

                    const ct = (res.headers.get('content-type') || '').toLowerCase();
                    if (!ct.includes('application/json')) {
                        // Can happen if the session expired (HTML login), offline fallback, proxy error page, etc.
                        console.warn('[chat] poll: non-json response', { status: res.status, contentType: ct });
                        return;
                    }

                    const data = await res.json();
                    const onlineUsers = Array.isArray(data?.online) ? data.online : [];
                    renderOnline(onlineUsers);
                    updateGate(onlineUsers.length);

                    const msgs = Array.isArray(data?.messages) ? data.messages : [];
                    let appendedAny = false;
                    msgs.forEach(m => {
                        const ok = appendMessage(m);
                        if (ok) appendedAny = true;
                    });
                    if (appendedAny) {
                        focusLastMessage();
                    }

                    const newLast = Number(data?.last_id ?? lastMessageId);
                    if (!Number.isNaN(newLast)) lastMessageId = Math.max(lastMessageId, newLast);
                } catch (e) {
                    console.warn('[chat] poll failed', e);
                } finally {
                    pollingInFlight = false;
                }
            }

            function startPolling(options) {
                if (pollingTimer) return;
                console.log('[chat] starting polling fallback');

                const useInitial = options?.useInitial !== false;
                if (useInitial) {
                    renderOnline(initialOnline);
                    updateGate((initialOnline || []).length);
                }

                pollOnce();
                pollingTimer = setInterval(pollOnce, 5000);
            }

            // iOS/Safari can throttle timers heavily in background.
            // When the user comes back, do an immediate sync.
            window.addEventListener('focus', () => pollOnce());
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) pollOnce();
            });

            // Vite's module scripts load after this inline script, so Echo may appear a bit later.
            if (window.Echo) {
                startRealtime();
                // Safety net: even with Echo present, polling keeps messages in sync
                // on hosts where websockets/broadcasting are unavailable.
                startPolling({ useInitial: false });
            } else {
                console.log('[chat] waiting for Echo…');
                window.addEventListener('echo:ready', () => {
                    startRealtime();
                    startPolling({ useInitial: false });
                }, { once: true });

                let attempts = 0;
                const timer = setInterval(() => {
                    attempts++;
                    if (window.Echo) {
                        clearInterval(timer);
                        startRealtime();
                        startPolling({ useInitial: false });
                        return;
                    }
                    if (attempts >= 30) {
                        clearInterval(timer);
                        console.warn('[chat] Echo still not present after waiting (no realtime)');
                        startPolling({ useInitial: true });
                    }
                }, 100);
            }

            if (formEl && textareaEl) {
                let isSending = false;

                function autoGrow() {
                    textareaEl.style.height = 'auto';
                    const styles = window.getComputedStyle(textareaEl);
                    const lineHeight = parseFloat(styles.lineHeight || '20') || 20;
                    const max = Math.round(lineHeight * 3);
                    textareaEl.style.height = Math.min(textareaEl.scrollHeight, max) + 'px';
                }

                autoGrow();
                textareaEl.addEventListener('input', autoGrow);

                textareaEl.addEventListener('keydown', (ev) => {
                    if (ev.key === 'Enter' && !ev.shiftKey) {
                        ev.preventDefault();
                        formEl.requestSubmit?.();
                    }
                });

                // Voice dictation (Web Speech API)
                if (voiceBtn) {
                    syncVoiceAvailability();

                    if (SpeechRecognitionCtor) {
                        recognition = new SpeechRecognitionCtor();
                        recognition.lang = 'fr-FR';
                        recognition.interimResults = true;
                        recognition.continuous = true;
                        recognition.maxAlternatives = 1;

                        recognition.onresult = (event) => {
                            if (!textareaEl) return;
                            let finalText = '';
                            let interimText = '';

                            for (let i = event.resultIndex; i < event.results.length; i++) {
                                const res = event.results[i];
                                const chunk = String(res?.[0]?.transcript ?? '').trim();
                                if (!chunk) continue;
                                if (res.isFinal) {
                                    finalText += (finalText ? ' ' : '') + chunk;
                                } else {
                                    interimText += (interimText ? ' ' : '') + chunk;
                                }
                            }

                            if (finalText) {
                                dictationBase = (dictationBase || '').trim();
                                dictationBase = dictationBase
                                    ? (dictationBase + ' ' + finalText).trim()
                                    : finalText;
                            }

                            dictationInterim = interimText;
                            const composed = [dictationBase, dictationInterim].filter(Boolean).join(' ').trim();
                            textareaEl.value = composed;
                            textareaEl.selectionStart = textareaEl.selectionEnd = textareaEl.value.length;
                            autoGrow();
                        };

                        recognition.onerror = (event) => {
                            const code = event?.error ? String(event.error) : 'unknown';
                            setVoiceStatus(`Dictée vocale indisponible (${code}).`, { autoHideMs: 5000 });
                            setDictationUi(false);
                        };

                        recognition.onend = () => {
                            // If it stopped by itself (silence/permission), reflect it in UI.
                            if (dictationActive) {
                                setDictationUi(false);
                                dictationInterim = '';
                            }
                        };
                    }

                    voiceBtn.addEventListener('click', () => {
                        if (!SpeechRecognitionCtor || !recognition) return;
                        if (textareaEl?.disabled) return;

                        if (dictationActive) {
                            try {
                                recognition.stop();
                            } catch {
                                // ignore
                            }
                            setDictationUi(false);
                            dictationInterim = '';
                            return;
                        }

                        dictationBase = String(textareaEl.value || '').trim();
                        dictationInterim = '';
                        setDictationUi(true);
                        try {
                            recognition.start();
                        } catch (e) {
                            setDictationUi(false);
                            setVoiceStatus('Impossible de démarrer la dictée vocale.', { autoHideMs: 5000 });
                        }
                    });
                }

                formEl.addEventListener('submit', async (ev) => {
                    // Progressive enhancement: if Echo isn't loaded, let the normal POST+redirect happen.
                    if (!window.Echo) {
                        // When polling mode is active, prevent submit if chat is gated.
                        if (textareaEl?.disabled) {
                            ev.preventDefault();
                        }
                        return;
                    }

                    ev.preventDefault();

                    if (isSending) return;

                    const body = textareaEl.value.trim();
                    if (!body) return;

                    isSending = true;
                    const btn = formEl.querySelector('button[type="submit"]');
                    if (btn) btn.disabled = true;

                    const token = formEl.querySelector('input[name="_token"]')?.value;
                    const socketId = typeof window.Echo.socketId === 'function' ? window.Echo.socketId() : null;

                    const res = await fetch(formEl.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                            ...(socketId ? { 'X-Socket-Id': socketId } : {}),
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ body }),
                    });

                    try {
                        if (!res.ok) return;

                        const json = await res.json();
                        appendMessage(json);
                        if (json?.id) lastMessageId = Math.max(lastMessageId, Number(json.id));
                        textareaEl.value = '';
                        autoGrow();
                        textareaEl.focus();
                    } finally {
                        isSending = false;
                        if (btn) btn.disabled = textareaEl.disabled;
                    }
                });
            }
        })();
    </script>
</x-app-layout>
