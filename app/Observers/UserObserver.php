<?php

namespace App\Observers;

use App\Jobs\ComputeAstroProfile;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class UserObserver
{
    public function created(User $user): void
    {
        $this->syncPerson($user);

        // Keep it immediate so the profile UI is ready without relying on a running queue.
        ComputeAstroProfile::dispatchSync((int) $user->id);
    }

    public function updated(User $user): void
    {
        if ($user->wasRecentlyCreated) {
            return;
        }

        $this->syncPerson($user);

        $watched = [
            'date_of_birth',
            'birth_time',
            'birth_place',
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

    private function syncPerson(User $user): void
    {
        try {
            if (!Schema::hasTable('people')) {
                return;
            }

            $name = trim((string) $user->name);
            $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $first = (string) ($parts[0] ?? '');
            $last = '';
            if (count($parts) > 1) {
                $last = trim((string) implode(' ', array_slice($parts, 1)));
            }
            if ($first === '') {
                $first = 'Utilisateur';
            }

            Person::query()->updateOrCreate(
                ['user_id' => (int) $user->id],
                [
                    'first_name' => $first,
                    'last_name' => $last !== '' ? $last : null,
                    'birth_date' => $user->date_of_birth,
                    'is_child' => false,
                ]
            );
        } catch (\Throwable $e) {
            // Never block auth/user flows on family sync.
            report($e);
        }
    }
}
