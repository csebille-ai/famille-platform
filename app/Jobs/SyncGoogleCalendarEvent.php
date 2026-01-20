<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\GoogleEventLink;
use App\Models\User;
use App\Services\Google\GoogleCalendarClient;
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
            return;
        }

        $users = User::query()
            ->whereHas('googleAccount', fn ($q) => $q->whereNull('revoked_at'))
            ->whereHas('googleCalendar', fn ($q) => $q->where('is_enabled', true))
            ->get();

        foreach ($users as $user) {
            // Respect access control: only sync events visible to the user.
            $canSee = Event::visibleTo($user)->where('id', $event->id)->exists();

            if (!$canSee) {
                // If user can't see it, ensure it doesn't remain in their calendar.
                if ($this->action === 'upsert') {
                    $client->deleteEventIfLinked($user, $event);
                }
                continue;
            }

            if ($this->action === 'delete') {
                $client->deleteEventIfLinked($user, $event);
                continue;
            }

            $client->upsertEvent($user, $event);
        }

        // If the event was deleted from our DB, links will cascade.
        if ($this->action === 'delete') {
            GoogleEventLink::query()->where('event_id', $event->id)->delete();
        }
    }
}
