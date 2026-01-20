<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\User;
use App\Services\Google\GoogleCalendarClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InitialGoogleCalendarSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId)
    {
    }

    public function handle(GoogleCalendarClient $client): void
    {
        $user = User::query()->find($this->userId);
        if (!$user || !$user->hasGoogleCalendarSyncEnabled()) {
            return;
        }

        // Ensure the dedicated calendar exists before syncing events.
        $client->ensureDedicatedFamilyCalendar($user);

        // Simple bounded sync: upcoming + recent.
        $from = now()->subDays(30);
        $to = now()->addDays(365);

        $events = Event::visibleTo($user)
            ->whereNotNull('start_at')
            ->whereBetween('start_at', [$from, $to])
            ->orderBy('start_at')
            ->get();

        foreach ($events as $event) {
            $client->upsertEvent($user, $event);
        }
    }
}
