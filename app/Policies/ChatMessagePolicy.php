<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;

class ChatMessagePolicy
{
    public function delete(User $user, ChatMessage $message): bool
    {
        if ((int) ($message->user_id ?? 0) === (int) ($user->id ?? 0)) {
            return true;
        }

        return $user->can('manage-users');
    }
}
