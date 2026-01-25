<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ConversationsController extends Controller
{
    public function index(Request $request)
    {
        $viewerId = (int) (Auth::id() ?? 0);
        if ($viewerId <= 0) {
            abort(403);
        }

        $hasAudience = Schema::hasColumn('chat_messages', 'audience_type') && Schema::hasColumn('chat_messages', 'audience_user_ids');

        $threads = [];
        $otherUserIds = [];

        if ($hasAudience) {
            $recentPrivate = ChatMessage::query()
                ->with('user:id,name,avatar_path,avatar_updated_at')
                ->where('audience_type', 'subset')
                ->where(function ($q) use ($viewerId) {
                    $q->where('user_id', $viewerId)
                        ->orWhereJsonContains('audience_user_ids', $viewerId);
                })
                ->latest('id')
                ->limit(200)
                ->get();

            foreach ($recentPrivate as $m) {
                $senderId = (int) ($m->user_id ?? 0);
                $audIds = is_array($m->audience_user_ids) ? $m->audience_user_ids : [];
                $audIds = collect($audIds)->map(fn ($v) => (int) $v)->filter(fn (int $v) => $v > 0)->values()->all();

                $participants = array_values(array_unique(array_filter([$senderId, ...$audIds])));
                if (!in_array($viewerId, $participants, true)) {
                    continue;
                }

                // Only surface strict 1:1 DMs here (exactly 2 participants including me).
                if (count($participants) !== 2) {
                    continue;
                }

                $otherId = (int) ($participants[0] === $viewerId ? $participants[1] : $participants[0]);
                if ($otherId <= 0 || $otherId === $viewerId) {
                    continue;
                }

                $key = (string) $otherId;
                if (isset($threads[$key])) {
                    continue;
                }

                $threads[$key] = [
                    'other_id' => $otherId,
                    'last_message_id' => (int) $m->id,
                    'last_message_at' => $m->created_at,
                    'last_message_body' => (string) ($m->body ?? ''),
                    'last_message_sender_id' => $senderId,
                ];

                $otherUserIds[] = $otherId;
            }
        }

        $usersById = !empty($otherUserIds)
            ? User::query()
                ->whereIn('id', array_values(array_unique($otherUserIds)))
                ->get(['id', 'name', 'avatar_path', 'avatar_updated_at'])
                ->mapWithKeys(fn (User $u) => [(int) $u->id => $u])
                ->all()
            : [];

        $dmThreads = collect($threads)
            ->values()
            ->map(function (array $t) use ($usersById) {
                $u = $usersById[(int) $t['other_id']] ?? null;

                return [
                    'other_id' => (int) $t['other_id'],
                    'other_name' => $u ? (string) ($u->name ?? '—') : '—',
                    'other_avatar_url' => $u ? avatarUrl($u) : '',
                    'last_message_at' => $t['last_message_at'],
                    'last_message_body' => (string) ($t['last_message_body'] ?? ''),
                ];
            })
            ->sortByDesc(fn ($t) => $t['last_message_at'] ? $t['last_message_at']->timestamp : 0)
            ->values();

        return view('chat.conversations', [
            'dmThreads' => $dmThreads,
        ]);
    }
}
