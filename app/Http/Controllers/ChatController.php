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
    public function index()
    {
        $this->touchPresence();

        $viewerId = (int) (Auth::id() ?? 0);
        $isAdmin = Gate::allows('manage-users');
        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');

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
            ->when($viewerId > 0, fn ($q) => $q->whereDoesntHave('deletions', fn ($dq) => $dq->where('user_id', $viewerId)))
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $reactionSummaries = app(ChatReactions::class)
            ->summaryForMessageIds($messages->pluck('id')->map(fn ($v) => (int) $v)->all(), Auth::id());

        $initialOnline = $this->onlineUsers();
        $lastMessageId = (int) ($messages->last()?->id ?? 0);

        return view('chat.index', [
            'messages' => $messages,
            'initialOnline' => $initialOnline,
            'lastMessageId' => $lastMessageId,
            'reactionSummaries' => $reactionSummaries,
            'isAdmin' => $isAdmin,
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
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);

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
