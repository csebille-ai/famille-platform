<?php

namespace App\Observers;

use App\Jobs\ComputeAstroProfile;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        // Keep it immediate so the profile UI is ready without relying on a running queue.
        ComputeAstroProfile::dispatchSync((int) $user->id);
    }

    public function updated(User $user): void
    {
        if ($user->wasRecentlyCreated) {
            return;
        }

        $watched = [
            'date_of_birth',
            'birth_time',
            'birth_place',
            'birth_timezone',
            'birth_latitude',
            'birth_longitude',
        ];

        foreach ($watched as $attr) {
            if ($user->wasChanged($attr)) {
                ComputeAstroProfile::dispatchSync((int) $user->id);
                return;
            }
        }
    }
}
