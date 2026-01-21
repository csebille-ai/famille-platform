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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index()
    {
        $this->touchPresence();

        $viewerId = (int) (Auth::id() ?? 0);

        $messages = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
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
        ]);
    }

    public function store(Request $request)
    {
        $this->touchPresence();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = ChatMessage::create([
            'user_id' => Auth::id(),
            'body' => $validated['body'],
        ]);

        $message->loadMissing('user:id,name,avatar_path,avatar_updated_at');

        broadcast(new ChatMessageSent($message))->toOthers();

        try {
            $senderId = (int) (Auth::id() ?? 0);
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

        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);

        $messages = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
            ->when($sinceId > 0, fn($q) => $q->where('id', '>', $sinceId))
            ->when($viewerId > 0, fn ($q) => $q->whereDoesntHave('deletions', fn ($dq) => $dq->where('user_id', $viewerId)))
            ->orderBy('id')
            ->limit(50)
            ->get();

        $reactionSummaries = app(ChatReactions::class)
            ->summaryForMessageIds($messages->pluck('id')->map(fn ($v) => (int) $v)->all(), Auth::id());

        $messages = $messages
            ->map(fn(ChatMessage $m) => [
                'id' => $m->id,
                'body' => $m->deleted_for_all_at ? '' : $m->body,
                'is_deleted' => (bool) ($m->deleted_for_all_at !== null),
                'deleted_for_all_at' => $m->deleted_for_all_at?->toISOString(),
                'created_at' => $m->created_at?->toISOString(),
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
