<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Services\WebPush\WebPushNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders {--limit=50 : Max number of reminders to send per run} {--dry-run : Do not send push, just report}';

    protected $description = 'Send web-push reminders for events with reminder_at due and not reminded yet.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            if (!Schema::hasTable('events')) {
                $this->info('events table missing; nothing to do.');
                return self::SUCCESS;
            }
        } catch (\Throwable $e) {
            $this->warn('DB unavailable; skipping reminders.');
            return self::SUCCESS;
        }

        $now = now();

        $q = Event::query();

        if (Schema::hasColumn('events', 'status')) {
            $q->where('status', 'active');
        }

        if (Schema::hasColumn('events', 'notify')) {
            $q->where('notify', true);
        }

        if (Schema::hasColumn('events', 'reminder_at')) {
            $q->whereNotNull('reminder_at')
                ->where('reminder_at', '<=', $now);
        } else {
            $this->info('reminder_at column missing; nothing to do.');
            return self::SUCCESS;
        }

        if (Schema::hasColumn('events', 'reminded_at')) {
            $q->whereNull('reminded_at');
        }

        // Avoid sending extremely stale reminders.
        if (Schema::hasColumn('events', 'start_at')) {
            $q->where('start_at', '>=', $now->copy()->subDays(1));
        }

        $events = $q
            ->orderBy('reminder_at')
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) {
            $this->info('No reminders to send.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Reminders due: ' . $events->count());

        $adminIds = collect();
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
                $adminIds = User::query()
                    ->where('role', 'admin')
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn (int $v) => $v > 0)
                    ->unique()
                    ->values();
            }
        } catch (\Throwable $e) {
            $adminIds = collect();
        }

        $sent = 0;
        $failed = 0;

        foreach ($events as $event) {
            $tz = $event->timezone ?: config('app.timezone');
            $start = $event->start_at ? $event->start_at->copy()->timezone($tz) : null;

            $when = '';
            if ($event->all_day) {
                $when = $start ? $start->translatedFormat('D j M Y') : 'Aujourd’hui';
            } else {
                $when = $start ? $start->translatedFormat('D j M Y \à H:i') : 'Bientôt';
            }

            $body = $when;
            if (is_string($event->location) && trim($event->location) !== '') {
                $body .= ' — ' . trim($event->location);
            }

            $payload = [
                'title' => 'Rappel : ' . (string) $event->title,
                'body' => (string) Str::limit($body, 140, '…'),
                'url' => route('events.show', $event),
            ];

            try {
                if ($dryRun) {
                    $this->line('Would notify event #' . $event->id . ' (' . ($event->visibility ?: 'family') . ')');
                } else {
                    if (($event->visibility ?? 'family') === 'private') {
                        $recipientIds = $adminIds->all();
                        if (!empty($event->created_by_user_id)) {
                            $recipientIds[] = (int) $event->created_by_user_id;
                        }
                        app(WebPushNotifier::class)->notifyUsers($recipientIds, $payload, [
                            'TTL' => 1800,
                        ]);
                    } else {
                        app(WebPushNotifier::class)->notifyAll($payload, [
                            'TTL' => 1800,
                        ]);
                    }

                    if (Schema::hasColumn('events', 'reminded_at')) {
                        $event->forceFill(['reminded_at' => now()])->save();
                    }

                    $sent++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('[events] reminder push failed: ' . $e->getMessage(), [
                    'event_id' => $event->id,
                ]);
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
