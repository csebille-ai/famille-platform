<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
        $this->message->loadMissing('user:id,name,avatar_path,avatar_updated_at');
    }

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('chat');
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => (int) $this->message->id,
            'deleted_for_all' => true,
            'deleted_for_all_at' => $this->message->deleted_for_all_at?->toISOString(),
            'deleted_for_all_by_user_id' => (int) ($this->message->deleted_for_all_by_user_id ?? 0),
        ];
    }
}
