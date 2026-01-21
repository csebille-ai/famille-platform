<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
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

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('chat');
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toISOString(),
            'user' => [
                'id' => $this->message->user?->id,
                'name' => $this->message->user?->name,
                'avatar_url' => avatarUrl($this->message->user),
            ],
        ];
    }
}
