<x-app-layout pageBgClass="bg-slate-50">
    @php
        $ATTACH_PREFIX = '[[ATTACHMENT]]';
        $parseAttachment = function (?string $body) use ($ATTACH_PREFIX): ?array {
            $body = (string) $body;
            if (!str_starts_with($body, $ATTACH_PREFIX)) {
                return null;
            }
            $json = substr($body, strlen($ATTACH_PREFIX));
            $data = json_decode($json, true);
            if (!is_array($data)) {
                return null;
            }
            $type = (string) ($data['media_type'] ?? '');
            if (!in_array($type, ['image', 'video'], true)) {
                return null;
            }
            return $data;
        };

        $parseLinkCard = function (?string $body): ?array {
            $b = trim((string) $body);
            if ($b === '') return null;

            $url = null;
            if (preg_match('/^📹\s*Visio:\s*(https?:\/\/\S+)\s*$/u', $b, $m)) {
                $url = $m[1] ?? null;
            } elseif (preg_match('/^(https?:\/\/\S+)\s*$/u', $b, $m)) {
                $url = $m[1] ?? null;
            }
            $url = $url ? trim((string) $url) : null;
            if (!$url) return null;

            $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
            $domain = $host !== '' ? $host : preg_replace('/^https?:\/\//i', '', $url);

            $title = 'Lien';
            if (str_contains($b, 'Visio') || str_contains($domain, 'jit.si')) {
                $title = 'Appel vidéo';
            }

            return [
                'url' => $url,
                'domain' => $domain,
                'title' => $title,
            ];
        };

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

    <x-slot name="bottomDock">
        <div class="px-4 py-2">
            <div id="chatSoloHint" class="hidden mb-2 text-xs text-slate-500"></div>
            <form id="chatForm" method="POST" action="{{ route('chat.store') }}" class="flex items-end gap-2">
                @csrf
                <button
                    type="button"
                    id="chatAttachBtn"
                    class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                    aria-label="Ajouter"
                    title="Ajouter"
                >
                    ＋
                </button>

                <div class="flex-1 rounded-full border border-slate-200 bg-white px-4 py-2">
                    <textarea
                        id="body"
                        name="body"
                        rows="1"
                        class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6 max-h-28"
                        placeholder="Écrire un message…"
                        required
                    >{{ old('body') }}</textarea>
                </div>

                <input type="file" id="chatAttachInput" class="hidden" accept="image/*,video/*" />

                <button
                    type="submit"
                    id="chatSendBtn"
                    class="w-11 h-11 rounded-full inline-flex items-center justify-center bg-slate-900 text-white font-semibold disabled:opacity-50"
                    aria-label="Envoyer"
                    title="Envoyer"
                    disabled
                >
                    <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>
                </button>

                <x-input-error class="mt-2" :messages="$errors->get('body')" />
            </form>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-0 sm:px-6 py-0 sm:py-6 space-y-4 sm:space-y-6">
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

        <div class="bg-white sm:rounded-2xl shadow-sm flex flex-col h-[calc(100dvh-7rem-var(--mobile-bottom-nav-h,4rem)-env(safe-area-inset-bottom)-5rem)] sm:h-[calc(100vh-10rem)] sm:overflow-hidden">
            @php
                $visioDomain = trim((string) (config('visio.jitsi_domain') ?? 'meet.jit.si'));
                $visioProvider = (string) (config('visio.provider') ?? 'link');
                $visioUrl = trim((string) (config('visio.url') ?? ''));
                $visioAvailable = $visioProvider === 'jitsi'
                    ? ($visioDomain !== '')
                    : ($visioUrl !== '' || $visioDomain !== '');
            @endphp
            <div class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-slate-100">
                <div style="padding-top: calc(env(safe-area-inset-top) + 0.5rem)">
                    <div class="h-14 px-4 sm:px-6 pb-2 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        id="chatBackBtn"
                        class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                        aria-label="Retour"
                        title="Retour"
                    >
                        <i class="ph ph-arrow-left" aria-hidden="true"></i>
                    </button>

                    <div class="min-w-0 flex-1 text-center">
                        <div class="text-sm sm:text-base font-semibold text-gray-900 leading-tight">Famille</div>
                        <div class="text-xs text-slate-500 leading-tight">
                            <span class="text-emerald-600">●</span>
                            <span id="chatOnlineCount" class="font-semibold text-gray-900">{{ $onlineList->count() }}</span>
                            <span id="chatPresenceLabel">en ligne</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            id="chatVisioBtn"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            aria-label="Appel vidéo"
                            title="{{ $visioAvailable ? 'Appel vidéo' : 'Indisponible' }}"
                            {{ $visioAvailable ? '' : 'disabled' }}
                            data-jitsi-domain="{{ $visioDomain }}"
                        >
                            <i class="ph ph-video-camera" aria-hidden="true"></i>
                        </button>

                        <button
                            type="button"
                            id="chatSearchBtn"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                            aria-label="Rechercher"
                            title="Rechercher"
                        >
                            <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                        </button>

                        <button
                            type="button"
                            id="chatInfoBtn"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                            aria-label="Infos"
                            title="Infos"
                        >
                            <i class="ph ph-info" aria-hidden="true"></i>
                        </button>
                    </div>
                    </div>
                </div>

                <div id="chatSearchBar" class="hidden px-4 sm:px-6 pb-3">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-2">
                        <input
                            type="search"
                            id="chatSearchInput"
                            class="w-full border-0 p-0 focus:ring-0 text-sm"
                            placeholder="Rechercher dans la conversation…"
                        />
                    </div>
                </div>
            </div>

            <div id="chatScroll" class="flex-1 min-h-0 overflow-y-auto overscroll-contain pb-[calc(6rem+var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom))] sm:pb-0">
                <div class="relative">
                    <button
                        type="button"
                        id="chatScrollToBottom"
                        class="hidden absolute right-4 bottom-4 z-10 items-center gap-2 rounded-full bg-slate-900 text-white shadow-lg px-4 py-2 text-sm font-semibold"
                        aria-label="Nouveau message"
                        title="Nouveau message"
                    >
                        <i class="ph ph-arrow-down" aria-hidden="true"></i>
                        <span>Nouveau</span>
                    </button>

                    <div id="chatMessages" class="flex flex-col gap-3 p-4 sm:p-6">
                        <div id="chatNotifBanner" class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900">Activer les notifications</div>
                                    <div id="chatNotifBody" class="mt-1 text-sm text-slate-600">Recevez un push quand un message arrive.</div>
                                    <div id="chatNotifHelp" class="mt-2 text-xs text-slate-500"></div>
                                </div>
                                <button type="button" id="chatNotifClose" class="w-8 h-8 rounded-full inline-flex items-center justify-center text-slate-500 hover:bg-white" aria-label="Fermer" title="Fermer">✕</button>
                            </div>
                            <div class="mt-3 flex items-center gap-2">
                                <button type="button" id="chatNotifPrimary" class="inline-flex items-center justify-center rounded-full bg-slate-900 text-white px-4 py-2 text-sm font-semibold">Activer</button>
                                <button type="button" id="chatNotifLater" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 px-4 py-2 text-sm font-semibold">Plus tard</button>
                            </div>
                        </div>
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
                                <div class="max-w-[90%] sm:max-w-[80%]">
                                    @if($isGroupStart)
                                        <div class="mb-1 text-xs text-slate-500 {{ $isMe ? 'text-right' : '' }}">
                                            {{ $firstName }} · {{ $m->created_at?->format('H:i') }}
                                        </div>
                                    @endif

                                    <div class="flex items-end gap-2 {{ $isMe ? 'flex-row-reverse' : '' }}">
                                        <div class="shrink-0 {{ $isGroupEnd ? '' : 'invisible' }}" data-avatar>
                                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center text-xs font-semibold {{ $colors['avatar'] }}">
                                                {{ $initials }}
                                            </div>
                                        </div>

                                        <div class="px-4 py-3 border {{ $isMe ? 'bg-slate-900 text-white border-slate-900 rounded-2xl rounded-br-md' : 'bg-white text-gray-900 border-slate-200 rounded-2xl rounded-bl-md' }}" data-bubble>
                                            @php
                                                $att = $parseAttachment($m->body);
                                                $link = $att ? null : $parseLinkCard($m->body);
                                            @endphp
                                            @if ($att)
                                                @php
                                                    $attType = (string) ($att['media_type'] ?? '');
                                                    $attUrl = (string) ($att['url'] ?? '#');
                                                    $attThumb = (string) ($att['thumb_url'] ?? '');
                                                    $attName = (string) ($att['name'] ?? ($attType === 'video' ? 'Vidéo' : 'Photo'));
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="block text-left"
                                                    aria-label="Ouvrir {{ $attName }}"
                                                    data-chat-media-open="1"
                                                    data-url="{{ $attUrl }}"
                                                    data-type="{{ $attType }}"
                                                    data-name="{{ $attName }}"
                                                    data-thumb="{{ $attThumb }}"
                                                >
                                                    <div class="relative overflow-hidden rounded-xl border shadow-sm w-64 max-w-full h-40 sm:w-72 sm:h-44 {{ $isMe ? 'border-white/20 bg-white/5' : 'border-slate-200 bg-slate-50' }}">
                                                        @if ($attThumb !== '')
                                                            <img src="{{ $attThumb }}" alt="{{ $attName }}" class="block w-full h-full object-contain" loading="lazy" />
                                                        @else
                                                            <div class="w-full h-full flex items-center justify-center text-xs {{ $isMe ? 'text-white/80' : 'text-slate-500' }}">{{ $attName }}</div>
                                                        @endif

                                                        <div class="absolute top-2 right-2 pointer-events-none">
                                                            <div class="w-9 h-9 rounded-full bg-black/40 backdrop-blur flex items-center justify-center text-white">
                                                                <i class="ph ph-arrows-out" aria-hidden="true"></i>
                                                            </div>
                                                        </div>

                                                        @if ($attType === 'video')
                                                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                                                <div class="w-12 h-12 rounded-full bg-black/40 flex items-center justify-center text-white text-xl">▶</div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="mt-2 text-xs opacity-80">{{ $attName }}</div>
                                                </button>
                                            @elseif ($link)
                                                <div class="rounded-xl border border-slate-200 {{ $isMe ? 'bg-white/10' : 'bg-slate-50' }} p-3">
                                                    <div class="text-sm font-semibold {{ $isMe ? 'text-white' : 'text-gray-900' }}">{{ $link['title'] }}</div>
                                                    <div class="mt-0.5 text-xs {{ $isMe ? 'text-white/80' : 'text-slate-500' }}">{{ $link['domain'] }}</div>
                                                    <div class="mt-3 flex items-center gap-2">
                                                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-full bg-slate-900 text-white px-3 py-1.5 text-xs font-semibold">Rejoindre</a>
                                                        <button type="button" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700" data-copy-link="{{ $link['url'] }}">Copier le lien</button>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-sm whitespace-pre-wrap">{{ $m->body }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="hidden sm:block border-t border-slate-100 bg-white sticky bottom-0 z-40">
                <div class="px-4 sm:px-6 py-3">
                    <div id="chatSoloHintDesktop" class="hidden mb-2 text-xs text-slate-500"></div>
                    <form id="chatFormDesktop" method="POST" action="{{ route('chat.store') }}" class="flex items-end gap-2">
                        @csrf
                        <button
                            type="button"
                            id="chatAttachBtnDesktop"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                            aria-label="Ajouter"
                            title="Ajouter"
                        >
                            ＋
                        </button>

                        <div class="flex-1 rounded-full border border-slate-200 bg-white px-4 py-2">
                            <textarea
                                id="bodyDesktop"
                                name="body"
                                rows="1"
                                class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6"
                                placeholder="Écrire un message…"
                                required
                            >{{ old('body') }}</textarea>
                        </div>

                        <input type="file" id="chatAttachInputDesktop" class="hidden" accept="image/*,video/*" />

                        <button
                            type="submit"
                            id="chatSendBtnDesktop"
                            class="w-11 h-11 rounded-full inline-flex items-center justify-center bg-slate-900 text-white font-semibold disabled:opacity-50"
                            aria-label="Envoyer"
                            title="Envoyer"
                            disabled
                        >
                            <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>
                        </button>

                        <x-input-error class="mt-2" :messages="$errors->get('body')" />
                    </form>
                </div>
            </div>
        </div>

        <div id="chatAttachSheet" class="fixed inset-0 z-50 hidden">
            <div id="chatAttachBackdrop" class="absolute inset-0 bg-black/40"></div>
            <div class="absolute inset-x-0 bottom-0 bg-white rounded-t-3xl p-4 shadow-2xl">
                <div class="text-sm font-semibold text-gray-900 px-2">Ajouter</div>
                <div id="chatAttachQuota" class="mt-1 text-xs text-slate-500 px-2"></div>
                <div class="mt-3 grid gap-2">
                    <button
                        type="button"
                        id="chatAttachPickMedia"
                        class="w-full inline-flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900"
                    >
                        <span>Photo / Vidéo</span>
                        <i class="ph ph-image" aria-hidden="true"></i>
                    </button>

                    <button
                        type="button"
                        id="chatAttachPickVoice"
                        class="w-full inline-flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900"
                    >
                        <span>Dicter</span>
                        <i class="ph ph-microphone" aria-hidden="true"></i>
                    </button>
                </div>
                <button type="button" id="chatAttachCancel" class="mt-3 w-full text-sm text-slate-600 py-2">Annuler</button>
            </div>
        </div>

        <div id="chatInfoModal" class="fixed inset-0 z-50 hidden">
            <div id="chatInfoBackdrop" class="absolute inset-0 bg-black/40"></div>
            <div class="absolute inset-x-0 bottom-0 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:top-24 sm:bottom-auto w-full sm:w-[420px] bg-white rounded-t-3xl sm:rounded-3xl p-4 shadow-2xl">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-gray-900">Participants</div>
                    <button type="button" id="chatInfoClose" class="w-9 h-9 rounded-full inline-flex items-center justify-center text-slate-600 hover:bg-slate-50" aria-label="Fermer" title="Fermer">✕</button>
                </div>
                <div class="mt-1 text-xs text-slate-500"><span id="chatInfoCount">0</span> en ligne</div>
                <div id="chatInfoList" class="mt-3 space-y-2"></div>
            </div>
        </div>

        <div id="chatMediaModal" class="fixed inset-0 z-[60] hidden">
            <div id="chatMediaBackdrop" class="absolute inset-0 bg-black/80"></div>
            <div class="absolute inset-0 flex flex-col">
                <div class="shrink-0 flex items-center justify-between gap-3 p-3 sm:p-4 text-white">
                    <div id="chatMediaTitle" class="text-sm font-semibold truncate"></div>
                    <div class="flex items-center gap-2">
                        <a id="chatMediaOpenLink" href="#" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold hover:bg-white/15">Ouvrir</a>
                        <button type="button" id="chatMediaClose" class="w-9 h-9 rounded-full inline-flex items-center justify-center bg-white/10 hover:bg-white/15" aria-label="Fermer" title="Fermer">✕</button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 flex items-center justify-center p-3 sm:p-6">
                    <img id="chatMediaImg" class="hidden max-h-full max-w-full object-contain rounded-2xl bg-black/20" alt="" />
                    <video id="chatMediaVideo" class="hidden max-h-full max-w-full rounded-2xl bg-black/20" controls playsinline></video>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const start = () => {
            function firstExisting(...els) {
                for (const el of els) {
                    if (el) return el;
                }
                return null;
            }

            const scrollEl = document.getElementById('chatScroll');
            const messagesEl = document.getElementById('chatMessages');
            const emptyEl = document.getElementById('chatEmptyState');
            const onlineCountEl = document.getElementById('chatOnlineCount');
            const presenceLabelEl = document.getElementById('chatPresenceLabel');
            const composer = {
                mobile: {
                    key: 'mobile',
                    form: document.getElementById('chatForm'),
                    textarea: document.getElementById('body'),
                    attachBtn: document.getElementById('chatAttachBtn'),
                    attachInput: document.getElementById('chatAttachInput'),
                    sendBtn: document.getElementById('chatSendBtn'),
                    soloHint: document.getElementById('chatSoloHint'),
                },
                desktop: {
                    key: 'desktop',
                    form: document.getElementById('chatFormDesktop'),
                    textarea: document.getElementById('bodyDesktop'),
                    attachBtn: document.getElementById('chatAttachBtnDesktop'),
                    attachInput: document.getElementById('chatAttachInputDesktop'),
                    sendBtn: document.getElementById('chatSendBtnDesktop'),
                    soloHint: document.getElementById('chatSoloHintDesktop'),
                },
            };

            let activeComposerKey = 'mobile';
            try {
                activeComposerKey = window.matchMedia && window.matchMedia('(min-width: 640px)').matches ? 'desktop' : 'mobile';
            } catch {}

            function setActiveComposerKey(key) {
                if (key === 'mobile' || key === 'desktop') {
                    activeComposerKey = key;
                }
            }

            function getActiveComposer() {
                return composer[activeComposerKey] || composer.mobile;
            }

            function getAnyForm() {
                return composer.mobile.form || composer.desktop.form || null;
            }

            function getCsrfToken() {
                const f = getAnyForm();
                return f?.querySelector('input[name="_token"]')?.value || null;
            }

            function getSocketId() {
                try {
                    return (window.Echo && typeof window.Echo.socketId === 'function') ? window.Echo.socketId() : null;
                } catch {
                    return null;
                }
            }

            function syncComposerKeyFromMatchMedia() {
                try {
                    const next = window.matchMedia && window.matchMedia('(min-width: 640px)').matches ? 'desktop' : 'mobile';
                    setActiveComposerKey(next);
                } catch {}
            }
            window.addEventListener('resize', syncComposerKeyFromMatchMedia);
            const attachSheet = document.getElementById('chatAttachSheet');
            const attachBackdrop = document.getElementById('chatAttachBackdrop');
            const attachCancel = document.getElementById('chatAttachCancel');
            const attachPickMedia = document.getElementById('chatAttachPickMedia');
            const attachPickVoice = document.getElementById('chatAttachPickVoice');
            const scrollToBottomBtn = document.getElementById('chatScrollToBottom');
            const backBtn = document.getElementById('chatBackBtn');
            const searchBtn = document.getElementById('chatSearchBtn');
            const searchBar = document.getElementById('chatSearchBar');
            const searchInput = document.getElementById('chatSearchInput');
            const infoBtn = document.getElementById('chatInfoBtn');
            const infoModal = document.getElementById('chatInfoModal');
            const infoBackdrop = document.getElementById('chatInfoBackdrop');
            const infoClose = document.getElementById('chatInfoClose');
            const infoList = document.getElementById('chatInfoList');
            const infoCount = document.getElementById('chatInfoCount');

            const mediaModal = document.getElementById('chatMediaModal');
            const mediaBackdrop = document.getElementById('chatMediaBackdrop');
            const mediaClose = document.getElementById('chatMediaClose');
            const mediaTitle = document.getElementById('chatMediaTitle');
            const mediaImg = document.getElementById('chatMediaImg');
            const mediaVideo = document.getElementById('chatMediaVideo');
            const mediaOpenLink = document.getElementById('chatMediaOpenLink');
            const currentUserId = @json(auth()->id());
            const currentUserName = @json(auth()->user()?->name);
            const pollUrl = @json(route('chat.poll'));
            const quotaUrl = @json(url('/api/uploads/quota'));
            const presignUrl = @json(url('/api/uploads/presign'));
            const mpInitUrl = @json(url('/api/uploads/multipart/init'));
            const mpCompleteUrl = @json(url('/api/uploads/multipart/complete'));
            const finalizeUrl = @json(url('/api/uploads/finalize'));
            let lastMessageId = @json($lastMessageId ?? 0);
            const initialOnline = @json($initialOnline ?? []);

            const MAX_UPLOAD_BYTES = @json((int) config('uploads.max_upload_bytes'));
            const MULTIPART_THRESHOLD_BYTES = @json((int) config('uploads.multipart_threshold_bytes'));

            const quotaEl = document.getElementById('chatAttachQuota');

            const visioBtn = document.getElementById('chatVisioBtn');

            const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
            let recognition = null;
            let dictationActive = false;
            let dictationBase = '';
            let dictationInterim = '';

            const notifBanner = document.getElementById('chatNotifBanner');
            const notifBody = document.getElementById('chatNotifBody');
            const notifHelp = document.getElementById('chatNotifHelp');
            const notifPrimary = document.getElementById('chatNotifPrimary');
            const notifLater = document.getElementById('chatNotifLater');
            const notifClose = document.getElementById('chatNotifClose');

            const NOTIF_DISMISS_KEY = 'famille:chat:notif_dismissed_at';

            function setVoiceStatus() {}

            function parseAttachmentBody(body) {
                const prefix = '[[ATTACHMENT]]';
                const b = String(body || '');
                if (!b.startsWith(prefix)) return null;
                try {
                    const data = JSON.parse(b.slice(prefix.length));
                    if (!data || typeof data !== 'object') return null;
                    const t = String(data.media_type || '');
                    if (t !== 'image' && t !== 'video') return null;
                    return data;
                } catch (e) {
                    return null;
                }
            }

            function parseLinkCardBody(body) {
                const b = String(body || '').trim();
                if (!b) return null;

                let url = null;
                const m1 = b.match(/^📹\s*Visio:\s*(https?:\/\/\S+)\s*$/u);
                if (m1) url = m1[1];
                if (!url) {
                    const m2 = b.match(/^(https?:\/\/\S+)\s*$/u);
                    if (m2) url = m2[1];
                }
                if (!url) return null;
                url = String(url).trim();

                let domain = '';
                try {
                    domain = (new URL(url)).host || '';
                } catch {
                    domain = url.replace(/^https?:\/\//i, '').replace(/\/+$/g, '');
                }
                const title = (b.includes('Visio') || domain.includes('jit.si')) ? 'Appel vidéo' : 'Lien';
                return { url, domain, title };
            }

            function setDictationUi(active) {
                dictationActive = !!active;
                if (!attachPickVoice) return;
                attachPickVoice.setAttribute('aria-pressed', dictationActive ? 'true' : 'false');
                attachPickVoice.classList.toggle('border-slate-900', dictationActive);
            }
            function syncVoiceAvailability() {
                if (!attachPickVoice) return;
                const supported = !!SpeechRecognitionCtor;
                attachPickVoice.disabled = !supported;
                attachPickVoice.classList.toggle('opacity-50', attachPickVoice.disabled);
                attachPickVoice.classList.toggle('cursor-not-allowed', attachPickVoice.disabled);
                attachPickVoice.title = supported ? 'Dicter' : 'Dictée vocale non supportée par ce navigateur';
            }

            function sanitizeDomain(raw) {
                const v = String(raw || '').trim();
                return v.replace(/^https?:\/\//i, '').replace(/\/+$/g, '') || 'meet.jit.si';
            }

            function randomBase64Url(byteLen) {
                const len = Number(byteLen || 18);
                const cryptoObj = (window.crypto || window.msCrypto);
                if (!cryptoObj || !cryptoObj.getRandomValues) {
                    throw new Error('Secure random not available');
                }
                const bytes = new Uint8Array(len);
                cryptoObj.getRandomValues(bytes);
                let binary = '';
                for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
                return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
            }

            function buildVisioRoom() {
                return `famille-${randomBase64Url(18)}`;
            }

            async function postVisioLinkToChat(url) {
                const form = getAnyForm();
                if (!form) return;
                const token = getCsrfToken();
                if (!token) return;

                const message = `📹 Visio: ${url}`;
                const body = new URLSearchParams();
                body.set('_token', token);
                body.set('body', message);

                try {
                    await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        },
                        body: body.toString(),
                        credentials: 'same-origin',
                    });
                } catch (e) {
                    // Ignore; user still has the link opened.
                }
            }

            function openVisioInNewTab(url) {
                const w = window.open(url, '_blank', 'noopener,noreferrer');
                if (!w) {
                    // Pop-up blocked: fallback to normal navigation.
                    window.location.href = url;
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

            function scrollToBottom() {
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

            function isNearBottom() {
                if (!scrollEl) return true;
                const threshold = 120;
                const distance = scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight;
                return distance <= threshold;
            }

            function syncScrollToBottomButton() {
                if (!scrollToBottomBtn || !scrollEl) return;
                const show = !isNearBottom();
                scrollToBottomBtn.classList.toggle('hidden', !show);
                scrollToBottomBtn.classList.toggle('flex', show);
            }

            scrollToBottom();
            syncScrollToBottomButton();

            function hideEmptyState() {
                if (!emptyEl) return;
                emptyEl.classList.add('hidden');
            }

            function updatePresenceUi(count) {
                const c = Number(count || 0);
                if (presenceLabelEl) {
                    presenceLabelEl.textContent = c <= 1 ? 'en ligne' : 'en ligne';
                }

                const hint = c <= 1 ? 'Personne en ligne — votre message sera notifié.' : '';
                const show = c <= 1;
                [composer.mobile.soloHint, composer.desktop.soloHint].forEach((el) => {
                    if (!el) return;
                    el.textContent = hint;
                    el.classList.toggle('hidden', !show);
                });
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

                if (infoCount) {
                    infoCount.textContent = String(count);
                }

                if (infoList) {
                    infoList.innerHTML = '';
                    list
                        .slice(0, 24)
                        .forEach(u => {
                            const id = userId(u);
                            const name = userName(u);
                            const colors = paletteFor(id);

                            const row = document.createElement('div');
                            row.className = 'flex items-center gap-3';

                            const av = document.createElement('div');
                            av.className = `w-9 h-9 rounded-full flex items-center justify-center text-xs font-semibold ${colors.avatar}`;
                            av.textContent = initialsFor(name);

                            const label = document.createElement('div');
                            label.className = 'text-sm text-gray-900';
                            label.textContent = name;

                            row.appendChild(av);
                            row.appendChild(label);
                            infoList.appendChild(row);
                        });
                }

                updatePresenceUi(count);
            }

            syncVoiceAvailability();

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

                const wasAtBottom = isNearBottom();
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
                width.className = 'max-w-[90%] sm:max-w-[80%]';

                if (!sameAuthorAsPrev) {
                    const meta = document.createElement('div');
                    meta.className = `mb-1 text-xs text-slate-500 ${isMe ? 'text-right' : ''}`;
                    meta.textContent = `${firstName(name)} · ${whenTime}`;
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
                wrapper.dataset.bubble = '1';

                const att = parseAttachmentBody(body);
                const bodyEl = document.createElement('div');

                if (att) {
                    bodyEl.className = 'text-sm';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'block text-left';
                    btn.dataset.chatMediaOpen = '1';
                    btn.dataset.url = String(att.url || '#');
                    btn.dataset.type = String(att.media_type || '');

                    const card = document.createElement('div');
                    card.className = `relative overflow-hidden rounded-xl border shadow-sm w-64 max-w-full h-40 sm:w-72 sm:h-44 ${isMe ? 'border-white/20 bg-white/5' : 'border-slate-200 bg-slate-50'}`;

                    const thumb = String(att.thumb_url || '');
                    const nameLabel = String(att.name || (att.media_type === 'video' ? 'Vidéo' : 'Photo'));
                    btn.dataset.name = nameLabel;
                    btn.dataset.thumb = thumb;

                    if (thumb) {
                        const img = document.createElement('img');
                        img.src = thumb;
                        img.alt = nameLabel;
                        img.loading = 'lazy';
                        img.className = 'block w-full h-full object-contain';
                        card.appendChild(img);
                    } else {
                        const ph = document.createElement('div');
                        ph.className = `w-full h-full flex items-center justify-center text-xs ${isMe ? 'text-white/80' : 'text-slate-500'}`;
                        ph.textContent = nameLabel;
                        card.appendChild(ph);
                    }

                    const expand = document.createElement('div');
                    expand.className = 'absolute top-2 right-2 pointer-events-none';
                    expand.innerHTML = '<div class="w-9 h-9 rounded-full bg-black/40 backdrop-blur flex items-center justify-center text-white"><i class="ph ph-arrows-out" aria-hidden="true"></i></div>';
                    card.appendChild(expand);

                    if (String(att.media_type) === 'video') {
                        const overlay = document.createElement('div');
                        overlay.className = 'absolute inset-0 flex items-center justify-center pointer-events-none';
                        const pill = document.createElement('div');
                        pill.className = 'w-12 h-12 rounded-full bg-black/40 flex items-center justify-center text-white text-xl';
                        pill.textContent = '▶';
                        overlay.appendChild(pill);
                        card.appendChild(overlay);
                    }

                    const caption = document.createElement('div');
                    caption.className = 'mt-2 text-xs opacity-80';
                    caption.textContent = nameLabel;

                    btn.appendChild(card);
                    btn.appendChild(caption);
                    bodyEl.appendChild(btn);
                } else {
                    const link = parseLinkCardBody(body);
                    if (link) {
                        const card = document.createElement('div');
                        card.className = `rounded-xl border border-slate-200 ${isMe ? 'bg-white/10' : 'bg-slate-50'} p-3`;

                        const t = document.createElement('div');
                        t.className = `text-sm font-semibold ${isMe ? 'text-white' : 'text-gray-900'}`;
                        t.textContent = String(link.title || 'Lien');

                        const d = document.createElement('div');
                        d.className = `mt-0.5 text-xs ${isMe ? 'text-white/80' : 'text-slate-500'}`;
                        d.textContent = String(link.domain || '');

                        const actions = document.createElement('div');
                        actions.className = 'mt-3 flex items-center gap-2';

                        const join = document.createElement('a');
                        join.href = String(link.url || '#');
                        join.target = '_blank';
                        join.rel = 'noopener';
                        join.className = 'inline-flex items-center justify-center rounded-full bg-slate-900 text-white px-3 py-1.5 text-xs font-semibold';
                        join.textContent = 'Rejoindre';

                        const copy = document.createElement('button');
                        copy.type = 'button';
                        copy.className = 'inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700';
                        copy.textContent = 'Copier le lien';
                        copy.dataset.copyLink = String(link.url || '');

                        actions.appendChild(join);
                        actions.appendChild(copy);
                        card.appendChild(t);
                        card.appendChild(d);
                        card.appendChild(actions);

                        bodyEl.appendChild(card);
                    } else {
                        bodyEl.className = 'text-sm whitespace-pre-wrap';
                        bodyEl.textContent = body;
                    }
                }
                wrapper.appendChild(bodyEl);

                row.appendChild(avatarWrap);
                row.appendChild(wrapper);
                width.appendChild(row);
                outer.appendChild(width);
                messagesEl.appendChild(outer);

                if (wasAtBottom) {
                    scrollToBottom();
                }
                syncScrollToBottomButton();
                return true;
            }

            function appendLocalMessage(tempId, body) {
                const payload = {
                    id: tempId,
                    body,
                    created_at: new Date().toISOString(),
                    user: { id: currentUserId, name: currentUserName || 'Vous' },
                };
                const ok = appendMessage(payload);
                if (!ok) return;
                const row = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                if (row) {
                    row.dataset.localBody = String(body || '');
                }
                const bubble = row?.querySelector('[data-bubble]');
                if (bubble) {
                    const status = document.createElement('div');
                    status.className = 'mt-1 text-right text-xs opacity-70';
                    status.dataset.localStatus = '1';
                    status.textContent = 'Envoi…';
                    bubble.appendChild(status);
                }
            }

            function markLocalFailed(tempId, errorMessage) {
                const row = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                const bubble = row?.querySelector('[data-bubble]');
                if (!bubble) return;

                bubble.classList.add('border-red-300');

                const status = bubble.querySelector('[data-local-status]');
                if (status) {
                    status.textContent = 'Échec';
                    status.classList.add('text-red-200');
                }

                const actions = document.createElement('div');
                actions.className = 'mt-2 flex items-center justify-end gap-2';

                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'inline-flex items-center justify-center rounded-full bg-white text-slate-900 px-3 py-1.5 text-xs font-semibold';
                retry.textContent = 'Réessayer';
                retry.dataset.retryTempId = tempId;

                const copy = document.createElement('button');
                copy.type = 'button';
                copy.className = 'inline-flex items-center justify-center rounded-full border border-white/30 bg-transparent text-white px-3 py-1.5 text-xs font-semibold';
                copy.textContent = 'Copier';
                copy.dataset.copyText = String(row?.dataset?.localBody || '');

                actions.appendChild(retry);
                actions.appendChild(copy);
                bubble.appendChild(actions);

                if (errorMessage) {
                    const hint = document.createElement('div');
                    hint.className = 'mt-1 text-right text-xs text-red-200/80';
                    hint.textContent = String(errorMessage);
                    bubble.appendChild(hint);
                }
            }

            function setAttachSheetOpen(open) {
                if (!attachSheet) return;
                attachSheet.classList.toggle('hidden', !open);
                if (open) {
                    refreshQuota().catch(() => {});
                }
            }

            function formatBytes(bytes) {
                const b = Number(bytes || 0);
                if (!Number.isFinite(b) || b <= 0) return '0 B';
                const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                let v = b;
                let i = 0;
                while (v >= 1024 && i < units.length - 1) {
                    v /= 1024;
                    i++;
                }
                const txt = (v >= 10 || i === 0) ? v.toFixed(0) : v.toFixed(1);
                return `${txt} ${units[i]}`;
            }

            async function refreshQuota() {
                if (!quotaEl) return;
                const token = getCsrfToken();
                if (!token) return;

                const res = await fetch(quotaUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                });
                if (!res.ok) return;
                const data = await res.json();
                const remaining = Number(data?.remaining_bytes ?? 0);
                quotaEl.textContent = `Espace restant : ${formatBytes(remaining)}`;
            }

            async function postJson(url, payload) {
                const token = getCsrfToken();
                if (!token) throw new Error('missing_csrf');
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(payload || {}),
                });
                const txt = await res.text();
                let json = null;
                try { json = txt ? JSON.parse(txt) : null; } catch (e) {}
                if (!res.ok) {
                    const msg = (json && json.message) ? String(json.message) : `Erreur upload (${res.status})`;
                    const err = new Error(msg);
                    err.status = res.status;
                    err.data = json;
                    throw err;
                }
                return json;
            }

            function putWithProgress(url, blobOrFile, contentType, onProgress) {
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('PUT', url, true);
                    if (contentType) xhr.setRequestHeader('Content-Type', contentType);

                    xhr.upload.onprogress = (evt) => {
                        if (!evt.lengthComputable) return;
                        if (typeof onProgress === 'function') {
                            onProgress(evt.loaded, evt.total);
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve({
                                status: xhr.status,
                                etag: xhr.getResponseHeader('ETag') || xhr.getResponseHeader('etag') || null,
                            });
                        } else {
                            reject(new Error(`Upload failed (${xhr.status})`));
                        }
                    };
                    xhr.onerror = () => reject(new Error('network_error'));
                    xhr.send(blobOrFile);
                });
            }

            function appendUploadPlaceholder(name) {
                const tempId = `upload-${Date.now()}`;
                const payload = {
                    id: tempId,
                    body: `⏳ Envoi de ${name}… 0%`,
                    created_at: new Date().toISOString(),
                    user: { id: currentUserId, name: currentUserName || 'Vous' },
                };
                appendMessage(payload);
                return tempId;
            }

            function updateUploadPlaceholder(tempId, pct) {
                const row = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                if (!row) return;
                const b = row.querySelector('[data-bubble] .text-sm') || row.querySelector('[data-bubble]');
                const bodyEl = row.querySelector('[data-bubble] .text-sm');
                const bubble = row.querySelector('[data-bubble]');
                // find the first body element inside wrapper
                const bodyDiv = bubble?.querySelector('div');
                if (bodyDiv) bodyDiv.textContent = `⏳ Envoi… ${pct}%`;
            }

            function removeUploadPlaceholder(tempId) {
                const row = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                if (row) row.remove();
            }

            function uploadAttachment(file) {
                if (!file) return;

                const token = getCsrfToken();
                if (!token) {
                    alert('Session expirée. Recharge la page.');
                    return;
                }

                const size = Number(file.size || 0);
                if (size > MAX_UPLOAD_BYTES) {
                    alert('Fichier trop volumineux (max 2 Go).');
                    return;
                }

                const mime = String(file.type || 'application/octet-stream');
                const kind = mime.startsWith('video/') ? 'video' : 'photo';

                const tempId = appendUploadPlaceholder(file.name || 'fichier');
                setAttachSheetOpen(false);

                (async () => {
                    try {
                        if (size > MULTIPART_THRESHOLD_BYTES) {
                            const init = await postJson(mpInitUrl, {
                                filename: file.name || 'file',
                                mime,
                                size,
                                kind,
                                context: 'chat',
                            });

                            const partSize = Number(init?.part_size || 0);
                            const parts = Array.isArray(init?.parts) ? init.parts : [];
                            const uploadId = String(init?.upload_id || '');
                            const key = String(init?.key || '');

                            if (!uploadId || !key || !partSize || parts.length === 0) {
                                throw new Error('Multipart init invalide.');
                            }

                            const etags = [];

                            const partBytesArr = parts.map((p) => {
                                const partNumber = Number(p?.part_number || 0);
                                const start = (partNumber - 1) * partSize;
                                const end = Math.min(size, start + partSize);
                                return Math.max(0, end - start);
                            });
                            const partLoadedArr = parts.map(() => 0);

                            const updateOverallProgress = () => {
                                const loaded = partLoadedArr.reduce((a, b) => a + Number(b || 0), 0);
                                const pct = Math.max(0, Math.min(100, Math.round((loaded / size) * 100)));
                                updateUploadPlaceholder(tempId, pct);
                            };

                            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
                            const maxConc = isMobile ? 2 : 4;
                            const concurrency = Math.max(1, Math.min(maxConc, parts.length));

                            const uploadPartAtIndex = async (idx) => {
                                const p = parts[idx];
                                const partNumber = Number(p?.part_number || 0);
                                const uploadUrl = String(p?.upload_url || '');
                                if (!partNumber || !uploadUrl) throw new Error('Part invalide.');

                                const start = (partNumber - 1) * partSize;
                                const end = Math.min(size, start + partSize);
                                const blob = file.slice(start, end);
                                const partBytes = partBytesArr[idx];

                                let attempt = 0;
                                while (true) {
                                    try {
                                        const res = await putWithProgress(uploadUrl, blob, mime, (loaded) => {
                                            const v = Math.max(0, Math.min(partBytes, Number(loaded || 0)));
                                            partLoadedArr[idx] = v;
                                            updateOverallProgress();
                                        });
                                        const etag = String(res?.etag || '').trim();
                                        if (!etag) throw new Error('ETag manquant (R2).');
                                        partLoadedArr[idx] = partBytes;
                                        updateOverallProgress();
                                        etags.push({ part_number: partNumber, etag });
                                        return;
                                    } catch (e) {
                                        attempt++;
                                        if (attempt >= 3) throw e;
                                        await new Promise(r => setTimeout(r, 750 * attempt));
                                    }
                                }
                            };

                            let nextIndex = 0;
                            const workers = Array.from({ length: concurrency }, () => (async () => {
                                while (true) {
                                    const idx = nextIndex;
                                    nextIndex++;
                                    if (idx >= parts.length) return;
                                    await uploadPartAtIndex(idx);
                                }
                            })());

                            await Promise.all(workers);
                            etags.sort((a, b) => Number(a.part_number) - Number(b.part_number));

                            const complete = await postJson(mpCompleteUrl, {
                                key,
                                upload_id: uploadId,
                                parts: etags,
                                mime,
                                size,
                                kind,
                                context: 'chat',
                            });

                            await postJson(finalizeUrl, {
                                key,
                                public_url: complete?.public_url || init?.public_url || null,
                                mime,
                                size,
                                kind,
                                context: 'chat',
                                filename: file.name || null,
                                chat_thread_id: 'default',
                            });
                        } else {
                            const presign = await postJson(presignUrl, {
                                filename: file.name || 'file',
                                mime,
                                size,
                                kind,
                                context: 'chat',
                            });

                            const uploadUrl = String(presign?.upload_url || '');
                            const key = String(presign?.key || '');
                            if (!uploadUrl || !key) throw new Error('Presign invalide.');

                            await putWithProgress(uploadUrl, file, mime, (loaded, total) => {
                                const pct = Math.max(0, Math.min(100, Math.round((loaded / (total || size)) * 100)));
                                updateUploadPlaceholder(tempId, pct);
                            });

                            await postJson(finalizeUrl, {
                                key,
                                public_url: presign?.public_url || null,
                                mime,
                                size,
                                kind,
                                context: 'chat',
                                filename: file.name || null,
                                chat_thread_id: 'default',
                            });
                        }

                        removeUploadPlaceholder(tempId);
                        refreshQuota().catch(() => {});
                    } catch (e) {
                        removeUploadPlaceholder(tempId);
                        alert(String(e?.message || 'Upload impossible.'));
                    }
                })();
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
                    })
                    .joining((user) => {
                        console.log('[chat] joining(user)=', user);
                        const id = userId(user);
                        if (id != null) online.set(id, { id, name: userName(user) });
                        renderOnline(Array.from(online.values()));
                    })
                    .leaving((user) => {
                        console.log('[chat] leaving(user)=', user);
                        const id = userId(user);
                        if (id != null) online.delete(id);
                        renderOnline(Array.from(online.values()));
                    })
                    .listen('.message.sent', (e) => {
                        console.log('[chat] message.sent', e);
                        const appended = appendMessage(e);
                        if (e?.id) lastMessageId = Math.max(lastMessageId, Number(e.id));
                    });
            }

            if (visioBtn) {
                visioBtn.addEventListener('click', async () => {
                    const domain = sanitizeDomain(visioBtn.dataset.jitsiDomain);
                    try {
                        const room = buildVisioRoom();
                        const url = `https://${domain}/${encodeURIComponent(room)}`;

                        openVisioInNewTab(url);
                        await postVisioLinkToChat(url);
                    } catch (e) {
                        alert('Impossible de générer un lien visio sur ce navigateur.');
                    }
                });
            }

            function bindAttachFor(key) {
                const c = composer[key];
                if (!c) return;

                if (c.attachBtn) {
                    c.attachBtn.addEventListener('click', () => {
                        setActiveComposerKey(key);
                        setAttachSheetOpen(true);
                    });
                }

                if (c.textarea) {
                    c.textarea.addEventListener('focus', () => setActiveComposerKey(key));
                }

                if (c.attachInput) {
                    c.attachInput.addEventListener('change', () => {
                        const f = c.attachInput.files && c.attachInput.files[0];
                        c.attachInput.value = '';
                        if (f) uploadAttachment(f);
                    });
                }
            }

            bindAttachFor('mobile');
            bindAttachFor('desktop');

            if (attachPickMedia) {
                attachPickMedia.addEventListener('click', () => {
                    const c = getActiveComposer();
                    c?.attachInput?.click();
                });
            }

            if (attachBackdrop) {
                attachBackdrop.addEventListener('click', () => setAttachSheetOpen(false));
            }
            if (attachCancel) {
                attachCancel.addEventListener('click', () => setAttachSheetOpen(false));
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

            let isSending = false;

            function autoGrowTextarea(ta) {
                if (!ta) return;
                ta.style.height = 'auto';
                const styles = window.getComputedStyle(ta);
                const lineHeight = parseFloat(styles.lineHeight || '20') || 20;
                const max = Math.round(lineHeight * 4);
                ta.style.height = Math.min(ta.scrollHeight, max) + 'px';
            }

            function syncSendButtonFor(c) {
                if (!c?.sendBtn || !c?.textarea) return;
                const body = String(c.textarea.value || '').trim();
                c.sendBtn.disabled = body.length === 0 || isSending;
            }

            function bindComposerHandlers(c) {
                if (!c?.form || !c?.textarea) return;

                autoGrowTextarea(c.textarea);
                syncSendButtonFor(c);

                c.textarea.addEventListener('input', () => {
                    setActiveComposerKey(c.key);
                    autoGrowTextarea(c.textarea);
                    syncSendButtonFor(c);
                });

                c.textarea.addEventListener('keydown', (ev) => {
                    if (ev.key === 'Enter' && !ev.shiftKey) {
                        ev.preventDefault();
                        setActiveComposerKey(c.key);
                        c.form.requestSubmit?.();
                    }
                });

                c.form.addEventListener('submit', async (ev) => {
                    ev.preventDefault();

                    setActiveComposerKey(c.key);
                    if (isSending) return;

                    const body = c.textarea.value.trim();
                    if (!body) return;

                    isSending = true;
                    syncSendButtonFor(c);

                    const tempId = `temp-${Date.now()}`;
                    appendLocalMessage(tempId, body);

                    const token = c.form.querySelector('input[name="_token"]')?.value || getCsrfToken();
                    const socketId = getSocketId();

                    try {
                        const res = await fetch(c.form.action, {
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

                        const ct = (res.headers.get('content-type') || '').toLowerCase();
                        if (!res.ok) {
                            const errMsg = ct.includes('application/json') ? (await res.json())?.message : 'Envoi impossible.';
                            markLocalFailed(tempId, errMsg);
                            return;
                        }

                        const json = ct.includes('application/json') ? await res.json() : null;
                        const tempRow = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                        if (tempRow) tempRow.remove();

                        if (json) {
                            appendMessage(json);
                            if (json?.id) lastMessageId = Math.max(lastMessageId, Number(json.id));
                        }

                        c.textarea.value = '';
                        autoGrowTextarea(c.textarea);
                        syncSendButtonFor(c);
                        c.textarea.focus();
                    } catch (e) {
                        markLocalFailed(tempId, e?.message || 'Envoi impossible.');
                    } finally {
                        isSending = false;
                        syncSendButtonFor(c);
                    }
                });
            }

            bindComposerHandlers(composer.mobile);
            bindComposerHandlers(composer.desktop);

            // Voice dictation (Web Speech API)
            if (attachPickVoice) {
                syncVoiceAvailability();
                if (SpeechRecognitionCtor) {
                    recognition = new SpeechRecognitionCtor();
                    recognition.lang = 'fr-FR';
                    recognition.interimResults = true;
                    recognition.continuous = true;
                    recognition.maxAlternatives = 1;

                    recognition.onresult = (event) => {
                        const ta = getActiveComposer()?.textarea;
                        if (!ta) return;

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
                        ta.value = composed;
                        ta.selectionStart = ta.selectionEnd = ta.value.length;
                        autoGrowTextarea(ta);
                        syncSendButtonFor(getActiveComposer());
                    };

                    recognition.onerror = () => {
                        setDictationUi(false);
                    };

                    recognition.onend = () => {
                        if (dictationActive) {
                            setDictationUi(false);
                            dictationInterim = '';
                        }
                    };
                }

                attachPickVoice.addEventListener('click', () => {
                    if (!SpeechRecognitionCtor || !recognition) return;

                    const c = getActiveComposer();
                    setAttachSheetOpen(false);
                    c?.textarea?.focus();

                    if (dictationActive) {
                        try { recognition.stop(); } catch {}
                        setDictationUi(false);
                        dictationInterim = '';
                        return;
                    }

                    dictationBase = String(c?.textarea?.value || '').trim();
                    dictationInterim = '';
                    setDictationUi(true);
                    try {
                        recognition.start();
                    } catch {
                        setDictationUi(false);
                    }
                });
            }

            if (scrollEl) {
                scrollEl.addEventListener('scroll', () => {
                    syncScrollToBottomButton();
                });
            }
            if (scrollToBottomBtn) {
                scrollToBottomBtn.addEventListener('click', () => {
                    scrollToBottom();
                    syncScrollToBottomButton();
                });
            }

            if (messagesEl) {
                messagesEl.addEventListener('click', async (ev) => {
                    const target = ev.target;
                    if (!(target instanceof HTMLElement)) return;

                    const copyLink = target.closest('[data-copy-link]');
                    if (copyLink instanceof HTMLElement) {
                        const url = String(copyLink.dataset.copyLink || '').trim();
                        if (!url) return;
                        try {
                            await navigator.clipboard.writeText(url);
                            copyLink.textContent = 'Copié';
                            setTimeout(() => { copyLink.textContent = 'Copier le lien'; }, 1200);
                        } catch {
                            alert(url);
                        }
                        return;
                    }

                    const retryBtn = target.closest('[data-retry-temp-id]');
                    if (retryBtn instanceof HTMLElement) {
                        const tempId = String(retryBtn.dataset.retryTempId || '');
                        if (!tempId) return;
                        const row = messagesEl.querySelector(`[data-message-id="${tempId}"]`);
                        const body = String(row?.dataset?.localBody || '').trim();
                        if (textareaEl && body) {
                            textareaEl.value = body;
                            textareaEl.focus();
                            if (formEl) formEl.requestSubmit?.();
                        }
                        return;
                    }

                    const copyTextBtn = target.closest('[data-copy-text]');
                    if (copyTextBtn instanceof HTMLElement) {
                        const txt = String(copyTextBtn.dataset.copyText || '').trim();
                        if (!txt) return;
                        try {
                            await navigator.clipboard.writeText(txt);
                            copyTextBtn.textContent = 'Copié';
                            setTimeout(() => { copyTextBtn.textContent = 'Copier'; }, 1200);
                        } catch {
                            alert(txt);
                        }
                        return;
                    }
                });
            }

            if (backBtn) {
                backBtn.addEventListener('click', () => {
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = @json(route('dashboard'));
                    }
                });
            }

            if (searchBtn && searchBar && searchInput) {
                searchBtn.addEventListener('click', () => {
                    const open = searchBar.classList.contains('hidden');
                    searchBar.classList.toggle('hidden', !open);
                    if (open) {
                        searchInput.focus();
                    } else {
                        searchInput.value = '';
                        // reset
                        messagesEl?.querySelectorAll('[data-message-row]').forEach(el => el.classList.remove('hidden'));
                    }
                });
                searchInput.addEventListener('input', () => {
                    const q = String(searchInput.value || '').trim().toLowerCase();
                    const rows = messagesEl?.querySelectorAll('[data-message-row]') || [];
                    rows.forEach((row) => {
                        const txt = String(row.textContent || '').toLowerCase();
                        row.classList.toggle('hidden', q !== '' && !txt.includes(q));
                    });
                });
            }

            function notifDismissedRecently() {
                try {
                    const raw = localStorage.getItem(NOTIF_DISMISS_KEY);
                    const ts = raw ? Number(raw) : 0;
                    if (!ts) return false;
                    const ageMs = Date.now() - ts;
                    return ageMs < 1000 * 60 * 60 * 24 * 7; // 7 days
                } catch {
                    return false;
                }
            }

            function dismissNotifBanner() {
                try { localStorage.setItem(NOTIF_DISMISS_KEY, String(Date.now())); } catch {}
                if (notifBanner) notifBanner.classList.add('hidden');
            }

            async function refreshNotifBanner() {
                if (!notifBanner || !notifPrimary || !notifLater || !notifClose) return;
                if (!('Notification' in window) || !window.famillePush) return;
                if (notifDismissedRecently()) return;

                const perm = Notification.permission;
                const active = await window.famillePush.hasActive();
                if (active && perm === 'granted') {
                    notifBanner.classList.add('hidden');
                    return;
                }

                notifBanner.classList.remove('hidden');
                notifHelp.textContent = '';

                if (perm === 'denied') {
                    if (notifBody) notifBody.textContent = 'Les notifications sont désactivées pour ce site dans le navigateur.';
                    notifPrimary.textContent = 'Ouvrir les réglages';
                } else {
                    if (notifBody) notifBody.textContent = 'Recevez un push quand un message arrive.';
                    notifPrimary.textContent = 'Activer';
                }

                notifClose.addEventListener('click', dismissNotifBanner);
                notifLater.addEventListener('click', dismissNotifBanner);
                notifPrimary.addEventListener('click', async () => {
                    if (Notification.permission === 'denied') {
                        if (notifHelp) {
                            notifHelp.textContent = 'Ouvre les autorisations du site (icône cadenas) et autorise les notifications.';
                        }
                        return;
                    }
                    notifPrimary.disabled = true;
                    try {
                        const res = await window.famillePush.enable();
                        if (!res?.ok) {
                            if (notifHelp) notifHelp.textContent = 'Impossible d’activer les notifications.';
                        } else {
                            dismissNotifBanner();
                        }
                    } finally {
                        notifPrimary.disabled = false;
                    }
                });
            }

            refreshNotifBanner().catch(() => {});

            function setMediaOpen(open, opts) {
                if (!mediaModal || !mediaImg || !mediaVideo || !mediaTitle || !mediaOpenLink) return;
                mediaModal.classList.toggle('hidden', !open);

                if (!open) {
                    mediaImg.classList.add('hidden');
                    mediaVideo.classList.add('hidden');
                    mediaImg.src = '';
                    mediaImg.alt = '';
                    try { mediaVideo.pause(); } catch {}
                    mediaVideo.removeAttribute('src');
                    mediaVideo.load();
                    mediaTitle.textContent = '';
                    mediaOpenLink.href = '#';
                    return;
                }

                const type = String(opts?.type || '');
                const url = String(opts?.url || '');
                const name = String(opts?.name || (type === 'video' ? 'Vidéo' : 'Photo'));

                mediaTitle.textContent = name;
                mediaOpenLink.href = url || '#';

                if (type === 'video') {
                    mediaImg.classList.add('hidden');
                    mediaVideo.classList.remove('hidden');
                    mediaVideo.src = url;
                    mediaVideo.load();
                } else {
                    mediaVideo.classList.add('hidden');
                    try { mediaVideo.pause(); } catch {}
                    mediaVideo.removeAttribute('src');
                    mediaVideo.load();
                    mediaImg.classList.remove('hidden');
                    mediaImg.src = url;
                    mediaImg.alt = name;
                }
            }

            if (messagesEl) {
                messagesEl.addEventListener('click', (e) => {
                    const el = e.target && e.target.closest ? e.target.closest('[data-chat-media-open]') : null;
                    if (!el) return;
                    e.preventDefault();
                    const url = String(el.dataset.url || '');
                    const type = String(el.dataset.type || '');
                    const name = String(el.dataset.name || (type === 'video' ? 'Vidéo' : 'Photo'));
                    setMediaOpen(true, { url, type, name });
                });
            }

            if (mediaBackdrop) {
                mediaBackdrop.addEventListener('click', () => setMediaOpen(false));
            }
            if (mediaClose) {
                mediaClose.addEventListener('click', () => setMediaOpen(false));
            }
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                if (!mediaModal || mediaModal.classList.contains('hidden')) return;
                setMediaOpen(false);
            });

            function setInfoOpen(open) {
                if (!infoModal) return;
                infoModal.classList.toggle('hidden', !open);
            }

            if (infoBtn) {
                infoBtn.addEventListener('click', () => setInfoOpen(true));
            }
            if (infoBackdrop) {
                infoBackdrop.addEventListener('click', () => setInfoOpen(false));
            }
            if (infoClose) {
                infoClose.addEventListener('click', () => setInfoOpen(false));
            }

            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start, { once: true });
            } else {
                start();
            }
        })();
    </script>
</x-app-layout>
