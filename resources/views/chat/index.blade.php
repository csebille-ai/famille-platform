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

    <div id="chatBootDiag" style="position:fixed;left:12px;right:12px;bottom:12px;z-index:9999;display:none;background:#111827;color:#fff;padding:10px 12px;border-radius:12px;font:12px/1.4 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;box-shadow:0 12px 30px rgba(0,0,0,.28);pointer-events:none">
        <div style="font-weight:700">Chat</div>
        <div id="chatBootDiagMsg" style="opacity:.92">Initialisation…</div>
    </div>

    <script>
        (function () {
            // Minimal ES5 diagnostics for Safari/PWA WebViews.
            var diag = document.getElementById('chatBootDiag');
            var diagMsg = document.getElementById('chatBootDiagMsg');

            function show(msg) {
                try {
                    if (!diag || !diagMsg) return;
                    diagMsg.textContent = String(msg || 'Erreur');
                    diag.style.display = 'block';
                } catch (e) {}
            }

            window.__chatBootOk = function () {
                try {
                    if (!diag) return;
                    diag.style.display = 'none';
                } catch (e) {}
            };

            window.addEventListener('error', function (ev) {
                try {
                    var msg = (ev && (ev.message || (ev.error && ev.error.message))) || 'Erreur JS';
                    show('Erreur JS: ' + msg);
                } catch (e) {
                    show('Erreur JS');
                }
            });

            window.addEventListener('unhandledrejection', function (ev) {
                try {
                    var r = ev && ev.reason;
                    var msg2 = (r && (r.message || String(r))) || 'Promise rejetée';
                    show('Erreur JS: ' + msg2);
                } catch (e) {
                    show('Erreur JS');
                }
            });
        })();
    </script>

    <script>
        window.__CHAT_BOOTSTRAP__ = {
            currentUserId: @json(auth()->id()),
            currentUserName: @json(auth()->user()?->name),
            pollUrl: @json(route('chat.poll')),
            quotaUrl: @json(url('/api/uploads/quota')),
            presignUrl: @json(url('/api/uploads/presign')),
            mpInitUrl: @json(url('/api/uploads/multipart/init')),
            mpCompleteUrl: @json(url('/api/uploads/multipart/complete')),
            finalizeUrl: @json(url('/api/uploads/finalize')),
            lastMessageId: @json($lastMessageId ?? 0),
            initialOnline: @json($initialOnline ?? []),
            initialReactionSummaries: @json($reactionSummaries ?? []),
            quickTypeNameCandidates: @json($onlineList->pluck('name')->values()),
            maxUploadBytes: @json((int) config('uploads.max_upload_bytes')),
            multipartThresholdBytes: @json((int) config('uploads.multipart_threshold_bytes')),
            dashboardUrl: @json(route('dashboard')),
        };
    </script>

    @vite(['resources/js/chat-page.js'])
    </div>
</x-app-layout>


