<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Chat Live') }}
        </h2>
    </x-slot>

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
    @endphp

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-red-600">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <div class="text-xs text-gray-500">Connectés</div>
                        <div id="chatOnline" class="mt-1 flex flex-wrap gap-2">
                            <span class="text-xs text-gray-400">—</span>
                        </div>
                    </div>

                    <div id="chatMessages" class="space-y-3">
                        @forelse ($messages as $m)
                            @php
                                $userId = $m->user_id;
                                $name = $m->user?->name ?? '—';
                                $isMe = auth()->check() && (int) auth()->id() === (int) $userId;
                                $colors = $paletteFor((int) $userId);
                                $initials = $initialsFor($name);
                            @endphp

                            <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[85%] sm:max-w-[75%]">
                                    <div class="flex items-start gap-3 {{ $isMe ? 'flex-row-reverse' : '' }}">
                                        <div class="shrink-0">
                                            <div class="h-9 w-9 rounded-full flex items-center justify-center text-xs font-semibold {{ $colors['avatar'] }}">
                                                {{ $initials }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border px-4 py-3 {{ $isMe ? 'bg-gray-900 text-white border-gray-900' : ($colors['chip'].' bg-white') }}" data-message-id="{{ $m->id }}">
                                            <div class="flex items-baseline justify-between gap-3">
                                                <div class="text-sm font-semibold {{ $isMe ? 'text-white/90' : 'text-gray-900' }}">
                                                    {{ $name }}
                                                </div>
                                                <div class="text-xs {{ $isMe ? 'text-white/60' : 'text-gray-500' }}">
                                                    {{ $m->created_at?->format('d/m/Y H:i') }}
                                                </div>
                                            </div>
                                            <div class="mt-2 text-sm whitespace-pre-wrap {{ $isMe ? 'text-white' : 'text-gray-800' }}">{{ $m->body }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">Aucun message.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form id="chatForm" method="POST" action="{{ route('chat.store') }}" class="space-y-3">
                        @csrf
                        <div>
                            <x-input-label for="body" :value="__('Message')" />
                            <textarea id="body" name="body" rows="3" class="mt-1 block w-full" required>{{ old('body') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('body')" />
                        </div>

                        <div class="flex items-center justify-end">
                            <x-primary-button>
                                {{ __('Envoyer') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const messagesEl = document.getElementById('chatMessages');
            const onlineEl = document.getElementById('chatOnline');
            const formEl = document.getElementById('chatForm');
            const textareaEl = document.getElementById('body');
            const currentUserId = @json(auth()->id());
            const pollUrl = @json(route('chat.poll'));
            let lastMessageId = @json($lastMessageId ?? 0);
            const initialOnline = @json($initialOnline ?? []);

            const gateEl = document.createElement('div');
            gateEl.className = 'text-sm text-gray-600';
            gateEl.id = 'chatGate';
            if (formEl) {
                formEl.prepend(gateEl);
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

            if (messagesEl) {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }

            function renderOnline(users) {
                if (!onlineEl) return;
                onlineEl.innerHTML = '';
                if (!users || users.length === 0) {
                    const empty = document.createElement('span');
                    empty.className = 'text-xs text-gray-400';
                    empty.textContent = '—';
                    onlineEl.appendChild(empty);
                    return;
                }

                users.forEach(u => {
                    const id = userId(u);
                    const name = userName(u);
                    const colors = paletteFor(id);

                    const chip = document.createElement('span');
                    chip.className = `inline-flex items-center gap-2 text-xs px-2.5 py-1.5 rounded-full border ${colors.chip}`;

                    const dot = document.createElement('span');
                    dot.className = `h-5 w-5 rounded-full flex items-center justify-center text-[10px] font-semibold ${colors.avatar}`;
                    dot.textContent = initialsFor(name);

                    const label = document.createElement('span');
                    label.className = 'font-medium';
                    label.textContent = (currentUserId && id && Number(id) === Number(currentUserId)) ? `${name} (vous)` : name;

                    chip.appendChild(dot);
                    chip.appendChild(label);
                    onlineEl.appendChild(chip);
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
                    gateEl.textContent = ok
                        ? ''
                        : 'Chat désactivé : il faut au moins 2 connectés.';
                    gateEl.className = ok ? 'hidden' : 'mb-3 text-sm text-gray-600';
                }
            }

            function appendMessage(payload) {
                if (!messagesEl) return;
                const id = payload?.id ?? null;
                if (id != null && messagesEl.querySelector(`[data-message-id="${id}"]`)) {
                    return;
                }
                const uid = payload?.user?.id ?? payload?.user_id ?? null;
                const name = payload?.user?.name ?? '—';
                const body = payload?.body ?? '';
                const createdAt = payload?.created_at ? new Date(payload.created_at) : null;
                const when = createdAt ? createdAt.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

                const isMe = currentUserId && uid && Number(uid) === Number(currentUserId);
                const colors = paletteFor(uid);
                const initials = initialsFor(name);

                const outer = document.createElement('div');
                outer.className = `flex ${isMe ? 'justify-end' : 'justify-start'}`;

                const width = document.createElement('div');
                width.className = 'max-w-[85%] sm:max-w-[75%]';

                const row = document.createElement('div');
                row.className = `flex items-start gap-3 ${isMe ? 'flex-row-reverse' : ''}`;

                const avatarWrap = document.createElement('div');
                avatarWrap.className = 'shrink-0';
                const avatar = document.createElement('div');
                avatar.className = `h-9 w-9 rounded-full flex items-center justify-center text-xs font-semibold ${colors.avatar}`;
                avatar.textContent = initials;
                avatarWrap.appendChild(avatar);

                const wrapper = document.createElement('div');
                wrapper.className = `rounded-2xl border px-4 py-3 ${isMe ? 'bg-gray-900 text-white border-gray-900' : (colors.chip + ' bg-white')}`;
                if (id != null) {
                    wrapper.dataset.messageId = String(id);
                }
                wrapper.innerHTML = `
                    <div class="flex items-baseline justify-between gap-3">
                        <div class="text-sm font-semibold"></div>
                        <div class="text-xs"></div>
                    </div>
                    <div class="mt-2 text-sm whitespace-pre-wrap"></div>
                `;

                const nameEl = wrapper.querySelector('.font-semibold');
                const whenEl = wrapper.querySelector('.text-xs');
                const bodyEl = wrapper.querySelector('.whitespace-pre-wrap');

                nameEl.textContent = name;
                whenEl.textContent = when;
                bodyEl.textContent = body;

                nameEl.className = `text-sm font-semibold ${isMe ? 'text-white/90' : 'text-gray-900'}`;
                whenEl.className = `text-xs ${isMe ? 'text-white/60' : 'text-gray-500'}`;
                bodyEl.className = `mt-2 text-sm whitespace-pre-wrap ${isMe ? 'text-white' : 'text-gray-800'}`;

                row.appendChild(avatarWrap);
                row.appendChild(wrapper);
                width.appendChild(row);
                outer.appendChild(width);
                messagesEl.appendChild(outer);
                messagesEl.scrollTop = messagesEl.scrollHeight;
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
                        appendMessage(e);
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

                    const data = await res.json();
                    const onlineUsers = Array.isArray(data?.online) ? data.online : [];
                    renderOnline(onlineUsers);
                    updateGate(onlineUsers.length);

                    const msgs = Array.isArray(data?.messages) ? data.messages : [];
                    msgs.forEach(m => appendMessage(m));

                    const newLast = Number(data?.last_id ?? lastMessageId);
                    if (!Number.isNaN(newLast)) lastMessageId = Math.max(lastMessageId, newLast);
                } finally {
                    pollingInFlight = false;
                }
            }

            function startPolling() {
                if (pollingTimer) return;
                console.log('[chat] starting polling fallback');

                renderOnline(initialOnline);
                updateGate((initialOnline || []).length);

                pollOnce();
                pollingTimer = setInterval(pollOnce, 5000);
            }

            // Vite's module scripts load after this inline script, so Echo may appear a bit later.
            if (window.Echo) {
                startRealtime();
            } else {
                console.log('[chat] waiting for Echo…');
                window.addEventListener('echo:ready', () => startRealtime(), { once: true });

                let attempts = 0;
                const timer = setInterval(() => {
                    attempts++;
                    if (window.Echo) {
                        clearInterval(timer);
                        startRealtime();
                        return;
                    }
                    if (attempts >= 30) {
                        clearInterval(timer);
                        console.warn('[chat] Echo still not present after waiting (no realtime)');
                        startPolling();
                    }
                }, 100);
            }

            if (formEl && textareaEl) {
                formEl.addEventListener('submit', async (ev) => {
                    // Progressive enhancement: if Echo isn't loaded, let the normal POST+redirect happen.
                    if (!window.Echo) {
                        // When polling mode is active, prevent submit if chat is gated.
                        const onlineCount = (onlineEl?.querySelectorAll('span')?.length ?? 0);
                        if (textareaEl?.disabled) {
                            ev.preventDefault();
                        }
                        return;
                    }

                    ev.preventDefault();

                    const body = textareaEl.value.trim();
                    if (!body) return;

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

                    if (!res.ok) return;

                    const json = await res.json();
                    appendMessage(json);
                    if (json?.id) lastMessageId = Math.max(lastMessageId, Number(json.id));
                    textareaEl.value = '';
                    textareaEl.focus();
                });
            }
        })();
    </script>
</x-app-layout>
