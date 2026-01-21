<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\ChatPresence;
use App\Models\ChatMessage;
use App\Models\User;
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

        $messages = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $initialOnline = $this->onlineUsers();
        $lastMessageId = (int) ($messages->last()?->id ?? 0);

        return view('chat.index', [
            'messages' => $messages,
            'initialOnline' => $initialOnline,
            'lastMessageId' => $lastMessageId,
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
            return response()->json([
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at?->toISOString(),
                'user' => [
                    'id' => $message->user?->id,
                    'name' => $message->user?->name,
                    'avatar_url' => avatarUrl($message->user),
                ],
            ]);
        }

        return redirect()->route('chat.index')->with('status', __('Message envoyé.'));
    }

    public function poll(Request $request)
    {
        $this->touchPresence();

        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);

        $messages = ChatMessage::query()
            ->with('user:id,name,avatar_path,avatar_updated_at')
            ->when($sinceId > 0, fn($q) => $q->where('id', '>', $sinceId))
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn(ChatMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'created_at' => $m->created_at?->toISOString(),
                'user' => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'avatar_url' => avatarUrl($m->user),
                ],
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
