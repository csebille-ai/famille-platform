<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Person $person): bool
    {
        // Family app: members can see all profiles.
        return true;
    }

    public function update(User $user, Person $person): bool
    {
        if ($person->user_id && (int) $person->user_id === (int) $user->id) {
            return true;
        }

        if (!$person->is_child) {
            return false;
        }

        return $person->guardians()
            ->where('users.id', (int) $user->id)
            ->wherePivot('can_edit', true)
            ->exists();
    }

    public function createChild(User $user): bool
    {
        // Family portal: allow any authenticated adult to create a child profile.
        // Admins can always do it, but member/editor can too.
        return true;
    }
}
