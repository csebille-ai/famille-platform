<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetRole extends Command
{
    protected $signature = 'app:set-role
        {user : User id or email}
        {role : member|editor|admin}';

    protected $description = 'Set a user role (member/editor/admin).';

    public function handle(): int
    {
        $userArg = (string) $this->argument('user');
        $role = (string) $this->argument('role');

        $allowed = ['member', 'editor', 'admin'];
        if (!in_array($role, $allowed, true)) {
            $this->error('Invalid role. Allowed: ' . implode(', ', $allowed));
            return self::FAILURE;
        }

        $query = User::query();
        if (ctype_digit($userArg)) {
            $query->whereKey((int) $userArg);
        } else {
            $query->where('email', $userArg);
        }

        $user = $query->first();
        if (!$user) {
            $this->error('User not found: ' . $userArg);
            return self::FAILURE;
        }

        $user->role = $role;
        $user->save();

        $this->info(sprintf('User #%d (%s) role set to %s.', $user->id, $user->email, $role));
        return self::SUCCESS;
    }
}
