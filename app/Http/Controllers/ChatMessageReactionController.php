<?php

namespace App\Http\Controllers;

use App\Events\MessageReactionsUpdated;
use App\Models\ChatMessage;
use App\Models\MessageReaction;
use App\Services\ChatReactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatMessageReactionController extends Controller
{
    public function toggle(Request $request, ChatMessage $message)
    {
        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $emoji = trim((string) ($validated['emoji'] ?? ''));
        if ($emoji === '') {
            return response()->json(['message' => 'Invalid emoji'], 422);
        }

        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $existing = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            MessageReaction::query()->create([
                'message_id' => (int) $message->id,
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
        }

        $summary = app(ChatReactions::class)->summaryForMessage((int) $message->id, $userId);

        broadcast(new MessageReactionsUpdated((int) $message->id, $summary))->toOthers();

        return response()->json([
            'message_id' => (int) $message->id,
            'reaction_summary' => $summary,
        ]);
    }

    public function index(ChatMessage $message)
    {
        $groups = app(ChatReactions::class)->groupsForMessage((int) $message->id);
        return response()->json($groups);
    }
}
