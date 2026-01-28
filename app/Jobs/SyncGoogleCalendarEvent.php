<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\GoogleEventLink;
use App\Models\User;
use App\Services\Google\GoogleCalendarClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGoogleCalendarEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $eventId,
        public string $action // 'upsert'|'delete'
    ) {
    }

    public function handle(GoogleCalendarClient $client): void
    {
        $event = Event::query()->find($this->eventId);
        if (!$event) {
            Log::info('GoogleCalendarSync: event missing', [
                'job' => self::class,
                'event_id' => $this->eventId,
                'action' => $this->action,
                'db_default' => (string) config('database.default'),
                'db_name' => (string) (DB::connection()->getDatabaseName() ?? ''),
                'queue_db_connection' => (string) (config('queue.connections.database.connection') ?? ''),
            ]);
            return;
        }

        $users = User::query()
            ->whereHas('googleAccount', fn ($q) => $q->whereNull('revoked_at'))
            ->whereHas('googleCalendar', fn ($q) => $q->where('is_enabled', true))
            ->get();

        $stats = [
            'job' => self::class,
            'event_id' => $event->id,
            'action' => $this->action,
            'users_total' => $users->count(),
            'users_pushed' => 0,
            'users_deleted' => 0,
            'users_skipped' => 0,
            'users_errored' => 0,
        ];

        if ($users->isEmpty()) {
            Log::warning('GoogleCalendarSync: no eligible users found', $stats);
            return;
        }

        foreach ($users as $user) {
            // Respect access control: only sync events visible to the user.
            $canSee = Event::visibleTo($user)->where('id', $event->id)->exists();

            if (!$canSee) {
                // If user can't see it, ensure it doesn't remain in their calendar.
                if ($this->action === 'upsert') {
                    try {
                        $client->deleteEventIfLinked($user, $event);
                    } catch (\Throwable $e) {
                        $stats['users_errored']++;
                        report($e);
                    }
                }
                $stats['users_skipped']++;
                continue;
            }

            if ($this->action === 'delete') {
                try {
                    $client->deleteEventIfLinked($user, $event);
                    $stats['users_deleted']++;
                } catch (\Throwable $e) {
                    $stats['users_errored']++;
                    report($e);
                }
                continue;
            }

            try {
                $client->upsertEvent($user, $event);
                $stats['users_pushed']++;
            } catch (\Throwable $e) {
                $stats['users_errored']++;
                report($e);
            }
        }

        Log::info('GoogleCalendarSync: finished', $stats);

        // If the event was deleted from our DB, links will cascade.
        if ($this->action === 'delete') {
            GoogleEventLink::query()->where('event_id', $event->id)->delete();
        }
    }
}
