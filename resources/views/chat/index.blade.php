<x-app-layout pageBgClass="fam-page-bg">
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
                    class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]"
                    aria-label="Ajouter"
                    title="Ajouter"
                >
                    ＋
                </button>

                <div class="flex-1 min-w-0">
                    <div id="chatQuickType" class="hidden mb-2">
                        <div id="chatQuickTypeList" role="listbox" aria-label="Suggestions" class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1"></div>
                    </div>

                    <div class="rounded-full border border-slate-200 bg-white px-4 py-2">
                        <textarea
                            id="body"
                            name="body"
                            rows="1"
                            class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6 max-h-28"
                            placeholder="Écrire un message…"
                            required
                        >{{ old('body') }}</textarea>
                    </div>
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

        <div class="bg-white sm:rounded-2xl shadow-sm flex flex-col h-[calc(100dvh-var(--app-nav-h,0px)-var(--mobile-bottom-nav-h,4rem)-env(safe-area-inset-bottom))] sm:h-[calc(100vh-10rem)] sm:overflow-hidden">
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
                        class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]"
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
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)] disabled:opacity-50 disabled:cursor-not-allowed"
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
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]"
                            aria-label="Rechercher"
                            title="Rechercher"
                        >
                            <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                        </button>

                        <button
                            type="button"
                            id="chatInfoBtn"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]"
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

                    <div id="chatMessages" class="flex flex-col gap-2.5 sm:gap-3 p-4 sm:p-6">
                        <div id="chatNotifBanner" class="hidden rounded-2xl border border-black/10 bg-white p-4">
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
                                <button type="button" id="chatNotifLater" class="inline-flex items-center justify-center rounded-full border border-black/10 bg-white text-slate-700 px-4 py-2 text-sm font-semibold">Plus tard</button>
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
                                $avatarUrl = '';
                                try {
                                    $avatarUrl = (string) (avatarUrl($m->user) ?? '');
                                } catch (\Throwable $e) {
                                    $avatarUrl = '';
                                }

                                $prev = $messages[$i - 1] ?? null;
                                $next = $messages[$i + 1] ?? null;
                                $prevUserId = $prev ? (int) $prev->user_id : null;
                                $nextUserId = $next ? (int) $next->user_id : null;
                                $isGroupStart = $prevUserId !== $userId;
                                $isGroupEnd = $nextUserId !== $userId;

                                $dayKey = $m->created_at?->format('Y-m-d') ?? null;
                                $dayLabel = $m->created_at?->format('d/m/Y') ?? '';

                                $reactionSummary = (array) (($reactionSummaries ?? [])[(int) $m->id] ?? []);
                                $isDeletedForAll = $m->deleted_for_all_at !== null;
                                if ($isDeletedForAll) {
                                    $reactionSummary = [];
                                }
                            @endphp

                            @if($dayKey && $dayKey !== $prevDay)
                                <div class="py-2 flex justify-center">
                                    <div class="text-xs text-slate-500 bg-white border border-black/10 rounded-full px-3 py-1">
                                        {{ $dayLabel }}
                                    </div>
                                </div>
                                @php
                                    $prevDay = $dayKey;
                                @endphp
                            @endif

                            @php
                                $att = null;
                                $link = null;
                                if (!$isDeletedForAll) {
                                    $att = $parseAttachment($m->body);
                                    $link = $att ? null : $parseLinkCard($m->body);
                                }
                            @endphp

                            <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }} group" data-message-row data-user-id="{{ $userId }}" data-message-id="{{ $m->id }}" data-day-key="{{ $dayKey }}" data-deleted="{{ $isDeletedForAll ? '1' : '0' }}" data-reaction-summary='@json($reactionSummary)'>
                                <div class="{{ $att ? 'w-[clamp(240px,72vw,420px)] max-w-[92vw] sm:w-[clamp(320px,48vw,520px)] sm:max-w-[520px]' : 'max-w-[72%] sm:max-w-[68%]' }}">
                                    @if($isGroupStart)
                                        <div class="mb-1 text-xs text-slate-500 {{ $isMe ? 'text-right' : '' }}" title="{{ $name }}">
                                            {{ $firstName }} · {{ $m->created_at?->format('H:i') }}
                                        </div>
                                    @endif

                                    <div class="flex items-end gap-2 {{ $isMe ? 'flex-row-reverse' : '' }}">
                                        <div class="shrink-0 {{ $isGroupEnd ? '' : 'invisible' }}" data-avatar>
                                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full overflow-hidden flex items-center justify-center text-xs font-semibold {{ $avatarUrl !== '' ? 'bg-white border border-black/10' : $colors['avatar'] }}">
                                                @if($avatarUrl !== '')
                                                    <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                                                @else
                                                    {{ $initials }}
                                                @endif
                                            </div>
                                        </div>

                                        <div class="relative {{ $att ? 'p-0 border-0 bg-transparent' : 'px-4 py-3 border' }} {{ $att ? '' : ($isMe ? 'bg-slate-900 text-white border-slate-900 rounded-2xl rounded-br-md' : 'bg-white text-gray-900 border-slate-200 rounded-2xl rounded-bl-md') }}" data-bubble>
                                            @if(!$isDeletedForAll)
                                                <button
                                                    type="button"
                                                    class="hidden sm:inline-flex absolute -top-3 {{ $isMe ? '-left-3' : '-right-3' }} w-8 h-8 items-center justify-center rounded-full border border-black/10 bg-white text-slate-700 shadow-sm opacity-0 group-hover:opacity-100 transition-opacity"
                                                    data-reaction-trigger
                                                    data-message-id="{{ $m->id }}"
                                                    aria-label="Réagir"
                                                    title="Réagir"
                                                >
                                                    🙂
                                                </button>
                                            @endif

                                            @if($isDeletedForAll)
                                                <div class="text-sm italic {{ $isMe ? 'text-white/80' : 'text-slate-500' }}">Message supprimé</div>
                                            @elseif ($att)
                                                @php
                                                    $attType = (string) ($att['media_type'] ?? '');
                                                    $attUrl = (string) ($att['url'] ?? '#');
                                                    $attOpenUrl = (string) ($att['open_url'] ?? $attUrl);
                                                    $attThumb = (string) ($att['thumb_url'] ?? '');
                                                    $attName = (string) ($att['name'] ?? ($attType === 'video' ? 'Vidéo' : 'Photo'));
                                                    $attCaption = trim((string) ($att['caption'] ?? $att['text'] ?? ''));
                                                    $attW = (int) ($att['width'] ?? 0);
                                                    $attH = (int) ($att['height'] ?? 0);
                                                    $attLandscape = ($attW > 0 && $attH > 0) ? ($attW > $attH) : false;
                                                    $attMediaH = $attType === 'video'
                                                        ? ($attLandscape ? 'h-[clamp(10rem,30vh,36vh)]' : 'h-[clamp(14rem,46vh,52vh)]')
                                                        : ($attLandscape ? 'h-[clamp(10rem,28vh,36vh)]' : 'h-[clamp(14rem,40vh,52vh)]');
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="group block w-full text-left rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/10 focus-visible:ring-offset-2 focus-visible:ring-offset-white"
                                                    style="-webkit-tap-highlight-color: transparent;"
                                                    aria-label="Ouvrir {{ $attName }}"
                                                    data-chat-media-open="1"
                                                    data-url="{{ $attUrl }}"
                                                    data-open-url="{{ $attOpenUrl }}"
                                                    data-type="{{ $attType }}"
                                                    data-name="{{ $attName }}"
                                                    data-thumb="{{ $attThumb }}"
                                                >
                                                    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                                                        <div class="relative w-full {{ $attMediaH }} bg-slate-100 animate-pulse" data-chat-media-card>
                                                            @if ($attThumb !== '')
                                                                <img src="{{ $attThumb }}" alt="{{ $attName }}" class="block w-full h-full object-cover" loading="lazy" data-chat-media-thumb />
                                                            @else
                                                                <div class="w-full h-full flex items-center justify-center text-xs text-slate-500">{{ $attName }}</div>
                                                            @endif

                                                            <div class="absolute top-2 right-2 pointer-events-none opacity-60 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                                                <div class="w-9 h-9 rounded-full bg-black/35 backdrop-blur flex items-center justify-center text-white">
                                                                    <i class="ph ph-arrows-out" aria-hidden="true"></i>
                                                                </div>
                                                            </div>

                                                            @if ($attType === 'video')
                                                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                                                    <div class="w-11 h-11 rounded-full bg-black/35 backdrop-blur-sm flex items-center justify-center text-white text-lg">▶</div>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        @if ($attCaption !== '')
                                                            <div class="px-4 py-3 text-sm text-slate-600 bg-white">
                                                                {{ $attCaption }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </button>
                                            @elseif ($link)
                                                <div class="rounded-xl border border-black/10 {{ $isMe ? 'bg-white/10' : 'bg-white' }} p-3">
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

                                    <div class="mt-1 flex flex-wrap gap-1.5 {{ $isMe ? 'justify-end' : 'justify-start' }}" data-reactions-row>
                                        @if(!$isDeletedForAll)
                                            @foreach($reactionSummary as $r)
                                                @php
                                                    $emoji = (string) ($r['emoji'] ?? '');
                                                    $count = (int) ($r['count'] ?? 0);
                                                    $mine = (bool) ($r['reacted_by_me'] ?? false);
                                                @endphp
                                                @if($emoji !== '' && $count > 0)
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-semibold shadow-sm {{ $mine ? 'border-teal-300 bg-teal-50 text-teal-800' : 'border-black/10 bg-amber-50/60 text-slate-700' }}"
                                                        data-reaction-chip
                                                        data-emoji="{{ $emoji }}"
                                                        data-message-id="{{ $m->id }}"
                                                        aria-label="Réactions {{ $emoji }}"
                                                    >
                                                        <span class="text-sm leading-none">{{ $emoji }}</span>
                                                        <span class="text-[11px] leading-none">{{ $count }}</span>
                                                    </button>
                                                @endif
                                            @endforeach
                                        @endif
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
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]"
                            aria-label="Ajouter"
                            title="Ajouter"
                        >
                            ＋
                        </button>

                        <div class="flex-1 min-w-0">
                            <div id="chatQuickTypeDesktop" class="hidden mb-2">
                                <div id="chatQuickTypeListDesktop" role="listbox" aria-label="Suggestions" class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1"></div>
                            </div>

                            <div class="rounded-full border border-slate-200 bg-white px-4 py-2">
                                <textarea
                                    id="bodyDesktop"
                                    name="body"
                                    rows="1"
                                    class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6"
                                    placeholder="Écrire un message…"
                                    required
                                >{{ old('body') }}</textarea>
                            </div>
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

        @include('chat.partials.overlays')
    </div>
    @include('chat.partials.bootstrap')
    @vite(['resources/js/chat-page.js'])
</x-app-layout>
