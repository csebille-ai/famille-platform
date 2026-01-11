<?php

namespace App\Console\Commands;

use App\Mail\UserInviteMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendPendingUserInvites extends Command
{
    protected $signature = 'users:send-invites {--limit=0 : Max number of invitations to send} {--dry-run : Do not send emails, just report what would happen}';

    protected $description = 'Send invitation emails for users with no invited_at timestamp yet.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 0) {
            $limit = 0;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        $dryRun = (bool) $this->option('dry-run');

        $q = User::query()
            ->whereNull('invited_at')
            ->orderBy('id');

        if ($limit > 0) {
            $q->limit($limit);
        }

        $users = $q->get();
        $total = $users->count();

        if ($total === 0) {
            $this->info('No pending invitations.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Pending invitations: {$total}");

        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            $label = (string) $user->email;

            if ($dryRun) {
                $this->line("Would send: {$label}");
                continue;
            }

            $token = Password::createToken($user);
            $acceptUrl = route('invite.create', ['token' => $token, 'email' => $user->email]);

            try {
                Mail::to($user->email)->send(new UserInviteMail($user, $token, $acceptUrl));
                $user->forceFill(['invited_at' => now()])->save();
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
                $this->error("Failed: {$label}");
            }
        }

        if ($dryRun) {
            $this->info('Dry run complete.');
            return self::SUCCESS;
        }

        $this->info("Sent: {$sent}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
