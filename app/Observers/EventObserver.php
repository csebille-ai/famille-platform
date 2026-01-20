<?php

namespace App\Observers;

use App\Jobs\SyncGoogleCalendarEvent;
use App\Models\Event;

class EventObserver
{
    public function created(Event $event): void
    {
        SyncGoogleCalendarEvent::dispatch($event->id, 'upsert');
    }

    public function updated(Event $event): void
    {
        SyncGoogleCalendarEvent::dispatch($event->id, 'upsert');
    }

    public function deleted(Event $event): void
    {
        SyncGoogleCalendarEvent::dispatch($event->id, 'delete');
    }
}
