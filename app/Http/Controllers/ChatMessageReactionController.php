<?php

namespace App\Http\Controllers;

use App\Events\MessageReactionsUpdated;
use App\Models\ChatMessage;
use App\Models\ChatMessageReaction;
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

        if (!in_array($emoji, ChatReactions::PICKER_EMOJIS, true)) {
            return response()->json(['message' => 'Emoji not allowed'], 422);
        }

        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $existing = ChatMessageReaction::query()
            ->where('chat_message_id', $message->id)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ChatMessageReaction::query()->create([
                'chat_message_id' => (int) $message->id,
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
        }

        $summary = app(ChatReactions::class)->summaryForMessage((int) $message->id, $userId);

        broadcast(new MessageReactionsUpdated((int) $message->id, $summary))->toOthers();

        $myReactions = [];
        foreach ($summary as $r) {
            if (!empty($r['reacted_by_me'])) {
                $myReactions[] = (string) ($r['emoji'] ?? '');
            }
        }

        return response()->json([
            'message_id' => (int) $message->id,
            // Backward compat (UI already uses it)
            'reaction_summary' => $summary,
            // New contract
            'reactions_summary' => array_map(fn ($r) => [
                'emoji' => (string) ($r['emoji'] ?? ''),
                'count' => (int) ($r['count'] ?? 0),
            ], $summary),
            'my_reactions' => array_values(array_filter($myReactions, fn ($e) => trim((string) $e) !== '')),
        ]);
    }

    public function index(ChatMessage $message)
    {
        $groups = app(ChatReactions::class)->groupsForMessage((int) $message->id);
        return response()->json($groups);
    }
}
