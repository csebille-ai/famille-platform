<?php

namespace App\Console\Commands;

use App\Jobs\ComputeAstroProfile;
use App\Models\User;
use Illuminate\Console\Command;

class RecomputeAstroProfiles extends Command
{
    protected $signature = 'astro:recompute
        {--user-id= : Recompute only this user id}
        {--email= : Recompute only this email}
        {--all : Recompute for all users}
        {--limit=0 : Max users to process (only with --all)}
        {--include-missing-dob : Also process users without date_of_birth}
        {--queue : Dispatch jobs to the queue instead of running synchronously}
        {--sync : Run synchronously (default)}
        {--dry-run : Do not compute, just list targets}
        {--yes : Do not prompt for confirmation}';

    protected $description = 'Recompute astro profiles (archetype/talents/signature) for one or many users.';

    public function handle(): int
    {
        $userId = $this->option('user-id');
        $email = $this->option('email');
        $all = (bool) $this->option('all');
        $limit = (int) $this->option('limit');
        $includeMissingDob = (bool) $this->option('include-missing-dob');
        $dryRun = (bool) $this->option('dry-run');
        $yes = (bool) $this->option('yes');

        $useQueue = (bool) $this->option('queue');
        $useSync = (bool) $this->option('sync') || !$useQueue;

        if ($userId && $email) {
            $this->error('Use either --user-id or --email, not both.');
            return self::INVALID;
        }

        if (!$all && !$userId && !$email) {
            $this->error('Choose a target: --user-id=, --email=, or --all.');
            return self::INVALID;
        }

        if ($all && ($userId || $email)) {
            $this->error('When using --all, do not pass --user-id or --email.');
            return self::INVALID;
        }

        if ($limit < 0) {
            $limit = 0;
        }
        if ($limit > 50000) {
            $limit = 50000;
        }

        $query = User::query()->orderBy('id');

        if (!$includeMissingDob) {
            $query->whereNotNull('date_of_birth');
        }

        if ($userId) {
            if (!is_numeric($userId)) {
                $this->error('--user-id must be a number.');
                return self::INVALID;
            }
            $query->whereKey((int) $userId);
        }

        if ($email) {
            $query->where('email', (string) $email);
        }

        if ($all && $limit > 0) {
            $query->limit($limit);
        }

        $targets = $query->get(['id', 'email', 'name']);
        $count = $targets->count();

        if ($count === 0) {
            $this->info('No users to process.');
            return self::SUCCESS;
        }

        $mode = $useQueue ? 'queue' : 'sync';
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Users to process: {$count} ({$mode})");

        if ($all && !$yes && !$dryRun) {
            if (!$this->confirm('Proceed?')) {
                $this->warn('Aborted.');
                return self::SUCCESS;
            }
        }

        if ($dryRun) {
            foreach ($targets as $u) {
                $label = trim((string) ($u->email ?? ''));
                if ($label === '') {
                    $label = (string) ($u->name ?? '');
                }
                $this->line("#{$u->id} {$label}");
            }
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($targets as $u) {
            if ($useQueue) {
                ComputeAstroProfile::dispatch((int) $u->id);
            } else {
                ComputeAstroProfile::dispatchSync((int) $u->id);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
