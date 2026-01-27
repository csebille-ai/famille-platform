<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

class EventPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        if (Gate::forUser($user)->allows('manage-users')) {
            return true;
        }

        if (($event->visibility ?? 'family') === 'private') {
            if ((int) $event->created_by_user_id === (int) $user->id) {
                return true;
            }

            return $event->privateSharedWithUsers()
                ->where('users.id', '=', $user->id)
                ->exists();
        }

        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        if (Gate::forUser($user)->allows('manage-users')) {
            return true;
        }

        // Members can edit any family-visible event.
        // Private events remain editable only by their creator (or admins).
        if (($event->visibility ?? 'family') === 'private') {
            if ((int) $event->created_by_user_id === (int) $user->id) {
                return true;
            }

            return $event->privateSharedWithUsers()
                ->where('users.id', '=', $user->id)
                ->exists();
        }

        return true;
    }

    public function delete(User $user, Event $event): bool
    {
        if (Gate::forUser($user)->allows('manage-users')) {
            return true;
        }

        return (int) $event->created_by_user_id === (int) $user->id;
    }
}
