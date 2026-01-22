<x-app-layout pageBgClass="fam-page-bg">
    <div class="chat-theme">
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
        <div class="px-4 py-2 bg-[color:var(--chat-surface)]/95 supports-[backdrop-filter]:bg-[color:var(--chat-surface)]/80 supports-[backdrop-filter]:backdrop-blur-xl border-t border-[color:var(--chat-border-soft)] shadow-[0_-10px_25px_rgba(15,23,42,0.06)]">
            <div id="chatSoloHint" class="hidden mb-2 text-xs text-[color:var(--chat-muted)]"></div>
            <form id="chatForm" method="POST" action="{{ route('chat.store') }}" class="flex items-end gap-2">
                @csrf
                <button
                    type="button"
                    id="chatAttachBtn"
                    class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] text-[color:var(--chat-muted)] hover:bg-white/70 active:bg-white/90"
                    aria-label="Ajouter"
                    title="Ajouter"
                >
                    ＋
                </button>

                <div class="flex-1 min-w-0">
                    <div id="chatQuickType" class="hidden mb-2">
                        <div id="chatQuickTypeList" role="listbox" aria-label="Suggestions" class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1"></div>
                    </div>

                    <div class="rounded-full border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] px-4 py-2 focus-within:ring-2 focus-within:ring-[color:var(--chat-primary)]/25 focus-within:border-[color:rgba(14,165,160,0.25)]">
                        <textarea
                            id="body"
                            name="body"
                            rows="1"
                            class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6 max-h-28 bg-transparent text-[color:var(--chat-text)] placeholder:text-[color:var(--chat-muted)]/70"
                            placeholder="Écrire un message…"
                            required
                        >{{ old('body') }}</textarea>
                    </div>
                </div>

                <input type="file" id="chatAttachInput" class="hidden" accept="image/*,video/*" />

                <button
                    type="submit"
                    id="chatSendBtn"
                    class="w-11 h-11 rounded-full inline-flex items-center justify-center bg-[color:var(--chat-primary)] text-white font-semibold shadow-[0_10px_25px_rgba(14,165,160,0.22)] hover:bg-[color:var(--chat-primary-hover)] active:scale-[0.99] disabled:opacity-50"
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
            <div class="bg-[color:var(--chat-surface)] rounded-2xl shadow-[0_1px_0_rgba(15,23,42,0.03),0_10px_25px_rgba(15,23,42,0.06)] p-4 text-sm text-[color:var(--chat-text)] border border-[color:var(--chat-border-soft)]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-[color:var(--chat-surface)] rounded-2xl shadow-[0_1px_0_rgba(15,23,42,0.03),0_10px_25px_rgba(15,23,42,0.06)] p-4 border border-[color:var(--chat-border-soft)]">
                <div class="text-sm font-semibold text-red-600">Erreur</div>
                <ul class="mt-2 space-y-1 text-sm text-red-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="chat-shell sm:rounded-2xl flex flex-col h-[calc(100dvh-var(--app-nav-h,0px)-var(--mobile-bottom-nav-h,4rem)-env(safe-area-inset-bottom))] sm:h-[calc(100vh-10rem)] sm:overflow-hidden">
            @php
                $visioDomain = trim((string) (config('visio.jitsi_domain') ?? 'meet.jit.si'));
                $visioProvider = (string) (config('visio.provider') ?? 'link');
                $visioUrl = trim((string) (config('visio.url') ?? ''));
                $visioAvailable = $visioProvider === 'jitsi'
                    ? ($visioDomain !== '')
                    : ($visioUrl !== '' || $visioDomain !== '');
            @endphp
            <div class="sticky top-0 z-20 bg-[color:var(--chat-surface)]/95 backdrop-blur border-b border-[color:var(--chat-border-soft)]">
                <div style="padding-top: calc(env(safe-area-inset-top) + 0.5rem)">
                    <div class="h-14 px-4 sm:px-6 pb-2 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        id="chatBackBtn"
                        class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] text-[color:var(--chat-muted)] hover:bg-white/70 active:bg-white/90"
                        aria-label="Retour"
                        title="Retour"
                    >
                        <i class="ph ph-arrow-left" aria-hidden="true"></i>
                    </button>

                    <div class="min-w-0 flex-1 text-center">
                        <div class="text-sm sm:text-base font-semibold text-[color:var(--chat-text)] leading-tight">Famille</div>
                        <div class="text-xs text-[color:var(--chat-muted)] leading-tight">
                            <span class="text-emerald-600">●</span>
                            <span id="chatOnlineCount" class="font-semibold text-[color:var(--chat-text)]">{{ $onlineList->count() }}</span>
                            <span id="chatPresenceLabel">en ligne</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            id="chatVisioBtn"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] text-[color:var(--chat-muted)] hover:bg-white/70 active:bg-white/90 disabled:opacity-50 disabled:cursor-not-allowed"
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
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] text-[color:var(--chat-muted)] hover:bg-white/70 active:bg-white/90"
                            aria-label="Rechercher"
                            title="Rechercher"
                        >
                            <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                        </button>

                        <button
                            type="button"
                            id="chatInfoBtn"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] text-[color:var(--chat-muted)] hover:bg-white/70 active:bg-white/90"
                            aria-label="Infos"
                            title="Infos"
                        >
                            <i class="ph ph-info" aria-hidden="true"></i>
                        </button>
                    </div>
                    </div>
                </div>

                <div id="chatSearchBar" class="hidden px-4 sm:px-6 pb-3">
                    <div class="rounded-2xl border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] px-4 py-2">
                        <input
                            type="search"
                            id="chatSearchInput"
                            class="w-full border-0 p-0 focus:ring-0 text-sm bg-transparent text-[color:var(--chat-text)] placeholder:text-[color:var(--chat-muted)]/70"
                            placeholder="Rechercher dans la conversation…"
                        />
                    </div>
                </div>
            </div>

            <div id="chatScroll" class="chat-scroll-bg flex-1 min-h-0 overflow-y-auto overscroll-contain pb-[calc(6rem+var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom))] sm:pb-0">
                <div class="relative">
                    <button
                        type="button"
                        id="chatScrollToBottom"
                        class="hidden absolute right-4 bottom-4 z-10 items-center gap-2 rounded-full bg-[color:var(--chat-text)] text-white shadow-lg px-4 py-2 text-sm font-semibold"
                        aria-label="Nouveau message"
                        title="Nouveau message"
                    >
                        <i class="ph ph-arrow-down" aria-hidden="true"></i>
                        <span>Nouveau</span>
                    </button>

                    <div id="chatMessages" class="flex flex-col gap-1.5 sm:gap-2 p-4 sm:p-6">
                        <div id="chatNotifBanner" class="hidden rounded-2xl border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface)] p-4 shadow-[0_1px_0_rgba(15,23,42,0.03),0_10px_25px_rgba(15,23,42,0.06)]">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-[color:var(--chat-text)]">Activer les notifications</div>
                                    <div id="chatNotifBody" class="mt-1 text-sm text-[color:var(--chat-muted)]">Recevez un push quand un message arrive.</div>
                                    <div id="chatNotifHelp" class="mt-2 text-xs text-[color:var(--chat-muted)]"></div>
                                </div>
                                <button type="button" id="chatNotifClose" class="w-8 h-8 rounded-full inline-flex items-center justify-center text-[color:var(--chat-muted)] hover:bg-[color:var(--chat-surface-2)]" aria-label="Fermer" title="Fermer">✕</button>
                            </div>
                            <div class="mt-3 flex items-center gap-2">
                                <button type="button" id="chatNotifPrimary" class="inline-flex items-center justify-center rounded-full bg-[color:var(--chat-primary)] text-white px-4 py-2 text-sm font-semibold shadow-[0_10px_25px_rgba(14,165,160,0.20)] hover:bg-[color:var(--chat-primary-hover)]">Activer</button>
                                <button type="button" id="chatNotifLater" class="inline-flex items-center justify-center rounded-full border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] text-[color:var(--chat-text)] px-4 py-2 text-sm font-semibold hover:bg-white/70">Plus tard</button>
                            </div>
                        </div>
                    @if(($messages ?? collect())->count() === 0)
                        <div id="chatEmptyState" class="py-12 text-center">
                            <div class="text-base font-semibold text-[color:var(--chat-text)]">👋 Aucun message pour l’instant</div>
                            <div class="text-sm text-[color:var(--chat-muted)] mt-1">Lance la discussion !</div>
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

                                $tz = (string) config('app.timezone', 'UTC');
                                $createdAt = $m->created_at?->timezone($tz);
                                $dayKey = $createdAt?->format('Y-m-d') ?? null;
                                $dayLabel = '';
                                if ($createdAt) {
                                    if ($createdAt->isToday()) {
                                        $dayLabel = 'Aujourd’hui';
                                    } elseif ($createdAt->isYesterday()) {
                                        $dayLabel = 'Hier';
                                    } else {
                                        $dayLabel = $createdAt->locale(app()->getLocale())->translatedFormat('D j M');
                                    }
                                }

                                $reactionSummary = (array) (($reactionSummaries ?? [])[(int) $m->id] ?? []);
                                $isDeletedForAll = $m->deleted_for_all_at !== null;
                                if ($isDeletedForAll) {
                                    $reactionSummary = [];
                                }
                            @endphp

                            @if($dayKey && $dayKey !== $prevDay)
                                <div class="py-2 flex justify-center">
                                    <div class="text-xs font-semibold text-[color:var(--chat-muted)] bg-[color:rgba(255,255,255,0.75)] supports-[backdrop-filter]:bg-[color:rgba(255,255,255,0.55)] supports-[backdrop-filter]:backdrop-blur-xl border border-[color:var(--chat-border-soft)] rounded-full px-3 py-1 shadow-[0_1px_0_rgba(15,23,42,0.02)]">
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

                            <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }} group {{ $isGroupStart ? 'mt-2.5' : 'mt-0.5' }}" data-message-row data-user-id="{{ $userId }}" data-message-id="{{ $m->id }}" data-day-key="{{ $dayKey }}" data-deleted="{{ $isDeletedForAll ? '1' : '0' }}" data-reaction-summary='@json($reactionSummary)'>
                                <div class="{{ $att ? 'w-[clamp(240px,72vw,420px)] max-w-[92vw] sm:w-[clamp(320px,48vw,520px)] sm:max-w-[520px]' : 'max-w-[72%] sm:max-w-[68%]' }}">
                                    @if($isGroupStart)
                                        <div class="mb-1 flex items-center gap-1.5 text-xs {{ $isMe ? 'justify-end' : '' }}" title="{{ $name }}">
                                            <span class="font-semibold text-[color:var(--chat-text)]/90">{{ $firstName }}</span>
                                            <span class="text-[color:var(--chat-muted)]">·</span>
                                            <span class="text-[0.7rem] font-semibold text-[color:var(--chat-muted)]">{{ $m->created_at?->format('H:i') }}</span>
                                        </div>
                                    @endif

                                    <div class="flex items-end gap-2 {{ $isMe ? 'flex-row-reverse' : '' }}">
                                        <div class="shrink-0 {{ $isGroupEnd ? '' : 'invisible' }}" data-avatar>
                                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full overflow-hidden flex items-center justify-center text-xs font-semibold {{ $avatarUrl !== '' ? 'bg-[color:var(--chat-surface)] border border-[color:var(--chat-border-soft)]' : $colors['avatar'] }}">
                                                @if($avatarUrl !== '')
                                                    <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                                                @else
                                                    {{ $initials }}
                                                @endif
                                            </div>
                                        </div>

                                        <div class="relative {{ $att ? 'p-0 border-0 bg-transparent' : 'px-4 py-3 border' }} {{ $att ? '' : ($isMe ? 'bg-[color:var(--chat-primary)] text-white border-[color:rgba(14,165,160,0.35)] shadow-[0_8px_18px_rgba(14,165,160,0.22)] rounded-2xl rounded-br-md' : 'bg-[color:var(--chat-surface)] text-[color:var(--chat-text)] border-[color:var(--chat-border-soft)] shadow-[0_1px_0_rgba(15,23,42,0.03),0_10px_25px_rgba(15,23,42,0.07)] rounded-2xl rounded-bl-md') }}" data-bubble>
                                            @if(!$isDeletedForAll)
                                                <button
                                                    type="button"
                                                    class="hidden sm:inline-flex absolute -top-3 {{ $isMe ? '-left-3' : '-right-3' }} w-8 h-8 items-center justify-center rounded-full border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] text-[color:var(--chat-muted)] shadow-sm opacity-0 group-hover:opacity-100 transition-opacity"
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
                                                    class="group block w-full text-left rounded-3xl focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--chat-primary)]/25 focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--chat-bg)]"
                                                    style="-webkit-tap-highlight-color: transparent;"
                                                    aria-label="Ouvrir {{ $attName }}"
                                                    data-chat-media-open="1"
                                                    data-url="{{ $attUrl }}"
                                                    data-open-url="{{ $attOpenUrl }}"
                                                    data-type="{{ $attType }}"
                                                    data-name="{{ $attName }}"
                                                    data-thumb="{{ $attThumb }}"
                                                >
                                                    <div class="rounded-3xl bg-[color:var(--chat-surface-2)] border border-[color:var(--chat-border-soft)] p-2 shadow-[0_1px_0_rgba(15,23,42,0.03),0_12px_28px_rgba(15,23,42,0.08)]">
                                                        <div class="overflow-hidden rounded-2xl border border-[color:var(--chat-border-soft)] bg-white">
                                                            <div class="relative w-full {{ $attMediaH }} bg-slate-100 animate-pulse" data-chat-media-card>
                                                                @if ($attThumb !== '')
                                                                    <img src="{{ $attThumb }}" alt="{{ $attName }}" class="block w-full h-full object-cover" loading="lazy" data-chat-media-thumb />
                                                                @else
                                                                    <div class="w-full h-full flex items-center justify-center text-xs text-[color:var(--chat-muted)]">{{ $attName }}</div>
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
                                                        </div>

                                                        <div class="mt-2 flex items-center justify-between gap-2">
                                                            <div class="min-w-0 text-[0.7rem] font-semibold text-[color:var(--chat-muted)] truncate">
                                                                <span class="text-[color:var(--chat-text)]/80">{{ $firstName }}</span>
                                                                <span class="text-[color:var(--chat-muted)]">·</span>
                                                                <span>{{ $m->created_at?->format('H:i') }}</span>
                                                            </div>
                                                            <div class="shrink-0 text-[0.7rem] font-extrabold text-[color:var(--chat-primary)] inline-flex items-center gap-1">
                                                                <span>Ouvrir</span>
                                                                <i class="ph ph-arrow-up-right" aria-hidden="true"></i>
                                                            </div>
                                                        </div>

                                                        @if ($attCaption !== '')
                                                            <div class="mt-1 text-sm text-[color:var(--chat-text)]/80 whitespace-pre-line">
                                                                {{ $attCaption }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </button>
                                            @elseif ($link)
                                                <div class="rounded-xl border border-[color:var(--chat-border-soft)] {{ $isMe ? 'bg-white/10' : 'bg-[color:var(--chat-surface-2)]' }} p-3">
                                                    <div class="text-sm font-semibold {{ $isMe ? 'text-white' : 'text-[color:var(--chat-text)]' }}">{{ $link['title'] }}</div>
                                                    <div class="mt-0.5 text-xs {{ $isMe ? 'text-white/80' : 'text-[color:var(--chat-muted)]' }}">{{ $link['domain'] }}</div>
                                                    <div class="mt-3 flex items-center gap-2">
                                                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-full bg-[color:var(--chat-primary)] text-white px-3 py-1.5 text-xs font-semibold shadow-[0_10px_25px_rgba(14,165,160,0.18)]">Rejoindre</a>
                                                        <button type="button" class="inline-flex items-center justify-center rounded-full border border-[color:var(--chat-border-soft)] bg-white/70 px-3 py-1.5 text-xs font-semibold text-[color:var(--chat-text)] hover:bg-white" data-copy-link="{{ $link['url'] }}">Copier le lien</button>
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
                                                        class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-semibold shadow-sm {{ $mine ? 'border-[color:rgba(14,165,160,0.35)] bg-[color:rgba(14,165,160,0.12)] text-[color:rgba(11,90,87,1)]' : 'border-[color:var(--chat-border-soft)] bg-[color:rgba(255,255,255,0.70)] text-[color:var(--chat-text)]' }}"
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

            <div class="hidden sm:block border-t border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface)] sticky bottom-0 z-40">
                <div class="px-4 sm:px-6 py-3">
                    <div id="chatSoloHintDesktop" class="hidden mb-2 text-xs text-[color:var(--chat-muted)]"></div>
                    <form id="chatFormDesktop" method="POST" action="{{ route('chat.store') }}" class="flex items-end gap-2">
                        @csrf
                        <button
                            type="button"
                            id="chatAttachBtnDesktop"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] text-[color:var(--chat-muted)] hover:bg-white/70 active:bg-white/90"
                            aria-label="Ajouter"
                            title="Ajouter"
                        >
                            ＋
                        </button>

                        <div class="flex-1 min-w-0">
                            <div id="chatQuickTypeDesktop" class="hidden mb-2">
                                <div id="chatQuickTypeListDesktop" role="listbox" aria-label="Suggestions" class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1"></div>
                            </div>

                            <div class="rounded-full border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface-2)] px-4 py-2 focus-within:ring-2 focus-within:ring-[color:var(--chat-primary)]/25 focus-within:border-[color:rgba(14,165,160,0.25)]">
                                <textarea
                                    id="bodyDesktop"
                                    name="body"
                                    rows="1"
                                    class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6 bg-transparent text-[color:var(--chat-text)] placeholder:text-[color:var(--chat-muted)]/70"
                                    placeholder="Écrire un message…"
                                    required
                                >{{ old('body') }}</textarea>
                            </div>
                        </div>

                        <input type="file" id="chatAttachInputDesktop" class="hidden" accept="image/*,video/*" />

                        <button
                            type="submit"
                            id="chatSendBtnDesktop"
                            class="w-11 h-11 rounded-full inline-flex items-center justify-center bg-[color:var(--chat-primary)] text-white font-semibold shadow-[0_10px_25px_rgba(14,165,160,0.22)] hover:bg-[color:var(--chat-primary-hover)] active:scale-[0.99] disabled:opacity-50"
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

        <div id="chatAttachSheet" class="fixed inset-0 z-50 hidden" aria-hidden="true">
            <div id="chatAttachBackdrop" class="absolute inset-0 bg-black/30 backdrop-blur-sm opacity-0 transition-opacity duration-[220ms] ease-out motion-reduce:transition-none"></div>
            <div class="absolute inset-x-0 bottom-0 flex justify-center">
                <div
                    id="chatAttachPanel"
                    class="w-full max-w-[560px] rounded-t-3xl bg-[color:var(--chat-surface)] border border-[color:var(--chat-border-soft)] shadow-[0_-18px_55px_rgba(15,23,42,0.18)] p-4 pb-[calc(env(safe-area-inset-bottom)+16px)] opacity-0 translate-y-6 transition-[transform,opacity] duration-[220ms] ease-out motion-reduce:transition-none motion-reduce:transform-none"
                    role="dialog"
                    aria-label="Ajouter"
                >
                    <div class="mx-auto h-1 w-9 rounded-full bg-black/10"></div>

                    <div class="mt-3">
                        <div class="text-sm font-semibold text-[color:var(--chat-text)]">Ajouter</div>
                        <div id="chatAttachQuota" class="mt-1 text-xs text-[color:var(--chat-muted)]"></div>
                    </div>

                    <div class="mt-3 grid gap-2">
                        <button
                            type="button"
                            id="chatAttachPickMedia"
                            class="group w-full h-14 inline-flex items-center justify-between rounded-2xl border border-[color:var(--chat-border)] bg-[color:var(--chat-surface-2)] px-4 text-sm font-semibold text-[color:var(--chat-text)] transition-[transform,background-color,border-color] hover:bg-white/75 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgba(14,165,160,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--chat-surface)]"
                        >
                            <span>Photo / Vidéo</span>
                            <i class="ph ph-image text-[22px] text-[color:var(--chat-muted)] transition-colors group-active:text-[color:var(--chat-primary)] group-focus-visible:text-[color:var(--chat-primary)]" aria-hidden="true"></i>
                        </button>

                        <button
                            type="button"
                            id="chatAttachPickVoice"
                            class="group w-full h-14 inline-flex items-center justify-between rounded-2xl border border-[color:var(--chat-border)] bg-[color:var(--chat-surface-2)] px-4 text-sm font-semibold text-[color:var(--chat-text)] transition-[transform,background-color,border-color] hover:bg-white/75 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgba(14,165,160,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--chat-surface)]"
                        >
                            <span>Dicter</span>
                            <i class="ph ph-microphone text-[22px] text-[color:var(--chat-muted)] transition-colors group-active:text-[color:var(--chat-primary)] group-focus-visible:text-[color:var(--chat-primary)]" aria-hidden="true"></i>
                        </button>
                    </div>

                    <button
                        type="button"
                        id="chatAttachCancel"
                        class="mt-3 w-full h-11 rounded-2xl text-sm font-semibold text-[color:var(--chat-muted)] hover:bg-[color:var(--chat-surface-2)] active:bg-white/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgba(14,165,160,0.30)] focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--chat-surface)]"
                    >
                        Annuler
                    </button>
                </div>
            </div>
        </div>

        <div id="chatInfoModal" class="fixed inset-0 z-50 hidden">
            <div id="chatInfoBackdrop" class="absolute inset-0 bg-black/40"></div>
            <div class="absolute inset-x-0 bottom-0 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:top-24 sm:bottom-auto w-full sm:w-[420px] bg-[color:var(--chat-surface)] rounded-t-3xl sm:rounded-3xl p-4 shadow-[0_1px_0_rgba(15,23,42,0.03),0_24px_70px_rgba(15,23,42,0.22)] border border-[color:var(--chat-border-soft)]">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-[color:var(--chat-text)]">Participants</div>
                    <button type="button" id="chatInfoClose" class="w-9 h-9 rounded-full inline-flex items-center justify-center text-[color:var(--chat-muted)] hover:bg-[color:var(--chat-surface-2)]" aria-label="Fermer" title="Fermer">✕</button>
                </div>
                <div class="mt-1 text-xs text-[color:var(--chat-muted)]"><span id="chatInfoCount">0</span> en ligne</div>
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

        <div id="chatQuickTypeMenu" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
            <div id="chatQuickTypeMenuBackdrop" class="absolute inset-0"></div>
            <div id="chatQuickTypeMenuPanel" role="menu" aria-label="Actions suggestion" class="absolute min-w-[14rem] rounded-2xl border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface)] shadow-[0_1px_0_rgba(15,23,42,0.03),0_24px_70px_rgba(15,23,42,0.22)] p-1">
                <div id="chatQuickTypeMenuMain">
                    <button type="button" data-qt-action="insert" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-[color:var(--chat-text)] hover:bg-[color:rgba(14,165,160,0.10)]">Insérer</button>
                    <button type="button" data-qt-action="copy" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-[color:var(--chat-text)] hover:bg-[color:rgba(14,165,160,0.10)]">Copier</button>
                    <button type="button" data-qt-action="pin" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-[color:var(--chat-text)] hover:bg-[color:rgba(14,165,160,0.10)]">Épingler</button>
                    <button type="button" data-qt-action="unpin" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-[color:var(--chat-text)] hover:bg-[color:rgba(14,165,160,0.10)]">Désépingler</button>
                    <div class="h-px bg-[color:var(--chat-border-soft)] my-1"></div>
                    <button type="button" data-qt-action="remove_recent" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-[color:var(--chat-text)] hover:bg-[color:rgba(14,165,160,0.10)]">Retirer des récents</button>
                    <button type="button" data-qt-action="hide" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Masquer</button>
                    <div class="h-px bg-[color:var(--chat-border-soft)] my-1"></div>
                    <button type="button" data-qt-action="manage_hidden" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-[color:var(--chat-text)] hover:bg-[color:rgba(14,165,160,0.10)]">Gérer les masqués…</button>
                </div>

                <div id="chatQuickTypeMenuHidden" class="hidden">
                    <div class="flex items-center justify-between gap-2 px-2 py-2">
                        <button type="button" data-qt-action="hidden_back" class="rounded-xl px-2 py-1 text-sm font-semibold text-[color:var(--chat-muted)] hover:bg-[color:rgba(14,165,160,0.10)]">← Retour</button>
                        <div class="text-sm font-semibold text-[color:var(--chat-text)]">Masqués</div>
                        <button type="button" data-qt-action="hidden_clear" class="rounded-xl px-2 py-1 text-sm font-semibold text-red-600 hover:bg-red-50">Tout rétablir</button>
                    </div>
                    <div class="h-px bg-[color:var(--chat-border-soft)] my-1"></div>
                    <div id="chatQuickTypeHiddenList" class="max-h-64 overflow-auto"></div>
                </div>
            </div>
        </div>

        <div id="chatQuickTypeToast" class="fixed inset-x-0 bottom-[calc(var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom)+0.75rem)] sm:bottom-6 z-[70] pointer-events-none hidden">
            <div class="mx-auto w-fit rounded-full bg-[color:var(--chat-text)] text-white px-3 py-1.5 text-xs font-semibold shadow-lg">Copié</div>
        </div>

        <div id="chatReactionsPicker" class="fixed inset-0 z-[80] hidden" aria-hidden="true">
            <div id="chatReactionsPickerBackdrop" class="absolute inset-0"></div>
            <div id="chatReactionsPickerPanel" class="fixed rounded-2xl border border-[color:var(--chat-border-soft)] bg-[color:var(--chat-surface)] shadow-[0_1px_0_rgba(15,23,42,0.03),0_24px_70px_rgba(15,23,42,0.22)] px-2 py-2">
                <div class="flex items-center gap-1.5">
                    @foreach(\App\Services\ChatReactions::BASE_EMOJIS as $e)
                        <button type="button" class="w-10 h-10 rounded-xl hover:bg-[color:rgba(14,165,160,0.10)] text-xl" data-reaction-pick="{{ $e }}" aria-label="Réagir {{ $e }}">{{ $e }}</button>
                    @endforeach
                    <button type="button" class="w-10 h-10 rounded-xl hover:bg-[color:rgba(14,165,160,0.10)] text-sm font-bold text-[color:var(--chat-muted)]" data-reaction-more aria-label="Plus">＋</button>
                </div>
                <div id="chatReactionsMore" class="hidden mt-2 pt-2 border-t border-[color:var(--chat-border-soft)]">
                    <div class="grid grid-cols-8 gap-1">
                        @foreach(['🎉','🔥','😍','🤩','😎','🤔','😅','😭','👏','✅','❌','💯','💪','✨','🫶','🤝'] as $e)
                            <button type="button" class="w-9 h-9 rounded-xl hover:bg-[color:rgba(14,165,160,0.10)] text-lg" data-reaction-pick="{{ $e }}" aria-label="Réagir {{ $e }}">{{ $e }}</button>
                        @endforeach
                    </div>
                </div>

                <div id="chatReactionsActions" class="mt-2 pt-2 border-t border-[color:var(--chat-border-soft)] space-y-1">
                    <button type="button" id="chatMsgDeleteMe" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-[color:var(--chat-text)] hover:bg-[color:rgba(14,165,160,0.10)]" data-message-action="delete_me">Supprimer pour moi</button>
                    <button type="button" id="chatMsgDeleteAll" class="hidden w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50" data-message-action="delete_all">Supprimer pour tout le monde</button>
                </div>
            </div>
        </div>

        <div id="chatReactionsWhoModal" class="fixed inset-0 z-[90] hidden" aria-hidden="true">
            <div id="chatReactionsWhoBackdrop" class="absolute inset-0 bg-black/40"></div>
            <div class="absolute inset-x-0 bottom-0 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:top-24 sm:bottom-auto w-full sm:w-[420px] bg-[color:var(--chat-surface)] rounded-t-3xl sm:rounded-3xl p-4 shadow-[0_1px_0_rgba(15,23,42,0.03),0_24px_70px_rgba(15,23,42,0.22)] border border-[color:var(--chat-border-soft)]">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-[color:var(--chat-text)]">Réactions</div>
                    <button type="button" id="chatReactionsWhoClose" class="w-9 h-9 rounded-full inline-flex items-center justify-center text-[color:var(--chat-muted)] hover:bg-[color:rgba(14,165,160,0.10)]" aria-label="Fermer" title="Fermer">✕</button>
                </div>
                <div id="chatReactionsWhoBody" class="mt-3 space-y-4"></div>
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

            // --- QuickType (suggestion bar) ---
            const QUICKTYPE_RECENTS_KEY_LEGACY = 'famille:chat:quicktype_recents_v1';
            const QUICKTYPE_MRU_KEY = 'famille:chat:quicktype_mru_v1';
            const QUICKTYPE_PINNED_KEY = 'famille:chat:quicktype_pinned_v1';
            const QUICKTYPE_HIDDEN_KEY = 'famille:chat:quicktype_hidden_v1';
            const QUICKTYPE_MAX_PINNED = 12;
            const QUICKTYPE_MAX_HIDDEN = 200;
            const QUICKTYPE_MAX_MRU = 400;
            const QUICKTYPE_MAX_SUGGESTIONS = 6;
            const QUICKTYPE_DEBOUNCE_MS = 110;
            const QUICKTYPE_MIN_CHARS = 2;
            const QUICKTYPE_DICT_URL = '/dict/fr_words_min.txt';
            const QUICKTYPE_DEBUG = false;

            const quickTypeUi = {
                mobile: {
                    root: document.getElementById('chatQuickType'),
                    list: document.getElementById('chatQuickTypeList'),
                },
                desktop: {
                    root: document.getElementById('chatQuickTypeDesktop'),
                    list: document.getElementById('chatQuickTypeListDesktop'),
                },
            };

            const quickTypeState = {
                activeKey: activeComposerKey,
                composing: false,
                selectedIndex: 0,
                suggestions: [],
                timer: null,
                seq: 0,
            };

            const quickTypeIndex = {
                dictReady: false,
                dictBuckets: new Map(),
                phraseBuckets: new Map(),
                nameBuckets: new Map(),
            };

            const QUICKTYPE_PHRASES = [
                'ça va',
                'comment ça va',
                'ça marche',
                'ça roule',
                'sans souci',
                'de rien',
                'bonne idée',
                'j’arrive',
                'je suis en route',
                'je suis là',
                'on se call',
                'tu peux préciser',
                'je regarde et je te dis',
            ];

            const QUICKTYPE_NAME_CANDIDATES = @json($onlineList->pluck('name')->values());

            function qtLog(event, data) {
                if (!QUICKTYPE_DEBUG) return;
                try {
                    // eslint-disable-next-line no-console
                    console.log('[QuickType]', event, data);
                } catch {}
            }

            const quickTypeMenu = {
                root: document.getElementById('chatQuickTypeMenu'),
                panel: document.getElementById('chatQuickTypeMenuPanel'),
                backdrop: document.getElementById('chatQuickTypeMenuBackdrop'),
                main: document.getElementById('chatQuickTypeMenuMain'),
                hidden: document.getElementById('chatQuickTypeMenuHidden'),
                hiddenList: document.getElementById('chatQuickTypeHiddenList'),
                open: false,
                key: 'mobile',
                index: 0,
                label: '',
                x: 0,
                y: 0,
            };

            const quickTypeToastEl = document.getElementById('chatQuickTypeToast');
            let quickTypeToastTimer = null;

            function showQuickTypeToast(text) {
                if (!quickTypeToastEl) return;
                const label = String(text || '').trim() || 'OK';
                const inner = quickTypeToastEl.querySelector('div');
                if (inner) inner.textContent = label;
                quickTypeToastEl.classList.remove('hidden');
                if (quickTypeToastTimer) {
                    clearTimeout(quickTypeToastTimer);
                    quickTypeToastTimer = null;
                }
                quickTypeToastTimer = setTimeout(() => {
                    quickTypeToastEl.classList.add('hidden');
                }, 1200);
            }

            function loadStringList(key) {
                try {
                    const raw = localStorage.getItem(key);
                    const arr = raw ? JSON.parse(raw) : [];
                    return Array.isArray(arr) ? arr.filter((s) => typeof s === 'string' && s.trim() !== '') : [];
                } catch {
                    return [];
                }
            }

            function saveStringList(key, arr) {
                try {
                    localStorage.setItem(key, JSON.stringify(arr));
                } catch {}
            }

            function loadQuickTypePinned() {
                return loadStringList(QUICKTYPE_PINNED_KEY);
            }

            function loadQuickTypeHidden() {
                return loadStringList(QUICKTYPE_HIDDEN_KEY);
            }

            function unhideSuggestion(text) {
                const v = String(text || '').trim();
                if (!v) return;
                const arr = loadQuickTypeHidden();
                const next = arr.filter((x) => normalizeForMatch(x) !== normalizeForMatch(v));
                saveStringList(QUICKTYPE_HIDDEN_KEY, next);
            }

            function clearHiddenSuggestions() {
                saveStringList(QUICKTYPE_HIDDEN_KEY, []);
            }

            function isHiddenSuggestion(text) {
                const v = normalizeForMatch(text);
                return loadQuickTypeHidden().some((s) => normalizeForMatch(s) === v);
            }

            function isPinnedSuggestion(text) {
                const v = normalizeForMatch(text);
                return loadQuickTypePinned().some((s) => normalizeForMatch(s) === v);
            }

            function pinSuggestion(text) {
                const v = String(text || '').trim();
                if (!v) return;
                if (isHiddenSuggestion(v)) return;
                const arr = loadQuickTypePinned();
                const next = [v, ...arr.filter((x) => normalizeForMatch(x) !== normalizeForMatch(v))].slice(0, QUICKTYPE_MAX_PINNED);
                saveStringList(QUICKTYPE_PINNED_KEY, next);
            }

            function unpinSuggestion(text) {
                const v = String(text || '').trim();
                if (!v) return;
                const arr = loadQuickTypePinned();
                const next = arr.filter((x) => normalizeForMatch(x) !== normalizeForMatch(v));
                saveStringList(QUICKTYPE_PINNED_KEY, next);
            }

            function hideSuggestion(text) {
                const v = String(text || '').trim();
                if (!v) return;
                unpinSuggestion(v);
                const arr = loadQuickTypeHidden();
                const next = [v, ...arr.filter((x) => normalizeForMatch(x) !== normalizeForMatch(v))].slice(0, QUICKTYPE_MAX_HIDDEN);
                saveStringList(QUICKTYPE_HIDDEN_KEY, next);
            }

            function removeRecent(text) {
                const v = String(text || '').trim();
                if (!v) return;
                const arr = loadQuickTypeRecents();
                const next = arr.filter((x) => normalizeForMatch(x) !== normalizeForMatch(v));
                try {
                    localStorage.setItem(QUICKTYPE_MRU_KEY, JSON.stringify(next));
                } catch {}
            }

            function loadQuickTypeRecents() {
                try {
                    const rawNew = localStorage.getItem(QUICKTYPE_MRU_KEY);
                    const rawLegacy = localStorage.getItem(QUICKTYPE_RECENTS_KEY_LEGACY);
                    const arrNew = rawNew ? JSON.parse(rawNew) : [];
                    const arrLegacy = rawLegacy ? JSON.parse(rawLegacy) : [];

                    const merged = [...(Array.isArray(arrNew) ? arrNew : []), ...(Array.isArray(arrLegacy) ? arrLegacy : [])]
                        .filter((s) => typeof s === 'string' && s.trim() !== '');

                    // De-dupe by normalized form, preserving recency.
                    const out = [];
                    const seen = new Set();
                    for (const s of merged) {
                        const k = normalizeForMatch(s);
                        if (!k) continue;
                        if (seen.has(k)) continue;
                        seen.add(k);
                        out.push(String(s).trim());
                        if (out.length >= QUICKTYPE_MAX_MRU) break;
                    }

                    // Best-effort migration.
                    try {
                        localStorage.setItem(QUICKTYPE_MRU_KEY, JSON.stringify(out));
                    } catch {}

                    return out;
                } catch {
                    return [];
                }
            }

            function saveQuickTypeRecent(text) {
                const v = String(text || '').trim();
                if (!v) return;
                if (isHiddenSuggestion(v)) return;
                const arr = loadQuickTypeRecents();
                const next = [v, ...arr.filter((x) => normalizeForMatch(x) !== normalizeForMatch(v))].slice(0, QUICKTYPE_MAX_MRU);
                try {
                    localStorage.setItem(QUICKTYPE_MRU_KEY, JSON.stringify(next));
                } catch {}
            }

            function learnQuickTypeFromMessage(body) {
                const text = String(body || '').trim();
                if (!text) return;

                // Extract words (letters + apostrophes/hyphens). Keep original accents/case.
                const words = [];
                try {
                    const re = /[\p{L}][\p{L}'’\-]*/gu;
                    let m;
                    while ((m = re.exec(text))) {
                        const w = String(m[0] || '').trim();
                        if (w.length < QUICKTYPE_MIN_CHARS) continue;
                        words.push(w);
                    }
                } catch {
                    const rough = text.split(/\s+/g);
                    for (const part of rough) {
                        const w = String(part || '').replace(/^[^A-Za-zÀ-ÿ]+|[^A-Za-zÀ-ÿ]+$/g, '').trim();
                        if (w.length < QUICKTYPE_MIN_CHARS) continue;
                        words.push(w);
                    }
                }

                if (!words.length) return;

                // Most-recently-used: add unique words from the message, from end → start.
                const current = loadQuickTypeRecents();
                const seenMsg = new Set();
                const additions = [];
                for (let i = words.length - 1; i >= 0; i--) {
                    const w = words[i];
                    const k = normalizeForMatch(w);
                    if (!k) continue;
                    if (seenMsg.has(k)) continue;
                    if (isHiddenSuggestion(w)) continue;
                    seenMsg.add(k);
                    additions.push(w);
                }

                if (!additions.length) return;

                const next = [...additions, ...current.filter((x) => !seenMsg.has(normalizeForMatch(x)))]
                    .slice(0, QUICKTYPE_MAX_MRU);
                try {
                    localStorage.setItem(QUICKTYPE_MRU_KEY, JSON.stringify(next));
                } catch {}
            }

            function getLastTokenInfoFromValue(value, pos) {
                const v = String(value || '');
                const p = Math.max(0, Math.min(v.length, Number(pos ?? v.length)));
                const before = v.slice(0, p);
                const m = before.match(/(^|[\s\n])([^\s\n]*)$/u);
                const rawToken = (m ? String(m[2] || '') : '');
                const tokenStart = p - rawToken.length;

                // Strip trailing punctuation from the token range.
                const stripped = rawToken.replace(/[\.,;:!\?…]+$/u, '');
                const tokenEnd = tokenStart + stripped.length;

                return {
                    raw: stripped,
                    norm: normalizeForMatch(stripped),
                    from: tokenStart,
                    to: tokenEnd,
                    pos: p,
                };
            }

            function getLastTokenInfo(textarea) {
                const value = String(textarea?.value || '');
                const pos = Math.max(0, Math.min(value.length, Number(textarea?.selectionStart ?? value.length)));
                return getLastTokenInfoFromValue(value, pos);
            }

            function bucketKeyFor(norm) {
                const n = String(norm || '');
                return n.length >= 2 ? n.slice(0, 2) : '';
            }

            function addToBuckets(map, item) {
                const raw = String(item || '').trim();
                if (!raw) return;
                const n = normalizeForMatch(raw);
                if (!n || n.length < 2) return;
                const key = bucketKeyFor(n);
                if (!key) return;
                if (!map.has(key)) map.set(key, []);
                map.get(key).push(raw);
            }

            function buildBuckets(list) {
                const map = new Map();
                for (const item of (Array.isArray(list) ? list : [])) {
                    addToBuckets(map, item);
                }
                return map;
            }

            async function loadQuickTypeDictionary() {
                try {
                    const res = await fetch(QUICKTYPE_DICT_URL, { credentials: 'same-origin' });
                    if (!res.ok) throw new Error('dict fetch failed');
                    const txt = await res.text();
                    const lines = txt.split(/\r?\n/g);
                    const words = [];
                    const seen = new Set();
                    for (const line of lines) {
                        const w = String(line || '').trim();
                        if (!w || w.startsWith('#')) continue;
                        const k = normalizeForMatch(w);
                        if (!k || k.length < 2) continue;
                        if (seen.has(k)) continue;
                        seen.add(k);
                        words.push(w);
                    }
                    quickTypeIndex.dictBuckets = buildBuckets(words);
                    quickTypeIndex.dictReady = true;
                    qtLog('dict_loaded', { words: words.length, buckets: quickTypeIndex.dictBuckets.size });
                } catch (e) {
                    quickTypeIndex.dictReady = false;
                    qtLog('dict_error', { message: e?.message || String(e) });
                }
            }

            function initQuickTypeSources() {
                quickTypeIndex.phraseBuckets = buildBuckets(QUICKTYPE_PHRASES);
                quickTypeIndex.nameBuckets = buildBuckets(QUICKTYPE_NAME_CANDIDATES);
                loadQuickTypeDictionary();
            }

            function normalizeForMatch(s) {
                let v = String(s || '').toLocaleLowerCase();
                // Accent-insensitive matching for FR (ça/ç/cà → ca)
                try {
                    v = v.normalize('NFD');
                } catch {}
                try {
                    v = v.replace(/\p{Diacritic}+/gu, '');
                } catch {
                    v = v.replace(/[\u0300-\u036f]+/g, '');
                }
                v = v.replace(/œ/g, 'oe').replace(/æ/g, 'ae');
                return v;
            }

            function computeQuickTypeSuggestions(textarea) {
                const info = getLastTokenInfo(textarea);
                const needle = info.norm;
                if (!needle || needle.length < QUICKTYPE_MIN_CHARS) return [];

                const hidden = new Set(loadQuickTypeHidden().map(normalizeForMatch));
                const seen = new Set();
                const out = [];

                function tryPush(candidate) {
                    const s = String(candidate || '').trim();
                    if (!s) return;
                    const k = normalizeForMatch(s);
                    if (!k) return;
                    if (hidden.has(k)) return;
                    if (!k.startsWith(needle)) return;
                    if (seen.has(k)) return;
                    seen.add(k);
                    out.push(s);
                }

                // Priority 0: pinned (still strict startsWith)
                for (const s of loadQuickTypePinned()) {
                    tryPush(s);
                    if (out.length >= QUICKTYPE_MAX_SUGGESTIONS) return out;
                }

                // Priority 1: MRU (learned from sent messages + accepted suggestions)
                for (const s of loadQuickTypeRecents()) {
                    tryPush(s);
                    if (out.length >= QUICKTYPE_MAX_SUGGESTIONS) return out;
                }

                // Priority 2: names
                const bKey = bucketKeyFor(needle);
                const nameBucket = quickTypeIndex.nameBuckets.get(bKey) || [];
                for (const s of nameBucket) {
                    tryPush(s);
                    if (out.length >= QUICKTYPE_MAX_SUGGESTIONS) return out;
                }

                // Priority 3: frequent phrases
                const phraseBucket = quickTypeIndex.phraseBuckets.get(bKey) || [];
                for (const s of phraseBucket) {
                    tryPush(s);
                    if (out.length >= QUICKTYPE_MAX_SUGGESTIONS) return out;
                }

                // Priority 4: dictionary words (bucketed by first 2 letters)
                const dictBucket = quickTypeIndex.dictBuckets.get(bKey) || [];
                for (const s of dictBucket) {
                    tryPush(s);
                    if (out.length >= QUICKTYPE_MAX_SUGGESTIONS) return out;
                }

                return out;
            }

            function renderQuickType(key, suggestions, selectedIndex) {
                const ui = quickTypeUi[key];
                if (!ui?.root || !ui?.list) return;

                const has = Array.isArray(suggestions) && suggestions.length > 0;
                ui.root.classList.toggle('hidden', !has);
                if (!has) {
                    ui.list.innerHTML = '';
                    return;
                }

                const idx = Math.max(0, Math.min(suggestions.length - 1, Number(selectedIndex || 0)));

                ui.list.innerHTML = suggestions
                    .map((label, i) => {
                        const selected = i === idx;
                        const pinned = isPinnedSuggestion(label);
                        const cls = selected
                            ? 'bg-slate-900 text-white border-slate-900'
                            : (pinned ? 'bg-amber-50 text-slate-900 border-amber-200 hover:bg-amber-100' : 'bg-white text-slate-700 border-black/10 hover:bg-[color:rgba(14,165,160,0.10)]');
                        const esc = String(label)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                        return `
                            <button
                                type="button"
                                role="option"
                                aria-selected="${selected ? 'true' : 'false'}"
                                data-qt-index="${i}"
                                class="inline-flex shrink-0 items-center rounded-full border px-3 py-1.5 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-slate-900/20 ${cls}"
                                title="Insérer"
                            >${pinned ? '<span class=\"mr-1\" aria-hidden=\"true\">📌</span>' : ''}${esc}</button>
                        `;
                    })
                    .join('');
            }

            async function copyToClipboard(text) {
                const v = String(text || '');
                if (!v) return;
                try {
                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        await navigator.clipboard.writeText(v);
                        return;
                    }
                } catch {}
                try {
                    const ta = document.createElement('textarea');
                    ta.value = v;
                    ta.setAttribute('readonly', 'true');
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                } catch {}
            }

            function closeQuickTypeMenu() {
                if (!quickTypeMenu.root) return;
                quickTypeMenu.open = false;
                quickTypeMenu.root.classList.add('hidden');
                quickTypeMenu.root.setAttribute('aria-hidden', 'true');
            }

            function showQuickTypeMenuView(view) {
                if (!quickTypeMenu.main || !quickTypeMenu.hidden) return;
                const v = String(view || 'main');
                quickTypeMenu.main.classList.toggle('hidden', v !== 'main');
                quickTypeMenu.hidden.classList.toggle('hidden', v !== 'hidden');
            }

            function renderHiddenSuggestions() {
                if (!quickTypeMenu.hiddenList) return;
                const hidden = loadQuickTypeHidden();
                if (!hidden.length) {
                    quickTypeMenu.hiddenList.innerHTML = '<div class="px-3 py-3 text-sm text-slate-500">Aucune suggestion masquée.</div>';
                    return;
                }
                quickTypeMenu.hiddenList.innerHTML = hidden
                    .slice(0, 200)
                    .map((label) => {
                        const esc = String(label)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/\"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                        return `
                            <div class="flex items-center justify-between gap-2 px-2 py-1">
                                <div class="text-sm text-slate-900 truncate max-w-[12rem]">${esc}</div>
                                <button type="button" data-qt-action="unhide" class="shrink-0 rounded-xl px-2 py-1 text-sm font-semibold text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]">Rétablir</button>
                            </div>
                        `;
                    })
                    .join('');
            }

            function positionQuickTypeMenu(x, y) {
                if (!quickTypeMenu.panel) return;
                const pad = 12;
                const vw = window.innerWidth || 0;
                const vh = window.innerHeight || 0;
                const rect = quickTypeMenu.panel.getBoundingClientRect();
                const w = rect.width || 240;
                const h = rect.height || 260;
                const left = Math.max(pad, Math.min(vw - w - pad, x));
                const top = Math.max(pad, Math.min(vh - h - pad, y));
                quickTypeMenu.panel.style.left = `${left}px`;
                quickTypeMenu.panel.style.top = `${top}px`;
            }

            function openQuickTypeMenu({ key, index, label, x, y }) {
                if (!quickTypeMenu.root || !quickTypeMenu.panel) return;
                quickTypeMenu.open = true;
                quickTypeMenu.key = key;
                quickTypeMenu.index = index;
                quickTypeMenu.label = label;
                quickTypeMenu.x = x;
                quickTypeMenu.y = y;

                showQuickTypeMenuView('main');

                const pinned = isPinnedSuggestion(label);
                const isRecent = loadQuickTypeRecents().some((s) => normalizeForMatch(s) === normalizeForMatch(label));
                const hasHidden = loadQuickTypeHidden().length > 0;

                const pinBtn = quickTypeMenu.panel.querySelector('button[data-qt-action="pin"]');
                const unpinBtn = quickTypeMenu.panel.querySelector('button[data-qt-action="unpin"]');
                const removeRecentBtn = quickTypeMenu.panel.querySelector('button[data-qt-action="remove_recent"]');
                const manageHiddenBtn = quickTypeMenu.panel.querySelector('button[data-qt-action="manage_hidden"]');
                if (pinBtn) pinBtn.classList.toggle('hidden', pinned);
                if (unpinBtn) unpinBtn.classList.toggle('hidden', !pinned);
                if (removeRecentBtn) removeRecentBtn.classList.toggle('hidden', !isRecent);
                if (manageHiddenBtn) manageHiddenBtn.classList.toggle('hidden', !hasHidden);

                quickTypeMenu.root.classList.remove('hidden');
                quickTypeMenu.root.setAttribute('aria-hidden', 'false');
                // Let it render before measuring.
                requestAnimationFrame(() => {
                    positionQuickTypeMenu(x, y);
                });
            }

            if (quickTypeMenu.root && quickTypeMenu.backdrop) {
                quickTypeMenu.backdrop.addEventListener('click', closeQuickTypeMenu);
            }
            window.addEventListener('keydown', (e) => {
                if (!quickTypeMenu.open) return;
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeQuickTypeMenu();
                }
            });

            if (quickTypeMenu.panel) {
                quickTypeMenu.panel.addEventListener('click', async (e) => {
                    const btn = e.target && e.target.closest ? e.target.closest('button[data-qt-action]') : null;
                    if (!btn) return;
                    const action = btn.getAttribute('data-qt-action');
                    const label = quickTypeMenu.label;
                    const c = composer[quickTypeMenu.key] || getActiveComposer();

                    if (action === 'manage_hidden') {
                        renderHiddenSuggestions();
                        showQuickTypeMenuView('hidden');
                        return;
                    }
                    if (action === 'hidden_back') {
                        showQuickTypeMenuView('main');
                        return;
                    }
                    if (action === 'hidden_clear') {
                        clearHiddenSuggestions();
                        renderHiddenSuggestions();
                        scheduleQuickTypeUpdate(quickTypeMenu.key);
                        return;
                    }

                    if (action === 'insert') {
                        applyQuickTypeSuggestion(c?.textarea, label);
                    } else if (action === 'copy') {
                        await copyToClipboard(label);
                        showQuickTypeToast('Copié');
                    } else if (action === 'pin') {
                        pinSuggestion(label);
                    } else if (action === 'unpin') {
                        unpinSuggestion(label);
                    } else if (action === 'hide') {
                        hideSuggestion(label);
                    } else if (action === 'remove_recent') {
                        removeRecent(label);
                    } else if (action === 'unhide') {
                        const row = btn.parentElement;
                        const labelEl = row ? row.querySelector('div') : null;
                        const v = labelEl ? (labelEl.textContent || '') : '';
                        unhideSuggestion(v);
                        renderHiddenSuggestions();
                        scheduleQuickTypeUpdate(quickTypeMenu.key);
                        return;
                    }

                    closeQuickTypeMenu();
                    scheduleQuickTypeUpdate(quickTypeMenu.key);
                    try { c?.textarea?.focus(); } catch {}
                });
            }

            // hidden list actions are handled by the panel click handler

            function applyQuickTypeSuggestion(textarea, suggestion) {
                if (!textarea) return;
                const value = String(textarea.value || '');
                const start = Math.max(0, Math.min(value.length, Number(textarea.selectionStart ?? value.length)));
                const end = Math.max(0, Math.min(value.length, Number(textarea.selectionEnd ?? value.length)));

                let replaceFrom = start;
                let replaceTo = end;

                if (start === end) {
                    const info = getLastTokenInfoFromValue(value, start);
                    replaceFrom = Math.max(0, Math.min(value.length, info.from));
                    replaceTo = Math.max(0, Math.min(value.length, info.to));
                }

                const left = value.slice(0, replaceFrom);
                const right = value.slice(replaceTo);
                let insert = String(suggestion || '').trim();
                if (!insert) return;

                // Autocomplete UX: replace last token and append a trailing space.
                insert = insert.replace(/\s+$/g, '') + ' ';

                const nextValue = left + insert + right;
                textarea.value = nextValue;
                const newPos = (left + insert).length;
                try {
                    textarea.setSelectionRange(newPos, newPos);
                } catch {}

                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                saveQuickTypeRecent(String(suggestion || ''));
            }

            function hideQuickType(key) {
                const ui = quickTypeUi[key];
                if (!ui?.root || !ui?.list) return;
                ui.root.classList.add('hidden');
                ui.list.innerHTML = '';
            }

            function resetQuickType(key) {
                quickTypeState.suggestions = [];
                quickTypeState.selectedIndex = 0;
                hideQuickType(key);
            }

            function scheduleQuickTypeUpdate(forcedKey) {
                if (quickTypeState.timer) {
                    clearTimeout(quickTypeState.timer);
                    quickTypeState.timer = null;
                }

                const c = forcedKey ? composer[forcedKey] : getActiveComposer();
                const key = c?.key || activeComposerKey;
                const textarea = c?.textarea;
                if (!textarea) return;
                if (quickTypeState.composing) return;

                const valueSnapshot = String(textarea.value || '');
                const selSnapshot = Math.max(0, Math.min(valueSnapshot.length, Number(textarea.selectionStart ?? valueSnapshot.length)));
                const tokenSnapshot = getLastTokenInfoFromValue(valueSnapshot, selSnapshot);
                const mySeq = ++quickTypeState.seq;

                // Strict rule: only show when last token is long enough.
                if (!tokenSnapshot.norm || tokenSnapshot.norm.length < QUICKTYPE_MIN_CHARS) {
                    resetQuickType(key);
                    return;
                }

                quickTypeState.timer = setTimeout(() => {
                    const c2 = forcedKey ? composer[forcedKey] : getActiveComposer();
                    const key2 = c2?.key || activeComposerKey;
                    const textarea2 = c2?.textarea;
                    if (!textarea2) return;
                    if (quickTypeState.composing) return;

                    const valueNow = String(textarea2.value || '');
                    const selNow = Math.max(0, Math.min(valueNow.length, Number(textarea2.selectionStart ?? valueNow.length)));
                    if (mySeq !== quickTypeState.seq) return; // newer update queued
                    if (valueNow !== valueSnapshot) return; // stale debounce result
                    if (selNow !== selSnapshot) return; // caret moved

                    const tokenNow = getLastTokenInfoFromValue(valueNow, selNow);
                    if (!tokenNow.norm || tokenNow.norm.length < QUICKTYPE_MIN_CHARS) {
                        resetQuickType(key2);
                        return;
                    }

                    quickTypeState.activeKey = key2;
                    quickTypeState.suggestions = computeQuickTypeSuggestions(textarea2);
                    quickTypeState.selectedIndex = Math.max(0, Math.min(quickTypeState.suggestions.length - 1, quickTypeState.selectedIndex));
                    qtLog('update', {
                        key: key2,
                        seq: mySeq,
                        input: valueNow,
                        token: tokenNow.raw,
                        matches: quickTypeState.suggestions.length,
                        top: quickTypeState.suggestions.slice(0, 5),
                    });
                    renderQuickType(key2, quickTypeState.suggestions, quickTypeState.selectedIndex);
                }, QUICKTYPE_DEBOUNCE_MS);
            }

            function bindQuickTypeForComposer(key) {
                const c = composer[key];
                const ui = quickTypeUi[key];
                if (!c?.textarea || !ui?.root || !ui?.list) return;

                const longPress = {
                    timer: null,
                    active: false,
                    index: -1,
                };

                c.textarea.addEventListener('compositionstart', () => {
                    quickTypeState.composing = true;
                });
                c.textarea.addEventListener('compositionend', () => {
                    quickTypeState.composing = false;
                    scheduleQuickTypeUpdate(key);
                });

                c.textarea.addEventListener('focus', () => {
                    setActiveComposerKey(key);
                    scheduleQuickTypeUpdate(key);
                });
                c.textarea.addEventListener('input', () => {
                    const info = getLastTokenInfo(c.textarea);
                    if (!info.norm || info.norm.length < QUICKTYPE_MIN_CHARS) {
                        if (quickTypeState.timer) {
                            clearTimeout(quickTypeState.timer);
                            quickTypeState.timer = null;
                        }
                        resetQuickType(key);
                        return;
                    }
                    scheduleQuickTypeUpdate(key);
                });

                c.textarea.addEventListener('keydown', (e) => {
                    if (quickTypeState.composing) return;
                    if (key !== activeComposerKey) return;

                    // If input becomes empty/too short (e.g. backspace), reset immediately.
                    if (e.key === 'Backspace' || e.key === 'Delete') {
                        // value here is pre-keypress; schedule a microtask to read the updated value.
                        queueMicrotask(() => {
                            const info = getLastTokenInfo(c.textarea);
                            if (!info.norm || info.norm.length < QUICKTYPE_MIN_CHARS) {
                                if (quickTypeState.timer) {
                                    clearTimeout(quickTypeState.timer);
                                    quickTypeState.timer = null;
                                }
                                resetQuickType(key);
                            }
                        });
                    }

                    const suggestions = quickTypeState.suggestions || [];
                    if (!suggestions.length) return;

                    const isMenuKey = (e.key === 'ContextMenu') || (e.shiftKey && e.key === 'F10');
                    if (isMenuKey) {
                        e.preventDefault();
                        const idx = Math.max(0, Math.min(suggestions.length - 1, quickTypeState.selectedIndex));
                        const label = suggestions[idx];
                        if (!label) return;
                        // Place the menu near the QuickType bar.
                        const anchorBtn = ui.list.querySelector(`button[data-qt-index="${idx}"]`);
                        if (anchorBtn) {
                            const r = anchorBtn.getBoundingClientRect();
                            openQuickTypeMenu({ key, index: idx, label, x: r.left + r.width / 2, y: r.top + r.height });
                        } else {
                            openQuickTypeMenu({ key, index: idx, label, x: (e.clientX || 20), y: (e.clientY || 20) });
                        }
                        return;
                    }

                    if (e.key === 'Tab' && !e.shiftKey && !e.altKey && !e.ctrlKey && !e.metaKey) {
                        e.preventDefault();
                        const s = suggestions[Math.max(0, Math.min(suggestions.length - 1, quickTypeState.selectedIndex))];
                        applyQuickTypeSuggestion(c.textarea, s);
                        scheduleQuickTypeUpdate(key);
                        return;
                    }

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        quickTypeState.selectedIndex = (quickTypeState.selectedIndex + 1) % suggestions.length;
                        renderQuickType(key, suggestions, quickTypeState.selectedIndex);
                        return;
                    }
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        quickTypeState.selectedIndex = (quickTypeState.selectedIndex - 1 + suggestions.length) % suggestions.length;
                        renderQuickType(key, suggestions, quickTypeState.selectedIndex);
                        return;
                    }
                });

                // Allow clicking chips without losing focus.
                ui.root.addEventListener('mousedown', (e) => {
                    if (e.target && e.target.closest && e.target.closest('button[data-qt-index]')) {
                        e.preventDefault();
                    }
                });

                ui.root.addEventListener('contextmenu', (e) => {
                    const btn = e.target && e.target.closest ? e.target.closest('button[data-qt-index]') : null;
                    if (!btn) return;
                    e.preventDefault();
                    const idx = Number(btn.getAttribute('data-qt-index') || 0);
                    const suggestions = quickTypeState.suggestions || [];
                    const label = suggestions[idx];
                    if (!label) return;
                    openQuickTypeMenu({ key, index: idx, label, x: e.clientX, y: e.clientY });
                });

                ui.root.addEventListener('keydown', (e) => {
                    const btn = e.target && e.target.closest ? e.target.closest('button[data-qt-index]') : null;
                    if (!btn) return;
                    const isMenuKey = (e.key === 'ContextMenu') || (e.shiftKey && e.key === 'F10');
                    if (!isMenuKey) return;
                    e.preventDefault();
                    const idx = Number(btn.getAttribute('data-qt-index') || 0);
                    const suggestions = quickTypeState.suggestions || [];
                    const label = suggestions[idx];
                    if (!label) return;
                    const r = btn.getBoundingClientRect();
                    openQuickTypeMenu({ key, index: idx, label, x: r.left + r.width / 2, y: r.top + r.height });
                });

                ui.root.addEventListener('pointerdown', (e) => {
                    const btn = e.target && e.target.closest ? e.target.closest('button[data-qt-index]') : null;
                    if (!btn) return;
                    if (e.pointerType === 'mouse') return; // long-press mainly for touch/pen

                    const idx = Number(btn.getAttribute('data-qt-index') || 0);
                    longPress.active = true;
                    longPress.index = idx;
                    if (longPress.timer) clearTimeout(longPress.timer);
                    longPress.timer = setTimeout(() => {
                        if (!longPress.active) return;
                        const suggestions = quickTypeState.suggestions || [];
                        const label = suggestions[idx];
                        if (!label) return;
                        const r = btn.getBoundingClientRect();
                        openQuickTypeMenu({ key, index: idx, label, x: r.left + r.width / 2, y: r.top + r.height });
                    }, 520);
                });

                function cancelLongPress() {
                    longPress.active = false;
                    longPress.index = -1;
                    if (longPress.timer) {
                        clearTimeout(longPress.timer);
                        longPress.timer = null;
                    }
                }
                ui.root.addEventListener('pointerup', cancelLongPress);
                ui.root.addEventListener('pointercancel', cancelLongPress);
                ui.root.addEventListener('pointermove', cancelLongPress);

                ui.root.addEventListener('click', (e) => {
                    const btn = e.target && e.target.closest ? e.target.closest('button[data-qt-index]') : null;
                    if (!btn) return;
                    const idx = Number(btn.getAttribute('data-qt-index') || 0);
                    const suggestions = quickTypeState.suggestions || [];
                    const s = suggestions[idx];
                    if (!s) return;
                    quickTypeState.selectedIndex = idx;
                    applyQuickTypeSuggestion(c.textarea, s);
                    scheduleQuickTypeUpdate(key);
                    try { c.textarea.focus(); } catch {}
                });

                // Hide after blur (small delay to allow chip click)
                c.textarea.addEventListener('blur', () => {
                    setTimeout(() => {
                        if (document.activeElement === c.textarea) return;
                        hideQuickType(key);
                    }, 120);
                });
            }

            bindQuickTypeForComposer('mobile');
            bindQuickTypeForComposer('desktop');

            initQuickTypeSources();

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
            const initialReactionSummaries = @json($reactionSummaries ?? []);
            const reactionSummaries = new Map();

            function toArraySummary(v) {
                if (!Array.isArray(v)) return [];
                return v
                    .map((r) => ({
                        emoji: String(r?.emoji || '').trim(),
                        count: Number(r?.count || 0),
                        reacted_by_me: Boolean(r?.reacted_by_me),
                    }))
                    .filter((r) => r.emoji && r.count > 0);
            }

            try {
                for (const [k, v] of Object.entries(initialReactionSummaries || {})) {
                    const id = Number(k);
                    if (!Number.isNaN(id) && id > 0) {
                        reactionSummaries.set(id, toArraySummary(v));
                    }
                }
            } catch {}

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

            function avatarUrlFor(u) {
                const raw = u?.avatar_url ?? u?.avatarUrl ?? u?.avatar ?? null;
                const s = String(raw ?? '').trim();
                return s ? s : '';
            }

            function buildAvatarNode({ name, colors, avatarUrl, sizeClass = 'w-9 h-9' }) {
                const wrap = document.createElement('div');
                wrap.className = `${sizeClass} rounded-full overflow-hidden flex items-center justify-center text-xs font-semibold`;

                const url = String(avatarUrl || '').trim();
                if (url) {
                    wrap.classList.add('bg-white');
                    wrap.classList.add('border');
                    wrap.classList.add('border-black/10');
                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = '';
                    img.loading = 'lazy';
                    img.className = 'h-full w-full object-cover';
                    wrap.appendChild(img);
                    return wrap;
                }

                wrap.className += ` ${colors.avatar}`;
                wrap.textContent = initialsFor(name);
                return wrap;
            }

            let pinToBottomUntil = 0;

            function pinToBottom(ms = 1200) {
                const until = Date.now() + Math.max(0, Number(ms || 0));
                pinToBottomUntil = Math.max(pinToBottomUntil, until);
            }

            function isPinnedToBottom() {
                return Date.now() < pinToBottomUntil;
            }

            function getMobileBottomDockHeight() {
                const dock = document.getElementById('mobileBottomDock');
                if (!dock) return 0;
                const rect = dock.getBoundingClientRect();
                return rect && rect.height ? rect.height : (dock.offsetHeight || 0);
            }

            function syncScrollBottomPadding() {
                if (!scrollEl) return;

                let isMobile = true;
                try {
                    isMobile = !(window.matchMedia && window.matchMedia('(min-width: 640px)').matches);
                } catch {}

                const dockH = isMobile ? getMobileBottomDockHeight() : 0;
                if (dockH > 0) {
                    const pad = Math.ceil(dockH + 12);
                    scrollEl.style.paddingBottom = `${pad}px`;
                    scrollEl.style.scrollPaddingBottom = `${pad}px`;
                } else {
                    scrollEl.style.paddingBottom = '';
                    scrollEl.style.scrollPaddingBottom = '';
                }
            }

            function scrollToBottom(opts = {}) {
                const options = (opts && typeof opts === 'object') ? opts : {};
                const force = !!options.force;

                const lastRow = messagesEl?.querySelector('[data-message-row]:last-child');
                if (!lastRow) return;

                if (!force && !isPinnedToBottom() && !isNearBottom()) return;

                syncScrollBottomPadding();

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

            function bindInitialMediaThumbPinning() {
                if (!messagesEl) return;
                const imgs = messagesEl.querySelectorAll('[data-chat-media-open] img');
                imgs.forEach((img) => {
                    if (!(img instanceof HTMLImageElement)) return;
                    if (img.dataset.pinBound === '1') return;
                    img.dataset.pinBound = '1';

                    const card = img.closest('[data-chat-media-card]');
                    const markLoaded = () => {
                        if (card) card.classList.remove('animate-pulse');
                    };
                    if (img.complete) {
                        // Cached images might not fire 'load', but decoding can still complete later.
                        try {
                            if (typeof img.decode === 'function') {
                                img.decode().then(() => {
                                    markLoaded();
                                    pinToBottom(900);
                                    scrollToBottom({ force: true });
                                }).catch(() => {});
                            }
                        } catch {}
                        markLoaded();
                        return;
                    }

                    img.addEventListener('load', () => {
                        // Thumbnails can load after initial scroll, changing layout;
                        // keep the bottom pinned while this happens.
                        markLoaded();
                        pinToBottom(900);
                        scrollToBottom({ force: true });
                    }, { once: true });
                });
            }

            function ensureBottom(ms = 900) {
                pinToBottom(ms);
                syncScrollBottomPadding();
                bindInitialMediaThumbPinning();
                scrollToBottom({ force: true });
                setTimeout(() => scrollToBottom({ force: true }), 120);
                setTimeout(() => scrollToBottom({ force: true }), 360);
                setTimeout(() => scrollToBottom({ force: true }), 800);
                setTimeout(() => scrollToBottom({ force: true }), 1600);
                setTimeout(() => scrollToBottom({ force: true }), 2600);
            }

            // Ensure we land at the bottom on initial load and when navigating back.
            window.addEventListener('load', () => ensureBottom(1200));
            window.addEventListener('pageshow', () => ensureBottom(1200));

            // Prevent the browser from restoring a previous scroll position (mobile/PWA can be inconsistent).
            try {
                if (window.history && 'scrollRestoration' in window.history) {
                    window.history.scrollRestoration = 'manual';
                }
            } catch {}

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) ensureBottom(1200);
            });

            window.addEventListener('resize', () => {
                syncScrollBottomPadding();
                if (isPinnedToBottom()) scrollToBottom({ force: true });
            });
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => {
                    syncScrollBottomPadding();
                    if (isPinnedToBottom()) scrollToBottom({ force: true });
                });
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

            syncScrollBottomPadding();
            ensureBottom(900);
            requestAnimationFrame(() => ensureBottom(1200));
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
                            const avatarUrl = avatarUrlFor(u);

                            const row = document.createElement('div');
                            row.className = 'flex items-center gap-3';

                            const av = buildAvatarNode({ name, colors, avatarUrl, sizeClass: 'w-9 h-9' });

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

            function renderReactionsRow(rowEl, summary) {
                if (!rowEl) return;
                const el = rowEl.querySelector('[data-reactions-row]');
                if (!el) return;

                const items = toArraySummary(summary);
                el.innerHTML = '';
                if (items.length === 0) return;

                for (const r of items) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.dataset.reactionChip = '1';
                    btn.dataset.emoji = r.emoji;
                    btn.dataset.messageId = rowEl.dataset.messageId || '';
                    btn.className = 'inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-semibold shadow-sm ' + (r.reacted_by_me ? 'border-teal-300 bg-teal-50 text-teal-800' : 'border-black/10 bg-amber-50/60 text-slate-700');

                    const emo = document.createElement('span');
                    emo.className = 'text-sm leading-none';
                    emo.textContent = r.emoji;
                    const count = document.createElement('span');
                    count.className = 'text-[11px] leading-none';
                    count.textContent = String(r.count);
                    btn.appendChild(emo);
                    btn.appendChild(count);
                    el.appendChild(btn);
                }
            }

            function updateReactionSummary(messageId, summary) {
                const id = Number(messageId || 0);
                if (!id) return;
                const normalized = toArraySummary(summary);
                reactionSummaries.set(id, normalized);
                const row = messagesEl?.querySelector(`[data-message-id="${id}"]`);
                if (row) {
                    row.dataset.reactionSummary = JSON.stringify(normalized);
                    renderReactionsRow(row, normalized);
                }
            }

            function optimisticToggle(summary, emoji) {
                const e = String(emoji || '').trim();
                if (!e) return summary;
                const items = toArraySummary(summary);
                const idx = items.findIndex((r) => r.emoji === e);
                if (idx >= 0) {
                    const r = items[idx];
                    if (r.reacted_by_me) {
                        r.count = Math.max(0, (Number(r.count) || 0) - 1);
                        r.reacted_by_me = false;
                        if (r.count <= 0) items.splice(idx, 1);
                    } else {
                        r.count = (Number(r.count) || 0) + 1;
                        r.reacted_by_me = true;
                    }
                } else {
                    items.push({ emoji: e, count: 1, reacted_by_me: true });
                }
                return items;
            }

            const reactionsPicker = {
                root: document.getElementById('chatReactionsPicker'),
                backdrop: document.getElementById('chatReactionsPickerBackdrop'),
                panel: document.getElementById('chatReactionsPickerPanel'),
                more: document.getElementById('chatReactionsMore'),
                deleteMeBtn: document.getElementById('chatMsgDeleteMe'),
                deleteAllBtn: document.getElementById('chatMsgDeleteAll'),
                open: false,
                messageId: 0,
            };
            function openReactionsPicker(messageId, x, y) {
                const id = Number(messageId || 0);
                if (!reactionsPicker.root || !reactionsPicker.panel || !id) return;

                const row = messagesEl?.querySelector(`[data-message-row][data-message-id="${id}"]`);
                if (row?.dataset?.deleted === '1') {
                    return;
                }

                // Anchor to the bubble, but keep the press point when possible (clamped inside the bubble).
                let anchorClientX = Number(x || 0);
                let anchorClientY = Number(y || 0);
                let bubbleClientTop = null;
                let bubbleClientBottom = null;

                try {
                    const bubble = row?.querySelector('[data-bubble]');
                    const br = bubble?.getBoundingClientRect?.();
                    if (br && Number.isFinite(br.left) && Number.isFinite(br.top) && br.width > 0 && br.height > 0) {
                        bubbleClientTop = br.top;
                        bubbleClientBottom = br.bottom;

                        const fallbackX = br.left + br.width / 2;
                        const fallbackY = br.top + br.height / 2;

                        const fuzz = 48;
                        const inX = Number.isFinite(anchorClientX) && anchorClientX >= (br.left - fuzz) && anchorClientX <= (br.right + fuzz);
                        const inY = Number.isFinite(anchorClientY) && anchorClientY >= (br.top - fuzz) && anchorClientY <= (br.bottom + fuzz);

                        const inset = 12;
                        const minX = br.left + inset;
                        const maxX = br.right - inset;
                        const minY = br.top + inset;
                        const maxY = br.bottom - inset;

                        anchorClientX = inX ? Math.max(minX, Math.min(anchorClientX, maxX)) : fallbackX;
                        anchorClientY = inY ? Math.max(minY, Math.min(anchorClientY, maxY)) : fallbackY;
                    }
                } catch {}

                reactionsPicker.messageId = id;
                reactionsPicker.open = true;
                reactionsPicker.more?.classList.add('hidden');
                const isOwner = row && currentUserId && Number(row.dataset.userId || 0) === Number(currentUserId);
                reactionsPicker.deleteAllBtn?.classList.toggle('hidden', !isOwner);
                reactionsPicker.root.classList.remove('hidden');

                const pad = 10;
                const offset = 12;
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;

                // Panel is position:fixed, so we position directly in client (viewport) coords.
                const anchorX = anchorClientX;
                const anchorY = anchorClientY;

                reactionsPicker.panel.style.left = '0px';
                reactionsPicker.panel.style.top = '0px';
                reactionsPicker.panel.style.right = '';
                reactionsPicker.panel.style.bottom = '';
                const rect = reactionsPicker.panel.getBoundingClientRect();

                // Mobile fallback: show as bottom sheet to avoid iOS long-press coordinate quirks.
                const isCoarse = (() => {
                    try {
                        if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return true;
                    } catch {}
                    return Number(navigator.maxTouchPoints || 0) > 0;
                })();
                if (isCoarse) {
                    reactionsPicker.panel.style.left = '12px';
                    reactionsPicker.panel.style.right = '12px';
                    reactionsPicker.panel.style.top = 'auto';
                    reactionsPicker.panel.style.bottom = 'calc(var(--mobile-bottom-nav-h,4rem) + env(safe-area-inset-bottom) + 12px)';
                    return;
                }

                const minLeft = pad;
                const maxLeft = viewportWidth - rect.width - pad;
                const left = Math.max(minLeft, Math.min(anchorX - rect.width / 2, maxLeft));

                const minTop = pad;
                const maxTop = viewportHeight - rect.height - pad;

                let top = 0;
                if (bubbleClientTop != null && bubbleClientBottom != null) {
                    const aboveTop = Number(bubbleClientTop) - rect.height - offset;
                    const belowTop = Number(bubbleClientBottom) + offset;
                    const fitsAbove = aboveTop >= minTop;
                    const fitsBelow = belowTop <= maxTop;
                    top = fitsBelow ? belowTop : (fitsAbove ? aboveTop : Math.max(minTop, Math.min(anchorY - rect.height / 2, maxTop)));
                } else {
                    const aboveTop = anchorY - rect.height - offset;
                    const belowTop = anchorY + offset;
                    const fitsAbove = aboveTop >= minTop;
                    const fitsBelow = belowTop <= maxTop;
                    top = fitsBelow ? belowTop : (fitsAbove ? aboveTop : Math.max(minTop, Math.min(anchorY - rect.height / 2, maxTop)));
                }

                reactionsPicker.panel.style.left = `${left}px`;
                reactionsPicker.panel.style.top = `${top}px`;
            }


                reactionsPicker.messageId = id;
                reactionsPicker.open = true;
                reactionsPicker.more?.classList.add('hidden');
                const isOwner = row && currentUserId && Number(row.dataset.userId || 0) === Number(currentUserId);
                reactionsPicker.deleteAllBtn?.classList.toggle('hidden', !isOwner);
                reactionsPicker.root.classList.remove('hidden');
                const pad = 10;
                const offset = 12;

                // Use the overlay root as the coordinate space (more reliable on iOS).
                const rootRect = reactionsPicker.root.getBoundingClientRect();
                const viewportWidth = rootRect?.width || window.innerWidth;
                const viewportHeight = rootRect?.height || window.innerHeight;
                const originLeft = rootRect?.left || 0;
                const originTop = rootRect?.top || 0;

                // x/y are client coords (viewport-relative) => convert to root-local coords.
                const anchorX = Number(x || 0) - originLeft;
                const anchorY = Number(y || 0) - originTop;

                reactionsPicker.panel.style.left = '0px';
                reactionsPicker.panel.style.top = '0px';
                const rect = reactionsPicker.panel.getBoundingClientRect();

                const left = Math.max(pad, Math.min(anchorX - rect.width / 2, viewportWidth - rect.width - pad));

                const above = anchorY - rect.height - offset;
                const below = anchorY + offset;
                const fitsAbove = above >= pad;
                const fitsBelow = below <= (viewportHeight - rect.height - pad);

                let top = above;
                if (!fitsAbove && fitsBelow) {
                    top = below;
                }
                if (!fitsAbove && !fitsBelow) {
                    top = Math.max(pad, Math.min(anchorY - rect.height / 2, viewportHeight - rect.height - pad));
                }

                reactionsPicker.panel.style.left = `${left}px`;
                reactionsPicker.panel.style.top = `${top}px`;
            }

            function closeReactionsPicker() {
                reactionsPicker.open = false;
                reactionsPicker.messageId = 0;
                reactionsPicker.root?.classList.add('hidden');
            }

            reactionsPicker.backdrop?.addEventListener('click', closeReactionsPicker);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeReactionsPicker();
            });

            async function postToggleReaction(messageId, emoji) {
                const id = Number(messageId || 0);
                const e = String(emoji || '').trim();
                if (!id || !e) return null;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const url = `/chat/messages/${id}/reactions`;

                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: JSON.stringify({ emoji: e }),
                    credentials: 'same-origin',
                });

                if (!resp.ok) {
                    const txt = await resp.text().catch(() => '');
                    throw new Error(`toggle failed ${resp.status} ${txt}`);
                }

                return await resp.json();
            }

            reactionsPicker.panel?.addEventListener('click', async (e) => {
                const actionBtn = e.target?.closest('[data-message-action]');
                const action = actionBtn?.getAttribute('data-message-action') || '';
                if (action) {
                    const mid = reactionsPicker.messageId;
                    if (!mid) return;

                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const headers = {
                        'Accept': 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    };

                    if (action === 'delete_me') {
                        const ok = confirm('Supprimer ce message pour vous ?');
                        if (!ok) return;
                        closeReactionsPicker();
                        try {
                            const resp = await fetch(`/chat/messages/${mid}/me`, { method: 'DELETE', headers, credentials: 'same-origin' });
                            if (resp.ok) {
                                const row = messagesEl?.querySelector(`[data-message-id="${mid}"]`);
                                if (row) row.style.display = 'none';
                            }
                        } catch {}
                        return;
                    }

                    if (action === 'delete_all') {
                        const ok = confirm('Supprimer ce message pour tout le monde ?');
                        if (!ok) return;
                        closeReactionsPicker();
                        try {
                            const resp = await fetch(`/chat/messages/${mid}`, { method: 'DELETE', headers, credentials: 'same-origin' });
                            if (resp.ok) {
                                markMessageDeletedForAll(mid);
                            }
                        } catch {}
                        return;
                    }

                    return;
                }

                const btn = e.target?.closest('[data-reaction-pick], [data-reaction-more]');
                if (!btn) return;
                if (btn.hasAttribute('data-reaction-more')) {
                    reactionsPicker.more?.classList.toggle('hidden');
                    return;
                }

                const emoji = btn.getAttribute('data-reaction-pick') || '';
                const mid = reactionsPicker.messageId;
                if (!mid || !emoji) return;

                const prev = reactionSummaries.get(mid) || [];
                const optimistic = optimisticToggle(prev, emoji);
                updateReactionSummary(mid, optimistic);
                closeReactionsPicker();

                try {
                    const json = await postToggleReaction(mid, emoji);
                    if (json?.reaction_summary) updateReactionSummary(mid, json.reaction_summary);
                } catch {
                    updateReactionSummary(mid, prev);
                }
            });

            async function openWhoReacted(messageId) {
                const id = Number(messageId || 0);
                if (!id) return;
                const modal = document.getElementById('chatReactionsWhoModal');
                const body = document.getElementById('chatReactionsWhoBody');
                if (!modal || !body) return;

                body.innerHTML = '<div class="text-sm text-slate-600">Chargement…</div>';
                modal.classList.remove('hidden');

                const url = `/chat/messages/${id}/reactions`;
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!resp.ok) {
                    body.innerHTML = '<div class="text-sm text-red-600">Impossible de charger</div>';
                    return;
                }
                const json = await resp.json();
                const groups = json?.emoji_groups || {};

                body.innerHTML = '';
                const entries = Object.entries(groups);
                if (entries.length === 0) {
                    body.innerHTML = '<div class="text-sm text-slate-600">Aucune réaction</div>';
                    return;
                }

                for (const [emoji, users] of entries) {
                    const section = document.createElement('div');
                    const title = document.createElement('div');
                    title.className = 'text-sm font-semibold text-gray-900';
                    title.textContent = `${emoji} ${Array.isArray(users) ? users.length : 0}`;
                    section.appendChild(title);

                    const list = document.createElement('div');
                    list.className = 'mt-2 space-y-2';
                    for (const u of (Array.isArray(users) ? users : [])) {
                        const row = document.createElement('div');
                        row.className = 'flex items-center gap-2';

                        const avatar = document.createElement('div');
                        avatar.className = 'w-8 h-8 rounded-full overflow-hidden bg-slate-100 border border-black/10';
                        const url = String(u?.avatar_url || '');
                        if (url) {
                            const img = document.createElement('img');
                            img.src = url;
                            img.alt = '';
                            img.className = 'w-full h-full object-cover';
                            img.loading = 'lazy';
                            avatar.appendChild(img);
                        }

                        const name = document.createElement('div');
                        name.className = 'text-sm text-slate-900 font-semibold';
                        name.textContent = String(u?.name || '—');

                        row.appendChild(avatar);
                        row.appendChild(name);
                        list.appendChild(row);
                    }

                    section.appendChild(list);
                    body.appendChild(section);
                }
            }

            document.getElementById('chatReactionsWhoClose')?.addEventListener('click', () => {
                document.getElementById('chatReactionsWhoModal')?.classList.add('hidden');
            });
            document.getElementById('chatReactionsWhoBackdrop')?.addEventListener('click', () => {
                document.getElementById('chatReactionsWhoModal')?.classList.add('hidden');
            });

            // Bubble triggers: right-click (desktop) + long-press (mobile)
            let suppressContextMenuUntil = 0;
            const isCoarsePointer = () => {
                try {
                    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return true;
                } catch {}
                return Number(navigator.maxTouchPoints || 0) > 0;
            };

            messagesEl?.addEventListener('contextmenu', (e) => {
                const bubble = e.target?.closest('[data-bubble]');
                if (!bubble) return;
                e.preventDefault();

                if (Date.now() < suppressContextMenuUntil) {
                    return;
                }

                const row = bubble.closest('[data-message-row]');
                const mid = row?.dataset?.messageId;
                const rect = bubble.getBoundingClientRect();
                const ax = rect.left + rect.width / 2;
                const ay = rect.top + rect.height / 2;
                const useRect = isCoarsePointer();
                const x = useRect ? ax : (Number(e.clientX || 0) || ax);
                const y = useRect ? ay : (Number(e.clientY || 0) || ay);
                openReactionsPicker(mid, x, y);
            });

            let longPressTimer = null;
            let longPressStart = null;

            messagesEl?.addEventListener('pointerdown', (e) => {
                if (e.pointerType !== 'touch') return;
                const bubble = e.target?.closest('[data-bubble]');
                if (!bubble) return;
                longPressStart = { x: e.clientX, y: e.clientY };
                if (longPressTimer) clearTimeout(longPressTimer);
                longPressTimer = setTimeout(() => {
                    const row = bubble.closest('[data-message-row]');
                    const mid = row?.dataset?.messageId;
                    const rect = bubble.getBoundingClientRect();
                    suppressContextMenuUntil = Date.now() + 1200;
                    openReactionsPicker(mid, longPressStart?.x ?? (rect.left + rect.width / 2), longPressStart?.y ?? (rect.top + rect.height / 2));
                }, 480);
            });

            messagesEl?.addEventListener('pointermove', (e) => {
                if (!longPressStart || !longPressTimer) return;
                const dx = Math.abs(e.clientX - longPressStart.x);
                const dy = Math.abs(e.clientY - longPressStart.y);
                if (dx > 10 || dy > 10) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                }
            });

            const cancelLongPress = () => {
                if (longPressTimer) clearTimeout(longPressTimer);
                longPressTimer = null;
                longPressStart = null;
            };

            messagesEl?.addEventListener('pointerup', cancelLongPress);
            messagesEl?.addEventListener('pointercancel', cancelLongPress);

            // Clicking reaction chips => open who reacted list
            messagesEl?.addEventListener('click', (e) => {
                const trigger = e.target?.closest('[data-reaction-trigger]');
                if (trigger) {
                    e.preventDefault();
                    e.stopPropagation();
                    const mid = trigger.getAttribute('data-message-id') || trigger.closest('[data-message-row]')?.dataset?.messageId;
                    const rect = trigger.getBoundingClientRect();
                    openReactionsPicker(mid, longPressStart?.x ?? (rect.left + rect.width / 2), longPressStart?.y ?? (rect.top + rect.height / 2));
                    return;
                }
                const chip = e.target?.closest('[data-reaction-chip]');
                if (!chip) return;
                const mid = chip.getAttribute('data-message-id') || chip.closest('[data-message-row]')?.dataset?.messageId;
                openWhoReacted(mid);
            });

            // Render existing DOM reaction summaries (server-rendered)
            try {
                messagesEl?.querySelectorAll('[data-message-row]').forEach((row) => {
                    const mid = Number(row?.dataset?.messageId || 0);
                    let rs = [];
                    try {
                        rs = JSON.parse(row?.dataset?.reactionSummary || '[]');
                    } catch {
                        rs = reactionSummaries.get(mid) || [];
                    }
                    if (mid) reactionSummaries.set(mid, toArraySummary(rs));
                    renderReactionsRow(row, reactionSummaries.get(mid) || []);
                });
            } catch {}

            function appendDaySeparator(dayKey, label) {
                if (!messagesEl || !dayKey || dayKey === lastDayKey) return;

                const sep = document.createElement('div');
                sep.className = 'py-2 flex justify-center';
                sep.dataset.daySeparator = '1';
                sep.dataset.dayKey = dayKey;

                const pill = document.createElement('div');
                pill.className = 'text-xs text-slate-500 bg-white border border-black/10 rounded-full px-3 py-1';
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
                const avatarUrl = avatarUrlFor(payload?.user || payload || {});
                const isDeleted = !!payload?.is_deleted || !!payload?.deleted_for_all || !!payload?.deleted_for_all_at;
                const body = isDeleted ? '' : (payload?.body ?? '');
                const att = isDeleted ? null : parseAttachmentBody(body);
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
                outer.className = `flex ${isMe ? 'justify-end' : 'justify-start'} group`;
                outer.dataset.messageRow = '1';
                outer.dataset.userId = uid != null ? String(uid) : '';
                outer.dataset.dayKey = dk;
                outer.dataset.deleted = isDeleted ? '1' : '0';
                if (id != null) outer.dataset.messageId = String(id);

                const initialRs = isDeleted ? [] : toArraySummary(payload?.reaction_summary || []);
                outer.dataset.reactionSummary = JSON.stringify(initialRs);
                if (id != null) {
                    reactionSummaries.set(Number(id), initialRs);
                }

                const width = document.createElement('div');
                width.className = att
                    ? 'w-[clamp(240px,72vw,420px)] max-w-[92vw] sm:w-[clamp(320px,48vw,520px)] sm:max-w-[520px]'
                    : 'max-w-[72%] sm:max-w-[68%]';

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
                const avatarNode = buildAvatarNode({ name, colors, avatarUrl, sizeClass: 'w-8 h-8 sm:w-9 sm:h-9' });
                avatarWrap.appendChild(avatarNode);

                const wrapper = document.createElement('div');
                if (att) {
                    wrapper.className = 'relative p-0 border-0 bg-transparent';
                } else {
                    wrapper.className = `relative px-4 py-3 border ${isMe ? 'bg-slate-900 text-white border-slate-900 rounded-2xl rounded-br-md' : 'bg-white text-gray-900 border-slate-200 rounded-2xl rounded-bl-md'}`;
                }
                wrapper.dataset.bubble = '1';

                if (!isDeleted) {
                    // Desktop discoverability: hover button 🙂
                    const reactBtn = document.createElement('button');
                    reactBtn.type = 'button';
                    reactBtn.className = `hidden sm:inline-flex absolute -top-3 ${isMe ? '-left-3' : '-right-3'} w-8 h-8 items-center justify-center rounded-full border border-black/10 bg-white text-slate-700 shadow-sm opacity-0 group-hover:opacity-100 transition-opacity`;
                    reactBtn.dataset.reactionTrigger = '1';
                    if (id != null) reactBtn.dataset.messageId = String(id);
                    reactBtn.setAttribute('aria-label', 'Réagir');
                    reactBtn.title = 'Réagir';
                    reactBtn.textContent = '🙂';
                    wrapper.appendChild(reactBtn);
                }
                const bodyEl = document.createElement('div');

                if (isDeleted) {
                    bodyEl.className = `text-sm italic ${isMe ? 'text-white/80' : 'text-slate-500'}`;
                    bodyEl.textContent = 'Message supprimé';
                } else if (att) {
                    bodyEl.className = 'text-sm';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'group block w-full text-left rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/10 focus-visible:ring-offset-2 focus-visible:ring-offset-white';
                    btn.style.webkitTapHighlightColor = 'transparent';
                    btn.dataset.chatMediaOpen = '1';
                    btn.dataset.url = String(att.url || '#');
                    btn.dataset.openUrl = String(att.open_url || '');
                    btn.dataset.type = String(att.media_type || '');

                    const rawW = Number(att.width || 0);
                    const rawH = Number(att.height || 0);
                    const isLandscape = rawW > 0 && rawH > 0 ? rawW > rawH : false;
                    const isVideo = String(att.media_type) === 'video';
                    const mediaH = isVideo
                        ? (isLandscape ? 'h-[clamp(10rem,30vh,36vh)]' : 'h-[clamp(14rem,46vh,52vh)]')
                        : (isLandscape ? 'h-[clamp(10rem,28vh,36vh)]' : 'h-[clamp(14rem,40vh,52vh)]');

                    const card = document.createElement('div');
                    card.className = 'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm';

                    const thumb = String(att.thumb_url || '');
                    const nameLabel = String(att.name || (att.media_type === 'video' ? 'Vidéo' : 'Photo'));
                    btn.dataset.name = nameLabel;
                    btn.dataset.thumb = thumb;

                    const mediaBox = document.createElement('div');
                    mediaBox.className = `relative w-full ${mediaH} bg-slate-100 animate-pulse`;
                    mediaBox.dataset.chatMediaCard = '1';

                    if (thumb) {
                        const img = document.createElement('img');
                        img.src = thumb;
                        img.alt = nameLabel;
                        img.loading = 'lazy';
                        img.className = 'block w-full h-full object-cover';
                        img.dataset.chatMediaThumb = '1';
                        // When the image loads, the bubble height changes; if we're at the bottom,
                        // keep it pinned so the new upload looks "properly placed".
                        img.addEventListener('load', () => {
                            mediaBox.classList.remove('animate-pulse');
                            if (isPinnedToBottom() || isNearBottom()) scrollToBottom({ force: true });
                        }, { once: true });
                        mediaBox.appendChild(img);
                    } else {
                        const ph = document.createElement('div');
                        ph.className = `w-full h-full flex items-center justify-center text-xs ${isMe ? 'text-white/80' : 'text-slate-500'}`;
                        ph.textContent = nameLabel;
                        mediaBox.appendChild(ph);
                    }

                    const expand = document.createElement('div');
                    expand.className = 'absolute top-2 right-2 pointer-events-none opacity-60 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity';
                    expand.innerHTML = '<div class="w-9 h-9 rounded-full bg-black/35 backdrop-blur flex items-center justify-center text-white"><i class="ph ph-arrows-out" aria-hidden="true"></i></div>';
                    mediaBox.appendChild(expand);

                    if (String(att.media_type) === 'video') {
                        const overlay = document.createElement('div');
                        overlay.className = 'absolute inset-0 flex items-center justify-center pointer-events-none';
                        const pill = document.createElement('div');
                        pill.className = 'w-11 h-11 rounded-full bg-black/35 backdrop-blur-sm flex items-center justify-center text-white text-lg';
                        pill.textContent = '▶';
                        overlay.appendChild(pill);
                        mediaBox.appendChild(overlay);
                    }

                    card.appendChild(mediaBox);

                    const caption = String(att.caption || att.text || '').trim();
                    if (caption) {
                        const cap = document.createElement('div');
                        cap.className = 'px-4 py-3 text-sm text-slate-600 bg-white';
                        cap.textContent = caption;
                        card.appendChild(cap);
                    }

                    btn.appendChild(card);
                    bodyEl.appendChild(btn);
                } else {
                    const link = parseLinkCardBody(body);
                    if (link) {
                        const card = document.createElement('div');
                        card.className = `rounded-xl border border-black/10 ${isMe ? 'bg-white/10' : 'bg-white'} p-3`;

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
                        copy.className = 'inline-flex items-center justify-center rounded-full border border-black/10 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700';
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

                const reactionsRow = document.createElement('div');
                reactionsRow.className = `mt-1 flex flex-wrap gap-1.5 ${isMe ? 'justify-end' : 'justify-start'}`;
                reactionsRow.dataset.reactionsRow = '1';
                width.appendChild(reactionsRow);

                outer.appendChild(width);
                messagesEl.appendChild(outer);

                renderReactionsRow(outer, reactionSummaries.get(Number(id)) || []);

                if (wasAtBottom || isPinnedToBottom()) {
                    scrollToBottom({ force: true });
                }
                syncScrollToBottomButton();
                return true;
            }

            function markMessageDeletedForAll(messageId) {
                const id = Number(messageId || 0);
                if (!id || !messagesEl) return;
                const row = messagesEl.querySelector(`[data-message-row][data-message-id="${id}"]`);
                if (!row) return;
                row.dataset.deleted = '1';
                reactionSummaries.set(id, []);

                const isMe = currentUserId && Number(row.dataset.userId || 0) === Number(currentUserId);
                const bubble = row.querySelector('[data-bubble]');
                if (bubble) {
                    bubble.className = `relative px-4 py-3 border ${isMe ? 'bg-slate-900 text-white border-slate-900 rounded-2xl rounded-br-md' : 'bg-white text-gray-900 border-slate-200 rounded-2xl rounded-bl-md'}`;
                    bubble.innerHTML = '';
                    const txt = document.createElement('div');
                    txt.className = `text-sm italic ${isMe ? 'text-white/80' : 'text-slate-500'}`;
                    txt.textContent = 'Message supprimé';
                    bubble.appendChild(txt);
                }

                const rr = row.querySelector('[data-reactions-row]');
                if (rr) rr.innerHTML = '';
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

            let attachSheetCloseTimer = null;

            function setAttachSheetOpen(open) {
                if (!attachSheet) return;

                const panel = document.getElementById('chatAttachPanel');
                const reduceMotion = (() => {
                    try { return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
                    catch { return false; }
                })();
                const DURATION_MS = 220;

                if (attachSheetCloseTimer) {
                    clearTimeout(attachSheetCloseTimer);
                    attachSheetCloseTimer = null;
                }

                if (open) {
                    attachSheet.classList.remove('hidden');
                    attachSheet.setAttribute('aria-hidden', 'false');

                    // Ensure initial state then animate in.
                    if (attachBackdrop) {
                        attachBackdrop.classList.add('opacity-0');
                        attachBackdrop.classList.remove('opacity-100');
                    }
                    if (panel) {
                        panel.classList.add('opacity-0', 'translate-y-6');
                        panel.classList.remove('opacity-100', 'translate-y-0');
                    }

                    requestAnimationFrame(() => {
                        if (attachBackdrop) {
                            attachBackdrop.classList.remove('opacity-0');
                            attachBackdrop.classList.add('opacity-100');
                        }
                        if (panel) {
                            panel.classList.remove('opacity-0', 'translate-y-6');
                            panel.classList.add('opacity-100', 'translate-y-0');
                        }
                    });

                    refreshQuota().catch(() => {});
                    return;
                }

                attachSheet.setAttribute('aria-hidden', 'true');
                if (attachBackdrop) {
                    attachBackdrop.classList.add('opacity-0');
                    attachBackdrop.classList.remove('opacity-100');
                }
                if (panel) {
                    panel.classList.add('opacity-0', 'translate-y-6');
                    panel.classList.remove('opacity-100', 'translate-y-0');
                }

                if (reduceMotion) {
                    attachSheet.classList.add('hidden');
                } else {
                    attachSheetCloseTimer = setTimeout(() => {
                        attachSheet.classList.add('hidden');
                        attachSheetCloseTimer = null;
                    }, DURATION_MS);
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

            async function postForm(url, formData) {
                const token = getCsrfToken();
                if (!token) throw new Error('missing_csrf');
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: formData,
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

            function waitMedia(el, eventName, timeoutMs) {
                return new Promise((resolve, reject) => {
                    const t = setTimeout(() => {
                        try { el.removeEventListener(eventName, on); } catch {}
                        reject(new Error('timeout:' + eventName));
                    }, timeoutMs);
                    const on = () => {
                        clearTimeout(t);
                        resolve();
                    };
                    el.addEventListener(eventName, on, { once: true });
                });
            }

            async function buildVideoPosterBlob(file) {
                try {
                    if (!file) return null;
                    if (!('URL' in window) || !('createObjectURL' in URL)) return null;

                    const url = URL.createObjectURL(file);
                    try {
                        const video = document.createElement('video');
                        video.preload = 'metadata';
                        video.muted = true;
                        video.playsInline = true;
                        video.src = url;

                        await waitMedia(video, 'loadedmetadata', 8000);

                        const duration = Number(video.duration || 0);
                        const target = (Number.isFinite(duration) && duration > 2) ? 1 : 0;
                        try { video.currentTime = target; } catch (e) { /* ignore */ }
                        try {
                            await waitMedia(video, 'seeked', 8000);
                        } catch {
                            // Some browsers/devices don't fire seeked reliably for blobs.
                            try { await waitMedia(video, 'loadeddata', 8000); } catch {}
                        }

                        const w = video.videoWidth || 0;
                        const h = video.videoHeight || 0;
                        if (!w || !h) return null;

                        const maxW = 640;
                        const scale = Math.min(1, maxW / w);
                        const cw = Math.max(1, Math.round(w * scale));
                        const ch = Math.max(1, Math.round(h * scale));

                        const canvas = document.createElement('canvas');
                        canvas.width = cw;
                        canvas.height = ch;
                        const ctx = canvas.getContext('2d');
                        if (!ctx) return null;
                        ctx.drawImage(video, 0, 0, cw, ch);

                        const blob = await new Promise((resolve) => {
                            canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.75);
                        });

                        const out = blob || null;
                        if (!out) return null;
                        return { blob: out, width: w, height: h };
                    } finally {
                        try { URL.revokeObjectURL(url); } catch (e) {}
                    }
                } catch {
                    return null;
                }
            }

            async function buildImageMeta(file) {
                try {
                    if (!file) return null;
                    if (!('URL' in window) || !('createObjectURL' in URL)) return null;

                    const url = URL.createObjectURL(file);
                    try {
                        const img = new Image();
                        img.decoding = 'async';
                        img.loading = 'eager';
                        img.src = url;

                        await new Promise((resolve, reject) => {
                            const t = setTimeout(() => reject(new Error('timeout:image_meta')), 6000);
                            img.onload = () => {
                                clearTimeout(t);
                                resolve();
                            };
                            img.onerror = () => {
                                clearTimeout(t);
                                reject(new Error('error:image_meta'));
                            };
                        });

                        const w = Number(img.naturalWidth || 0);
                        const h = Number(img.naturalHeight || 0);
                        if (!w || !h) return null;
                        return { width: w, height: h };
                    } finally {
                        try { URL.revokeObjectURL(url); } catch {}
                    }
                } catch {
                    return null;
                }
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
                        let finalized = null;
                        const posterPromise = (kind === 'video') ? buildVideoPosterBlob(file) : Promise.resolve(null);
                        const imageMetaPromise = (kind === 'photo') ? buildImageMeta(file) : Promise.resolve(null);

                        if (size > MULTIPART_THRESHOLD_BYTES) {
                            let init = null;
                            try {
                                init = await postJson(mpInitUrl, {
                                    filename: file.name || 'file',
                                    mime,
                                    size,
                                    kind,
                                    context: 'chat',
                                });
                            } catch (e) {
                                init = null;
                            }

                            if (!init || !init.upload_id || !init.key || !init.part_size || !Array.isArray(init.parts) || init.parts.length === 0) {
                                const presign = await postJson(presignUrl, {
                                    filename: file.name || 'file',
                                    mime,
                                    size,
                                    kind,
                                    context: 'chat',
                                });

                                const uploadUrl = String(presign?.upload_url || '');
                                const key = String(presign?.key || '');
                                const storageDisk = String(presign?.storage_disk || 'r2');
                                if (!uploadUrl || !key) throw new Error('Presign invalide.');

                                await putWithProgress(uploadUrl, file, mime, (loaded, total) => {
                                    const pct = Math.max(0, Math.min(100, Math.round((loaded / (total || size)) * 100)));
                                    updateUploadPlaceholder(tempId, pct);
                                });

                                const finPublicUrl = presign?.public_url || null;
                                if (kind === 'video') {
                                    const fd = new FormData();
                                    fd.append('key', key);
                                    if (finPublicUrl) fd.append('public_url', String(finPublicUrl));
                                    fd.append('mime', mime);
                                    fd.append('size', String(size));
                                    fd.append('kind', kind);
                                    fd.append('context', 'chat');
                                    fd.append('storage_disk', storageDisk);
                                    if (file.name) fd.append('filename', String(file.name));
                                    fd.append('chat_thread_id', 'default');

                                    const posterInfo = await posterPromise;
                                    const posterBlob = posterInfo && typeof posterInfo === 'object' ? posterInfo.blob : null;
                                    if (posterBlob) fd.append('poster_file', posterBlob, 'poster.jpg');

                                    finalized = await postForm(finalizeUrl, fd);
                                } else {
                                    finalized = await postJson(finalizeUrl, {
                                        key,
                                        public_url: finPublicUrl,
                                        mime,
                                        size,
                                        kind,
                                        context: 'chat',
                                        storage_disk: storageDisk,
                                        filename: file.name || null,
                                        chat_thread_id: 'default',
                                    });
                                }
                            } else {

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

                            const finPublicUrl = complete?.public_url || init?.public_url || null;
                            if (kind === 'video') {
                                const fd = new FormData();
                                fd.append('key', key);
                                if (finPublicUrl) fd.append('public_url', String(finPublicUrl));
                                fd.append('mime', mime);
                                fd.append('size', String(size));
                                fd.append('kind', kind);
                                fd.append('context', 'chat');
                                fd.append('storage_disk', 'r2');
                                if (file.name) fd.append('filename', String(file.name));
                                fd.append('chat_thread_id', 'default');

                                const posterInfo = await posterPromise;
                                const posterBlob = posterInfo && typeof posterInfo === 'object' ? posterInfo.blob : null;
                                if (posterBlob) fd.append('poster_file', posterBlob, 'poster.jpg');

                                finalized = await postForm(finalizeUrl, fd);
                            } else {
                                finalized = await postJson(finalizeUrl, {
                                    key,
                                    public_url: finPublicUrl,
                                    mime,
                                    size,
                                    kind,
                                    context: 'chat',
                                    storage_disk: 'r2',
                                    filename: file.name || null,
                                    chat_thread_id: 'default',
                                });
                            }

                            }
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
                            const storageDisk = String(presign?.storage_disk || 'r2');
                            if (!uploadUrl || !key) throw new Error('Presign invalide.');

                            await putWithProgress(uploadUrl, file, mime, (loaded, total) => {
                                const pct = Math.max(0, Math.min(100, Math.round((loaded / (total || size)) * 100)));
                                updateUploadPlaceholder(tempId, pct);
                            });

                            const finPublicUrl = presign?.public_url || null;
                            if (kind === 'video') {
                                const fd = new FormData();
                                fd.append('key', key);
                                if (finPublicUrl) fd.append('public_url', String(finPublicUrl));
                                fd.append('mime', mime);
                                fd.append('size', String(size));
                                fd.append('kind', kind);
                                fd.append('context', 'chat');
                                fd.append('storage_disk', storageDisk);
                                if (file.name) fd.append('filename', String(file.name));
                                fd.append('chat_thread_id', 'default');

                                const posterInfo = await posterPromise;
                                const posterBlob = posterInfo && typeof posterInfo === 'object' ? posterInfo.blob : null;
                                if (posterBlob) fd.append('poster_file', posterBlob, 'poster.jpg');

                                finalized = await postForm(finalizeUrl, fd);
                            } else {
                                finalized = await postJson(finalizeUrl, {
                                    key,
                                    public_url: finPublicUrl,
                                    mime,
                                    size,
                                    kind,
                                    context: 'chat',
                                    storage_disk: storageDisk,
                                    filename: file.name || null,
                                    chat_thread_id: 'default',
                                });
                            }
                        }

                        removeUploadPlaceholder(tempId);
                        refreshQuota().catch(() => {});

                        // Show attachment immediately in the chat (without waiting for realtime/polling).
                        const chatMessageId = Number(finalized?.chat_message_id || 0);
                        const openUrl = String(finalized?.open_url || '');
                        const mediaUrl = String(finalized?.media_url || finalized?.stream_url || '');
                        if (chatMessageId && (mediaUrl || openUrl)) {
                            const posterInfo = await posterPromise;
                            const imageMeta = await imageMetaPromise;
                            const dims = (kind === 'video')
                                ? (posterInfo && typeof posterInfo === 'object' ? { width: Number(posterInfo.width || 0), height: Number(posterInfo.height || 0) } : null)
                                : (imageMeta && typeof imageMeta === 'object' ? { width: Number(imageMeta.width || 0), height: Number(imageMeta.height || 0) } : null);

                            const attachment = {
                                media_type: kind === 'video' ? 'video' : 'image',
                                media_id: Number(finalized?.media_id || 0) || null,
                                name: String((kind === 'video' ? (file.name || 'Vidéo') : (file.name || 'Photo'))),
                                url: mediaUrl || openUrl,
                                open_url: openUrl || undefined,
                                thumb_url: String(finalized?.thumb_url || ''),
                                public_url: String(finalized?.public_url || ''),
                                width: dims && dims.width ? dims.width : undefined,
                                height: dims && dims.height ? dims.height : undefined,
                            };

                            appendMessage({
                                id: chatMessageId,
                                body: '[[ATTACHMENT]]' + JSON.stringify(attachment),
                                created_at: new Date().toISOString(),
                                user: { id: currentUserId, name: currentUserName || 'Vous' },
                            });

                            lastMessageId = Math.max(lastMessageId, chatMessageId);
                            ensureBottom(2000);
                        } else {
                            // Fallback: force a poll so the new message appears quickly.
                            pollOnce().catch(() => {});
                        }
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
                    })
                    .listen('.message.deleted', (e) => {
                        if (!e?.id) return;
                        markMessageDeletedForAll(Number(e.id));
                    })
                    .listen('.message.reactions.updated', (e) => {
                        if (!e?.message_id) return;
                        updateReactionSummary(Number(e.message_id), e.reaction_summary || []);
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
                    // On mobile, the dock height changes as the textarea grows.
                    requestAnimationFrame(() => {
                        syncScrollBottomPadding();
                        if (isPinnedToBottom()) scrollToBottom({ force: true });
                    });
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
                    ensureBottom(1600);

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

                        ensureBottom(1200);

                        // QuickType learning (MRU) on successful send
                        learnQuickTypeFromMessage(body);

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
                    if (isNearBottom()) {
                        pinToBottom(600);
                    } else {
                        pinToBottomUntil = 0;
                    }
                    syncScrollToBottomButton();
                });
            }
            if (scrollToBottomBtn) {
                scrollToBottomBtn.addEventListener('click', () => {
                    ensureBottom(900);
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
                const rawUrl = String(opts?.url || '');
                const rawOpenUrl = String(opts?.openUrl || opts?.open_url || '');
                const thumb = String(opts?.thumb || opts?.thumb_url || '');
                const name = String(opts?.name || (type === 'video' ? 'Vidéo' : 'Photo'));

                const normalize = (() => {
                    const stripTrailingSlash = (u) => u.endsWith('/') ? u.slice(0, -1) : u;

                    const toSameOriginPath = (u) => {
                        try {
                            const x = new URL(u, window.location.origin);
                            if (x.origin !== window.location.origin) return null;
                            return x.pathname + x.search + x.hash;
                        } catch {
                            return null;
                        }
                    };

                    const rewriteVideoShowToStream = (u) => {
                        const p = toSameOriginPath(u);
                        if (!p) return null;
                        const m = p.match(/^\/videos\/(\d+)$/);
                        if (!m) return null;
                        return `/videos/${m[1]}/stream`;
                    };

                    const rewritePhotoViewerToImage = (u) => {
                        const p = toSameOriginPath(u);
                        if (!p) return null;
                        const m = p.match(/^\/media\/photos\/(\d+)$/);
                        if (!m) return null;
                        return `/galerie/${m[1]}`;
                    };

                    const rewriteStreamToShow = (u) => {
                        const p = toSameOriginPath(u);
                        if (!p) return null;
                        const m = p.match(/^\/videos\/(\d+)\/stream$/);
                        if (!m) return null;
                        return `/videos/${m[1]}`;
                    };

                    return { stripTrailingSlash, rewriteVideoShowToStream, rewritePhotoViewerToImage, rewriteStreamToShow };
                })();

                let url = rawUrl;
                let openUrl = rawOpenUrl;

                if (!openUrl && type === 'video') {
                    const show = normalize.rewriteStreamToShow(rawUrl);
                    if (show) openUrl = show;
                }

                if (!openUrl && type !== 'video') {
                    // For legacy image attachments where url was the viewer page, keep it as openUrl.
                    const maybeViewer = normalize.rewritePhotoViewerToImage(rawUrl);
                    if (maybeViewer) openUrl = rawUrl;
                }

                // Backward-compat: some older payloads used a page URL as the media src.
                if (type === 'video') {
                    const stream = normalize.rewriteVideoShowToStream(normalize.stripTrailingSlash(url));
                    if (stream) url = stream;
                } else {
                    const img = normalize.rewritePhotoViewerToImage(normalize.stripTrailingSlash(url));
                    if (img) url = img;
                }

                mediaTitle.textContent = name;
                mediaOpenLink.href = (openUrl || url) || '#';

                if (type === 'video') {
                    mediaImg.classList.add('hidden');
                    mediaVideo.classList.remove('hidden');
                    if (thumb) {
                        mediaVideo.setAttribute('poster', thumb);
                    } else {
                        mediaVideo.removeAttribute('poster');
                    }
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
                    const openUrl = String(el.dataset.openUrl || '');
                    const type = String(el.dataset.type || '');
                    const name = String(el.dataset.name || (type === 'video' ? 'Vidéo' : 'Photo'));
                    const thumb = String(el.dataset.thumb || '');
                    setMediaOpen(true, { url, openUrl, type, name, thumb });
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
    </div>
</x-app-layout>

