<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\ChatPresence;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatReactions;
use App\Services\WebPush\WebPushNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    private function chatViewHelpers(): array
    {
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
            if ($b === '') {
                return null;
            }

            $url = null;
            if (preg_match('/^📹\s*Visio:\s*(https?:\/\/\S+)\s*$/u', $b, $m)) {
                $url = $m[1] ?? null;
            } elseif (preg_match('/^(https?:\/\/\S+)\s*$/u', $b, $m)) {
                $url = $m[1] ?? null;
            }

            $url = $url ? trim((string) $url) : null;
            if (!$url) {
                return null;
            }

            $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
            $domain = $host !== '' ? $host : preg_replace('/^https?:\/\//i', '', $url);

            $title = 'Lien';
            if (str_contains($b, 'Visio') || str_contains((string) $domain, 'jit.si')) {
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
            if (!$userId) {
                return $palette[0];
            }
            return $palette[$userId % count($palette)];
        };

        $initialsFor = function (?string $name) {
            $name = trim((string) $name);
            if ($name === '') {
                return '—';
            }
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
            if ($name === '') {
                return '—';
            }
            $parts = preg_split('/\s+/', $name);
            return $parts[0] ?? $name;
        };

        return compact(
            'parseAttachment',
            'parseLinkCard',
            'palette',
            'paletteFor',
            'initialsFor',
            'firstNameFor',
        );
    }

    private function normalizeOnlineList($initialOnline)
    {
        $onlineList = collect($initialOnline ?? [])
            ->map(function ($u) {
                $id = data_get($u, 'id') ?? data_get($u, 'user_id') ?? data_get($u, 'user.id');
                $name = data_get($u, 'name') ?? data_get($u, 'user.name');
                return ['id' => $id ? (int) $id : null, 'name' => $name ?: '—'];
            })
            ->filter(fn ($u) => !empty($u['id']))
            ->values();

        if (Auth::check()) {
            $onlineList = $onlineList->prepend([
                'id' => (int) Auth::id(),
                'name' => Auth::user()?->name ?? 'Vous',
            ]);
        }

        return $onlineList->unique('id')->values();
    }

    public function publicIndex(Request $request)
    {
        $this->touchPresence();

        $viewerId = (int) (Auth::id() ?? 0);
        $isAdmin = Gate::allows('manage-users');
        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');

        $messages = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
            ->when($hasAudience, function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNull('audience_type')
                        ->orWhere('audience_type', 'all');
                });
            })
            ->when(!$isAdmin && $viewerId > 0 && !$hasAudience, function ($q) {
                // Legacy schema: all messages are public.
            })
            ->when($viewerId > 0, fn ($q) => $q->whereDoesntHave('deletions', fn ($dq) => $dq->where('user_id', $viewerId)))
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $reactionSummaries = app(ChatReactions::class)
            ->summaryForMessageIds($messages->pluck('id')->map(fn ($v) => (int) $v)->all(), Auth::id());

        $initialOnline = $this->onlineUsers();
        $onlineList = $this->normalizeOnlineList($initialOnline);
        $lastMessageId = (int) ($messages->last()?->id ?? 0);

        return view('chat.index_v2', [
            'messages' => $messages,
            'initialOnline' => $initialOnline,
            'onlineList' => $onlineList,
            'lastMessageId' => $lastMessageId,
            'reactionSummaries' => $reactionSummaries,
            'isAdmin' => $isAdmin,
            'conversationWithUserId' => 0,
            'conversationWithUser' => null,
            'chatMode' => 'public',
            'chatTitle' => 'Famille — Public',
            'backUrl' => route('conversations.index'),
            'chatUrl' => route('chat.index'),
            'pollUrl' => route('chat.poll'),
            'storeUrl' => route('chat.store'),
            'dmBaseUrl' => url('/chat/dm'),
            ...$this->chatViewHelpers(),
        ]);
    }

    public function publicPoll(Request $request)
    {
        $this->touchPresence();

        $viewerId = (int) (Auth::id() ?? 0);
        $isAdmin = Gate::allows('manage-users');
        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');

        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);

        $messages = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
            ->when($sinceId > 0, fn ($q) => $q->where('id', '>', $sinceId))
            ->when($hasAudience, function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNull('audience_type')
                        ->orWhere('audience_type', 'all');
                });
            })
            ->when(!$isAdmin && $viewerId > 0 && !$hasAudience, function ($q) {
                // Legacy schema: all messages are public.
            })
            ->when($viewerId > 0, fn ($q) => $q->whereDoesntHave('deletions', fn ($dq) => $dq->where('user_id', $viewerId)))
            ->orderBy('id')
            ->limit(50)
            ->get();

        $reactionSummaries = app(ChatReactions::class)
            ->summaryForMessageIds($messages->pluck('id')->map(fn ($v) => (int) $v)->all(), Auth::id());

        $messages = $messages
            ->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'body' => $m->deleted_for_all_at ? '' : $m->body,
                'is_deleted' => (bool) ($m->deleted_for_all_at !== null),
                'deleted_for_all_at' => $m->deleted_for_all_at?->toISOString(),
                'created_at' => $m->created_at?->toISOString(),
                'audience_type' => 'all',
                'audience_user_ids' => [],
                'audience_users' => [],
                'user' => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'avatar_url' => avatarUrl($m->user),
                ],
                'reaction_summary' => $m->deleted_for_all_at ? [] : ($reactionSummaries[(int) $m->id] ?? []),
            ])
            ->values();

        $onlineUsers = $this->onlineUsers();

        return response()->json([
            'online' => $onlineUsers,
            'messages' => $messages,
            'last_id' => (int) ($messages->last()['id'] ?? $sinceId),
        ]);
    }

    public function publicStore(Request $request)
    {
        $this->touchPresence();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            // Ignore any targeting on the public page.
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['integer', 'min:1'],
        ]);

        $senderId = (int) (Auth::id() ?? 0);
        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');

        $message = ChatMessage::create([
            'user_id' => $senderId,
            'body' => $validated['body'],
            ...($hasAudience ? [
                'audience_type' => 'all',
                'audience_user_ids' => null,
            ] : []),
        ]);

        $message->loadMissing('user:id,name,avatar_path,avatar_updated_at');
        broadcast(new ChatMessageSent($message))->toOthers();

        try {
            $senderName = (string) ($message->user?->name ?? 'Quelqu\'un');

            $payload = [
                'title' => $senderName . ' – Nouveau message',
                'body' => (string) Str::limit((string) $message->body, 140, '…'),
                'url' => route('chat.index'),
            ];

            if ($senderId > 0) {
                app(WebPushNotifier::class)->notifyAllExceptUser($senderId, $payload, [
                    'TTL' => 600,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[chat] webpush notify failed: ' . $e->getMessage());
        }

        if ($request->expectsJson()) {
            $reactionSummary = app(ChatReactions::class)->summaryForMessage((int) $message->id, Auth::id());

            return response()->json([
                'id' => $message->id,
                'body' => $message->deleted_for_all_at ? '' : $message->body,
                'is_deleted' => (bool) ($message->deleted_for_all_at !== null),
                'deleted_for_all_at' => $message->deleted_for_all_at?->toISOString(),
                'created_at' => $message->created_at?->toISOString(),
                'audience_type' => 'all',
                'audience_user_ids' => [],
                'audience_users' => [],
                'user' => [
                    'id' => $message->user?->id,
                    'name' => $message->user?->name,
                    'avatar_url' => avatarUrl($message->user),
                ],
                'reaction_summary' => $message->deleted_for_all_at ? [] : $reactionSummary,
            ]);
        }

        return redirect()->route('chat.index')->with('status', __('Message envoyé.'));
    }

    public function dmIndex(Request $request, User $user)
    {
        $this->touchPresence();

        $viewerId = (int) (Auth::id() ?? 0);
        if ($viewerId <= 0) abort(403);

        $otherId = (int) $user->id;
        if ($otherId <= 0 || $otherId === $viewerId) {
            abort(404);
        }

        $isAdmin = Gate::allows('manage-users');
        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');
        if (!$hasAudience) {
            abort(422, 'Chat privé indisponible sur ce schéma de base de données.');
        }

        $messagesRaw = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
            ->where('audience_type', 'subset')
            ->where(function ($q) use ($viewerId, $otherId) {
                $q->where(function ($p) use ($viewerId, $otherId) {
                    $p->where('user_id', $viewerId)
                        ->whereJsonContains('audience_user_ids', $otherId);
                })->orWhere(function ($p) use ($viewerId, $otherId) {
                    $p->where('user_id', $otherId)
                        ->whereJsonContains('audience_user_ids', $viewerId);
                })->orWhere(function ($p) use ($viewerId, $otherId) {
                    // Fallback: messages from a group where both are recipients.
                    $p->whereJsonContains('audience_user_ids', $viewerId)
                        ->whereJsonContains('audience_user_ids', $otherId);
                });
            })
            ->when(!$isAdmin && $viewerId > 0, function ($q) use ($viewerId) {
                $q->where(function ($qq) use ($viewerId) {
                    $qq->where('user_id', $viewerId)
                        ->orWhereJsonContains('audience_user_ids', $viewerId);
                });
            })
            ->when($viewerId > 0, fn ($q) => $q->whereDoesntHave('deletions', fn ($dq) => $dq->where('user_id', $viewerId)))
            ->latest()
            ->limit(200)
            ->get();

        $messages = $messagesRaw
            ->filter(function (ChatMessage $m) use ($viewerId, $otherId) {
                $audIds = is_array($m->audience_user_ids) ? $m->audience_user_ids : [];
                $participants = array_values(array_unique(array_filter([(int) $m->user_id, ...collect($audIds)->map(fn ($v) => (int) $v)->all()])));
                sort($participants);
                $want = [$viewerId, $otherId];
                sort($want);
                return $participants === $want;
            })
            ->sortBy('id')
            ->take(-50)
            ->values();

        $reactionSummaries = app(ChatReactions::class)
            ->summaryForMessageIds($messages->pluck('id')->map(fn ($v) => (int) $v)->all(), Auth::id());

        $initialOnline = $this->onlineUsers();
        $onlineList = $this->normalizeOnlineList($initialOnline);
        $lastMessageId = (int) ($messages->last()?->id ?? 0);

        return view('chat.index_v2', [
            'messages' => $messages,
            'initialOnline' => $initialOnline,
            'onlineList' => $onlineList,
            'lastMessageId' => $lastMessageId,
            'reactionSummaries' => $reactionSummaries,
            'isAdmin' => $isAdmin,
            'conversationWithUserId' => 0,
            'conversationWithUser' => null,
            'chatMode' => 'dm',
            'chatTitle' => ((string) ($user->name ?? '—')) . ' — Privé',
            'backUrl' => route('conversations.index'),
            'chatUrl' => route('chat.dm', ['user' => $user]),
            'pollUrl' => route('chat.dm.poll', ['user' => $user]),
            'storeUrl' => route('chat.dm.store', ['user' => $user]),
            'dmUserId' => $otherId,
            'dmBaseUrl' => url('/chat/dm'),
            ...$this->chatViewHelpers(),
        ]);
    }

    public function dmPoll(Request $request, User $user)
    {
        $this->touchPresence();

        $viewerId = (int) (Auth::id() ?? 0);
        if ($viewerId <= 0) abort(403);

        $otherId = (int) $user->id;
        if ($otherId <= 0 || $otherId === $viewerId) abort(404);

        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');
        if (!$hasAudience) {
            abort(422);
        }

        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);

        $raw = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
            ->where('audience_type', 'subset')
            ->when($sinceId > 0, fn ($q) => $q->where('id', '>', $sinceId))
            ->where(function ($q) use ($viewerId, $otherId) {
                $q->where(function ($p) use ($viewerId, $otherId) {
                    $p->where('user_id', $viewerId)
                        ->whereJsonContains('audience_user_ids', $otherId);
                })->orWhere(function ($p) use ($viewerId, $otherId) {
                    $p->where('user_id', $otherId)
                        ->whereJsonContains('audience_user_ids', $viewerId);
                })->orWhere(function ($p) use ($viewerId, $otherId) {
                    $p->whereJsonContains('audience_user_ids', $viewerId)
                        ->whereJsonContains('audience_user_ids', $otherId);
                });
            })
            ->when($viewerId > 0, fn ($q) => $q->whereDoesntHave('deletions', fn ($dq) => $dq->where('user_id', $viewerId)))
            ->orderBy('id')
            ->limit(200)
            ->get();

        $messages = $raw
            ->filter(function (ChatMessage $m) use ($viewerId, $otherId) {
                $audIds = is_array($m->audience_user_ids) ? $m->audience_user_ids : [];
                $participants = array_values(array_unique(array_filter([(int) $m->user_id, ...collect($audIds)->map(fn ($v) => (int) $v)->all()])));
                sort($participants);
                $want = [$viewerId, $otherId];
                sort($want);
                return $participants === $want;
            })
            ->values();

        $reactionSummaries = app(ChatReactions::class)
            ->summaryForMessageIds($messages->pluck('id')->map(fn ($v) => (int) $v)->all(), Auth::id());

        $messages = $messages
            ->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'body' => $m->deleted_for_all_at ? '' : $m->body,
                'is_deleted' => (bool) ($m->deleted_for_all_at !== null),
                'deleted_for_all_at' => $m->deleted_for_all_at?->toISOString(),
                'created_at' => $m->created_at?->toISOString(),
                'audience_type' => 'subset',
                'audience_user_ids' => $m->audience_user_ids ?? [],
                'audience_users' => [],
                'user' => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'avatar_url' => avatarUrl($m->user),
                ],
                'reaction_summary' => $m->deleted_for_all_at ? [] : ($reactionSummaries[(int) $m->id] ?? []),
            ])
            ->values();

        $onlineUsers = $this->onlineUsers();

        return response()->json([
            'online' => $onlineUsers,
            'messages' => $messages,
            'last_id' => (int) ($messages->last()['id'] ?? $sinceId),
        ]);
    }

    public function dmStore(Request $request, User $user)
    {
        $this->touchPresence();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $senderId = (int) (Auth::id() ?? 0);
        if ($senderId <= 0) abort(403);

        $otherId = (int) $user->id;
        if ($otherId <= 0 || $otherId === $senderId) abort(404);

        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');
        if (!$hasAudience) {
            abort(422, 'Chat privé indisponible sur ce schéma de base de données.');
        }

        $message = ChatMessage::create([
            'user_id' => $senderId,
            'body' => $validated['body'],
            'audience_type' => 'subset',
            'audience_user_ids' => [$otherId],
        ]);

        $message->loadMissing('user:id,name,avatar_path,avatar_updated_at');
        broadcast(new ChatMessageSent($message))->toOthers();

        try {
            $senderName = (string) ($message->user?->name ?? 'Quelqu\'un');
            $payload = [
                'title' => 'Message privé de ' . $senderName,
                'body' => (string) Str::limit((string) $message->body, 140, '…'),
                'url' => route('chat.dm', ['user' => $senderId]),
            ];

            app(WebPushNotifier::class)->notifyUsers([$otherId], $payload, [
                'TTL' => 600,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[chat] webpush notify failed: ' . $e->getMessage());
        }

        if ($request->expectsJson()) {
            $reactionSummary = app(ChatReactions::class)->summaryForMessage((int) $message->id, Auth::id());

            return response()->json([
                'id' => $message->id,
                'body' => $message->deleted_for_all_at ? '' : $message->body,
                'is_deleted' => (bool) ($message->deleted_for_all_at !== null),
                'deleted_for_all_at' => $message->deleted_for_all_at?->toISOString(),
                'created_at' => $message->created_at?->toISOString(),
                'audience_type' => 'subset',
                'audience_user_ids' => [$otherId],
                'audience_users' => [],
                'user' => [
                    'id' => $message->user?->id,
                    'name' => $message->user?->name,
                    'avatar_url' => avatarUrl($message->user),
                ],
                'reaction_summary' => $message->deleted_for_all_at ? [] : $reactionSummary,
            ]);
        }

        return redirect()->route('chat.dm', ['user' => $user])->with('status', __('Message envoyé.'));
    }

    public function index(Request $request)
    {
        $this->touchPresence();

        $viewerId = (int) (Auth::id() ?? 0);
        $isAdmin = Gate::allows('manage-users');
        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');

        $withUserId = (int) $request->query('with_user_id', 0);
        $withUser = null;
        if ($withUserId > 0) {
            $withUser = User::query()->whereKey($withUserId)->first(['id', 'name', 'avatar_path', 'avatar_updated_at']);
            if (!$withUser) {
                $withUserId = 0;
            }
        }

        $messages = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
            ->when(!$isAdmin && $viewerId > 0 && $hasAudience, function ($q) use ($viewerId) {
                $q->where(function ($qq) use ($viewerId) {
                    $qq->whereNull('audience_type')
                        ->orWhere('audience_type', 'all')
                        ->orWhere('user_id', $viewerId)
                        ->orWhere(function ($q2) use ($viewerId) {
                            $q2->where('audience_type', 'subset')
                                ->whereJsonContains('audience_user_ids', $viewerId);
                        });
                });
            })
            ->when($viewerId > 0 && $withUserId > 0, function ($q) use ($viewerId, $withUserId, $hasAudience) {
                // Conversation view: show only messages between the viewer and the selected user.
                // - Public messages authored by either of them.
                // - Targeted messages where BOTH users participate (sender or recipient).
                $q->where(function ($qq) use ($viewerId, $withUserId, $hasAudience) {
                    $qq->where(function ($pub) use ($viewerId, $withUserId, $hasAudience) {
                        $pub->whereIn('user_id', [$viewerId, $withUserId]);
                        if ($hasAudience) {
                            $pub->where(function ($a) {
                                $a->whereNull('audience_type')
                                    ->orWhere('audience_type', 'all');
                            });
                        }
                    });

                    if ($hasAudience) {
                        $qq->orWhere(function ($sub) use ($viewerId, $withUserId) {
                            $sub->where('audience_type', 'subset')
                                ->where(function ($p) use ($viewerId) {
                                    $p->where('user_id', $viewerId)
                                        ->orWhereJsonContains('audience_user_ids', $viewerId);
                                })
                                ->where(function ($p) use ($withUserId) {
                                    $p->where('user_id', $withUserId)
                                        ->orWhereJsonContains('audience_user_ids', $withUserId);
                                });
                        });
                    }
                });
            })
            ->when($viewerId > 0, fn ($q) => $q->whereDoesntHave('deletions', fn ($dq) => $dq->where('user_id', $viewerId)))
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $reactionSummaries = app(ChatReactions::class)
            ->summaryForMessageIds($messages->pluck('id')->map(fn ($v) => (int) $v)->all(), Auth::id());

        $initialOnline = $this->onlineUsers();
        $onlineList = $this->normalizeOnlineList($initialOnline);
        $lastMessageId = (int) ($messages->last()?->id ?? 0);

        return view('chat.index_v2', [
            'messages' => $messages,
            'initialOnline' => $initialOnline,
            'onlineList' => $onlineList,
            'lastMessageId' => $lastMessageId,
            'reactionSummaries' => $reactionSummaries,
            'isAdmin' => $isAdmin,
            'conversationWithUserId' => $withUserId,
            'conversationWithUser' => $withUser ? [
                'id' => (int) $withUser->id,
                'name' => (string) ($withUser->name ?? '—'),
                'avatar_url' => avatarUrl($withUser),
            ] : null,
            ...$this->chatViewHelpers(),
        ]);
    }

    public function store(Request $request)
    {
        $this->touchPresence();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['integer', 'min:1', 'exists:users,id'],
        ]);

        $senderId = (int) (Auth::id() ?? 0);

        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');
        $audienceUserIds = [];
        if ($hasAudience) {
            $audienceUserIds = collect($validated['audience_user_ids'] ?? [])
                ->map(fn ($v) => (int) $v)
                ->filter(fn (int $v) => $v > 0 && $v !== $senderId)
                ->unique()
                ->values()
                ->all();
        }

        $audienceType = ($hasAudience && count($audienceUserIds) > 0) ? 'subset' : 'all';

        $message = ChatMessage::create([
            'user_id' => $senderId,
            'body' => $validated['body'],
            ...($hasAudience ? [
                'audience_type' => $audienceType,
                'audience_user_ids' => $audienceType === 'subset' ? $audienceUserIds : null,
            ] : []),
        ]);

        $message->loadMissing('user:id,name,avatar_path,avatar_updated_at');

        broadcast(new ChatMessageSent($message))->toOthers();

        try {
            $senderName = (string) ($message->user?->name ?? 'Quelqu\'un');

            $payload = null;

            if ($hasAudience && ($message->audience_type ?? 'all') === 'subset') {
                $payload = [
                    'title' => 'Message privé de ' . $senderName,
                    'body' => (string) Str::limit((string) $message->body, 140, '…'),
                    'url' => route('chat.index'),
                ];

                $notifyIds = collect($message->audience_user_ids ?? [])
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn (int $v) => $v > 0 && $v !== $senderId)
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($notifyIds)) {
                    app(WebPushNotifier::class)->notifyUsers($notifyIds, $payload, [
                        'TTL' => 600,
                    ]);
                }
            } else {
                $payload = [
                    'title' => $senderName . ' – Nouveau message',
                    'body' => (string) Str::limit((string) $message->body, 140, '…'),
                    'url' => route('chat.index'),
                ];

                if ($senderId > 0) {
                    app(WebPushNotifier::class)->notifyAllExceptUser($senderId, $payload, [
                        'TTL' => 600,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[chat] webpush notify failed: ' . $e->getMessage());
        }

        if ($request->expectsJson()) {
            $reactionSummary = app(ChatReactions::class)->summaryForMessage((int) $message->id, Auth::id());

            $audienceUsers = [];
            if ($hasAudience && ($message->audience_type ?? 'all') === 'subset') {
                $audienceUsers = User::query()
                    ->whereIn('id', $message->audience_user_ids ?? [])
                    ->orderBy('name')
                    ->get(['id', 'name', 'avatar_path', 'avatar_updated_at'])
                    ->map(fn (User $u) => [
                        'id' => (int) $u->id,
                        'name' => (string) ($u->name ?? '—'),
                        'avatar_url' => avatarUrl($u),
                    ])
                    ->values()
                    ->all();
            }

            return response()->json([
                'id' => $message->id,
                'body' => $message->deleted_for_all_at ? '' : $message->body,
                'is_deleted' => (bool) ($message->deleted_for_all_at !== null),
                'deleted_for_all_at' => $message->deleted_for_all_at?->toISOString(),
                'created_at' => $message->created_at?->toISOString(),
                'audience_type' => $hasAudience ? ($message->audience_type ?? 'all') : 'all',
                'audience_user_ids' => $hasAudience ? ($message->audience_user_ids ?? []) : [],
                'audience_users' => $audienceUsers,
                'user' => [
                    'id' => $message->user?->id,
                    'name' => $message->user?->name,
                    'avatar_url' => avatarUrl($message->user),
                ],
                'reaction_summary' => $message->deleted_for_all_at ? [] : $reactionSummary,
            ]);
        }

        return redirect()->route('chat.index')->with('status', __('Message envoyé.'));
    }

    public function poll(Request $request)
    {
        $this->touchPresence();

        $viewerId = (int) (Auth::id() ?? 0);
        $isAdmin = Gate::allows('manage-users');
        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');

        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
            'with_user_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);
        $withUserId = (int) ($validated['with_user_id'] ?? 0);
        if ($withUserId > 0 && !User::query()->whereKey($withUserId)->exists()) {
            $withUserId = 0;
        }

        $messages = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
            ->when($sinceId > 0, fn($q) => $q->where('id', '>', $sinceId))
            ->when(!$isAdmin && $viewerId > 0 && $hasAudience, function ($q) use ($viewerId) {
                $q->where(function ($qq) use ($viewerId) {
                    $qq->whereNull('audience_type')
                        ->orWhere('audience_type', 'all')
                        ->orWhere('user_id', $viewerId)
                        ->orWhere(function ($q2) use ($viewerId) {
                            $q2->where('audience_type', 'subset')
                                ->whereJsonContains('audience_user_ids', $viewerId);
                        });
                });
            })
            ->when($viewerId > 0 && $withUserId > 0, function ($q) use ($viewerId, $withUserId, $hasAudience) {
                $q->where(function ($qq) use ($viewerId, $withUserId, $hasAudience) {
                    $qq->where(function ($pub) use ($viewerId, $withUserId, $hasAudience) {
                        $pub->whereIn('user_id', [$viewerId, $withUserId]);
                        if ($hasAudience) {
                            $pub->where(function ($a) {
                                $a->whereNull('audience_type')
                                    ->orWhere('audience_type', 'all');
                            });
                        }
                    });

                    if ($hasAudience) {
                        $qq->orWhere(function ($sub) use ($viewerId, $withUserId) {
                            $sub->where('audience_type', 'subset')
                                ->where(function ($p) use ($viewerId) {
                                    $p->where('user_id', $viewerId)
                                        ->orWhereJsonContains('audience_user_ids', $viewerId);
                                })
                                ->where(function ($p) use ($withUserId) {
                                    $p->where('user_id', $withUserId)
                                        ->orWhereJsonContains('audience_user_ids', $withUserId);
                                });
                        });
                    }
                });
            })
            ->when($viewerId > 0, fn ($q) => $q->whereDoesntHave('deletions', fn ($dq) => $dq->where('user_id', $viewerId)))
            ->orderBy('id')
            ->limit(50)
            ->get();

        $audienceUserIds = $hasAudience
            ? $messages
                ->filter(fn (ChatMessage $m) => ($m->audience_type ?? 'all') === 'subset')
                ->flatMap(fn (ChatMessage $m) => is_array($m->audience_user_ids) ? $m->audience_user_ids : [])
                ->map(fn ($v) => (int) $v)
                ->filter(fn (int $v) => $v > 0)
                ->unique()
                ->values()
                ->all()
            : [];

        $audienceUsersById = [];
        if (!empty($audienceUserIds)) {
            $audienceUsersById = User::query()
                ->whereIn('id', $audienceUserIds)
                ->get(['id', 'name', 'avatar_path', 'avatar_updated_at'])
                ->mapWithKeys(fn (User $u) => [
                    (int) $u->id => [
                        'id' => (int) $u->id,
                        'name' => (string) ($u->name ?? '—'),
                        'avatar_url' => avatarUrl($u),
                    ],
                ])
                ->all();
        }

        $reactionSummaries = app(ChatReactions::class)
            ->summaryForMessageIds($messages->pluck('id')->map(fn ($v) => (int) $v)->all(), Auth::id());

        $messages = $messages
            ->map(fn(ChatMessage $m) => [
                'id' => $m->id,
                'body' => $m->deleted_for_all_at ? '' : $m->body,
                'is_deleted' => (bool) ($m->deleted_for_all_at !== null),
                'deleted_for_all_at' => $m->deleted_for_all_at?->toISOString(),
                'created_at' => $m->created_at?->toISOString(),
                'audience_type' => $hasAudience ? ($m->audience_type ?? 'all') : 'all',
                'audience_user_ids' => $hasAudience ? ($m->audience_user_ids ?? []) : [],
                'audience_users' => ($hasAudience && ($m->audience_type ?? 'all') === 'subset')
                    ? collect($m->audience_user_ids ?? [])
                        ->map(fn ($id) => $audienceUsersById[(int) $id] ?? null)
                        ->filter()
                        ->values()
                        ->all()
                    : [],
                'user' => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'avatar_url' => avatarUrl($m->user),
                ],
                'reaction_summary' => $m->deleted_for_all_at ? [] : ($reactionSummaries[(int) $m->id] ?? []),
            ])
            ->values();

        $onlineUsers = $this->onlineUsers();

        return response()->json([
            'online' => $onlineUsers,
            'messages' => $messages,
            'last_id' => (int) ($messages->last()['id'] ?? $sinceId),
        ]);
    }

    public function recipients(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $users = User::query()
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'avatar_path', 'avatar_updated_at'])
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => (string) ($u->name ?? '—'),
                'avatar_url' => avatarUrl($u),
            ])
            ->values()
            ->all();

        return response()->json([
            'users' => $users,
        ]);
    }

    private function touchPresence(): void
    {
        $userId = Auth::id();
        if (!$userId) {
            return;
        }

        ChatPresence::query()->updateOrCreate(
            ['user_id' => $userId],
            ['last_seen_at' => now()]
        );
    }

    private function onlineUsers(): array
    {
        // Consider a user online if they pinged in the last 45 seconds.
        $cutoff = now()->subSeconds(45);

        return User::query()
            ->join('chat_presences', 'users.id', '=', 'chat_presences.user_id')
            ->where('chat_presences.last_seen_at', '>=', $cutoff)
            ->orderBy('users.name')
            ->select(['users.id', 'users.name', 'users.avatar_path', 'users.avatar_updated_at'])
            ->get()
            ->map(fn(User $u) => [
                'id' => (int) $u->id,
                'name' => (string) ($u->name ?? '—'),
                'avatar_url' => avatarUrl($u),
            ])
            ->values()
            ->all();
    }
}
