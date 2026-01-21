<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int,array{emoji:string,count:int,reacted_by_me:bool}>  $reactionSummary
     */
    public function __construct(public int $messageId, public array $reactionSummary)
    {
    }

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('chat');
    }

    public function broadcastAs(): string
    {
        return 'message.reactions.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'reaction_summary' => $this->reactionSummary,
        ];
    }
}
