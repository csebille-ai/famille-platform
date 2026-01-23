<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatReactions
{
    /**
     * Base set used for ordering + picker.
     *
     * @var array<int,string>
     */
    public const BASE_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    /**
     * Fixed emoji set for the picker (whitelist).
     *
     * @var array<int,string>
     */
    public const PICKER_EMOJIS = [
        '👍', '❤️', '😂', '😮', '😢', '🙏',
        '🎉', '🔥', '😍', '🤩', '😎', '🤔',
        '😅', '😭', '👏', '✅', '❌', '💯',
    ];

    /**
     * @param  array<int,int>  $messageIds
     * @return array<int,array<int,array{emoji:string,count:int,reacted_by_me:bool}>>
     */
    public function summaryForMessageIds(array $messageIds, ?int $meUserId): array
    {
        $messageIds = array_values(array_filter(array_map('intval', $messageIds), fn ($id) => $id > 0));
        if (count($messageIds) === 0) {
            return [];
        }

        $meUserId = (int) ($meUserId ?? 0);

        $rows = DB::table('chat_message_reactions')
            ->whereIn('chat_message_id', $messageIds)
            ->selectRaw(
                'chat_message_id, emoji, count(*) as count, sum(case when user_id = ? then 1 else 0 end) as me_count',
                [$meUserId]
            )
            ->groupBy('chat_message_id', 'emoji')
            ->get();

        $byMessage = [];
        foreach ($rows as $r) {
            $messageId = (int) ($r->chat_message_id ?? 0);
            if ($messageId <= 0) {
                continue;
            }

            $emoji = (string) ($r->emoji ?? '');
            if (trim($emoji) === '') {
                continue;
            }

            $count = (int) ($r->count ?? 0);
            if ($count <= 0) {
                continue;
            }

            $reactedByMe = ((int) ($r->me_count ?? 0)) > 0;

            $byMessage[$messageId] ??= [];
            $byMessage[$messageId][] = [
                'emoji' => $emoji,
                'count' => $count,
                'reacted_by_me' => $reactedByMe,
            ];
        }

        foreach ($byMessage as $mid => $list) {
            usort($list, function (array $a, array $b): int {
                $ra = $this->emojiRank((string) ($a['emoji'] ?? ''));
                $rb = $this->emojiRank((string) ($b['emoji'] ?? ''));
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }

                $ca = (int) ($a['count'] ?? 0);
                $cb = (int) ($b['count'] ?? 0);
                if ($ca !== $cb) {
                    return $cb <=> $ca;
                }

                return strcmp((string) ($a['emoji'] ?? ''), (string) ($b['emoji'] ?? ''));
            });

            $byMessage[$mid] = array_values($list);
        }

        return $byMessage;
    }

    /**
     * @return array<int,array{emoji:string,count:int,reacted_by_me:bool}>
     */
    public function summaryForMessage(int $messageId, ?int $meUserId): array
    {
        $map = $this->summaryForMessageIds([$messageId], $meUserId);
        return $map[$messageId] ?? [];
    }

    /**
     * @return array{emoji_groups: array<string, array<int,array{id:int,name:string,avatar_url:string|null}>>}
     */
    public function groupsForMessage(int $messageId): array
    {
        $messageId = (int) $messageId;
        if ($messageId <= 0) {
            return ['emoji_groups' => []];
        }

        $rows = User::query()
            ->join('chat_message_reactions', 'users.id', '=', 'chat_message_reactions.user_id')
            ->where('chat_message_reactions.chat_message_id', '=', $messageId)
            ->orderBy('chat_message_reactions.emoji')
            ->orderBy('users.name')
            ->select([
                'users.id',
                'users.name',
                'users.avatar_path',
                'users.avatar_updated_at',
                'chat_message_reactions.emoji as reaction_emoji',
            ])
            ->get();

        $groups = [];
        foreach ($rows as $u) {
            $emoji = (string) ($u->reaction_emoji ?? '');
            if (trim($emoji) === '') {
                continue;
            }

            $groups[$emoji] ??= [];
            $groups[$emoji][] = [
                'id' => (int) ($u->id ?? 0),
                'name' => (string) ($u->name ?? '—'),
                'avatar_url' => avatarUrl($u),
            ];
        }

        $ordered = [];
        foreach (self::BASE_EMOJIS as $e) {
            if (isset($groups[$e])) {
                $ordered[$e] = $groups[$e];
                unset($groups[$e]);
            }
        }
        foreach ($groups as $e => $users) {
            $ordered[$e] = $users;
        }

        return ['emoji_groups' => $ordered];
    }

    /**
     * @return array<int,array{id:int,name:string,avatar_url:string|null}>
     */
    public function usersForMessageEmoji(int $messageId, string $emoji): array
    {
        $messageId = (int) $messageId;
        $emoji = trim((string) $emoji);

        if ($messageId <= 0 || $emoji == '') {
            return [];
        }

        $rows = User::query()
            ->join('chat_message_reactions', 'users.id', '=', 'chat_message_reactions.user_id')
            ->where('chat_message_reactions.chat_message_id', '=', $messageId)
            ->where('chat_message_reactions.emoji', '=', $emoji)
            ->orderBy('users.name')
            ->select([
                'users.id',
                'users.name',
                'users.avatar_path',
                'users.avatar_updated_at',
            ])
            ->get();

        $users = [];
        foreach ($rows as $u) {
            $users[] = [
                'id' => (int) ($u->id ?? 0),
                'name' => (string) ($u->name ?? '—'),
                'avatar_url' => avatarUrl($u),
            ];
        }

        return $users;
    }

    private function emojiRank(string $emoji): int
    {
        $i = array_search($emoji, self::BASE_EMOJIS, true);
        return $i === false ? 999 : (int) $i;
    }
}
