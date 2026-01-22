<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageDeleted;
use App\Models\ChatMessage;
use App\Models\ChatMessageDeletion;
use App\Models\ChatMessageReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatMessageDeletionController extends Controller
{
    public function destroyForMe(Request $request, ChatMessage $message)
    {
        $userId = (int) (Auth::id() ?? 0);
        abort_unless($userId > 0, 401);

        ChatMessageDeletion::query()->firstOrCreate([
            'message_id' => (int) $message->id,
            'user_id' => $userId,
        ]);

        return response()->json([
            'ok' => true,
            'message_id' => (int) $message->id,
            'scope' => 'me',
        ]);
    }

    public function destroyForAll(Request $request, ChatMessage $message)
    {
        $userId = (int) (Auth::id() ?? 0);
        abort_unless($userId > 0, 401);

        $this->authorize('delete', $message);

        if ($message->deleted_for_all_at) {
            return response()->json([
                'ok' => true,
                'message_id' => (int) $message->id,
                'scope' => 'all',
                'deleted_for_all' => true,
                'deleted_for_all_at' => $message->deleted_for_all_at?->toISOString(),
            ]);
        }

        DB::transaction(function () use ($message, $userId) {
            $message->forceFill([
                'deleted_for_all_at' => now(),
                'deleted_for_all_by_user_id' => $userId,
            ])->save();

            // Clear reactions for deleted messages.
            ChatMessageReaction::query()->where('chat_message_id', (int) $message->id)->delete();
        });

        $message->refresh();

        broadcast(new ChatMessageDeleted($message))->toOthers();

        return response()->json([
            'ok' => true,
            'message_id' => (int) $message->id,
            'scope' => 'all',
            'deleted_for_all' => true,
            'deleted_for_all_at' => $message->deleted_for_all_at?->toISOString(),
        ]);
    }
}
