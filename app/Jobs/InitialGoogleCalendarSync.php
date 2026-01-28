<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\User;
use App\Services\Google\GoogleCalendarClient;
use Illuminate\Support\Facades\Log;
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
            Log::info('GoogleCalendarResync: skipped (user missing or sync disabled)', [
                'job' => self::class,
                'user_id' => $this->userId,
            ]);
            return;
        }

        // Ensure the dedicated calendar exists before syncing events.
        try {
            $client->ensureDedicatedFamilyCalendar($user);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('GoogleCalendarResync: failed to ensure calendar', [
                'job' => self::class,
                'user_id' => $user->id,
            ]);
            return;
        }

        // Simple bounded sync: upcoming + recent.
        $from = now()->subDays(30);
        $to = now()->addDays(365);

        $events = Event::visibleTo($user)
            ->whereNotNull('start_at')
            ->whereBetween('start_at', [$from, $to])
            ->orderBy('start_at')
            ->get();

        Log::info('GoogleCalendarResync: starting', [
            'job' => self::class,
            'user_id' => $user->id,
            'events_count' => $events->count(),
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
        ]);

        foreach ($events as $event) {
            try {
                $client->upsertEvent($user, $event);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        Log::info('GoogleCalendarResync: finished', [
            'job' => self::class,
            'user_id' => $user->id,
            'events_count' => $events->count(),
        ]);
    }
}
