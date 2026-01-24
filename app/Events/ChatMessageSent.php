<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatReactions;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
        $this->message->loadMissing('user:id,name,avatar_path,avatar_updated_at');
    }

    public function broadcastOn(): Channel|array
    {
        $audienceType = (string) ($this->message->audience_type ?? 'all');
        if ($audienceType !== 'subset') {
            return new PresenceChannel('chat');
        }

        $channels = [];

        $senderId = (int) ($this->message->user_id ?? 0);
        if ($senderId > 0) {
            $channels[] = new PrivateChannel('chat.user.' . $senderId);
        }

        foreach (($this->message->audience_user_ids ?? []) as $uid) {
            $id = (int) $uid;
            if ($id <= 0) continue;
            $channels[] = new PrivateChannel('chat.user.' . $id);
        }

        // Admins can monitor all targeted messages.
        $channels[] = new PrivateChannel('chat.admin');

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $reactionSummary = app(ChatReactions::class)->summaryForMessage((int) $this->message->id, null);

        $audienceType = (string) ($this->message->audience_type ?? 'all');
        $audienceUsers = [];
        if ($audienceType === 'subset') {
            $ids = collect($this->message->audience_user_ids ?? [])
                ->map(fn ($v) => (int) $v)
                ->filter(fn (int $v) => $v > 0)
                ->unique()
                ->values();

            if ($ids->isNotEmpty()) {
                $audienceUsers = User::query()
                    ->whereIn('id', $ids->all())
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
        }

        return [
            'id' => $this->message->id,
            'body' => $this->message->deleted_for_all_at ? '' : $this->message->body,
            'is_deleted' => (bool) ($this->message->deleted_for_all_at !== null),
            'deleted_for_all_at' => $this->message->deleted_for_all_at?->toISOString(),
            'created_at' => $this->message->created_at?->toISOString(),
            'audience_type' => $audienceType,
            'audience_user_ids' => (array) ($this->message->audience_user_ids ?? []),
            'audience_users' => $audienceUsers,
            'user' => [
                'id' => $this->message->user?->id,
                'name' => $this->message->user?->name,
                'avatar_url' => avatarUrl($this->message->user),
            ],
            'reaction_summary' => $this->message->deleted_for_all_at ? [] : $reactionSummary,
        ];
    }
}
