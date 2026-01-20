<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleCalendarEvent;
use App\Models\Event;
use App\Models\GoogleAccount;
use App\Models\GoogleCalendar;
use App\Models\User;
use App\Services\Google\GoogleCalendarClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarAutoSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_observer_dispatches_sync_job_on_create(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $event = Event::create([
            'created_by_user_id' => $user->id,
            'title' => 'Test',
            'start_at' => now()->addDay(),
            'all_day' => false,
            'visibility' => 'family',
            'status' => 'active',
        ]);

        Bus::assertDispatched(SyncGoogleCalendarEvent::class, function (SyncGoogleCalendarEvent $job) use ($event) {
            return $job->eventId === $event->id && $job->action === 'upsert';
        });
    }

    public function test_sync_job_creates_calendar_and_event_link_for_visible_event(): void
    {
        Bus::fake();

        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars' => Http::response(['id' => 'cal_123'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/cal_123/events' => Http::response(['id' => 'evt_456'], 200),
        ]);

        $user = User::factory()->create();

        GoogleAccount::create([
            'user_id' => $user->id,
            'access_token' => 'access-123',
            'refresh_token' => 'refresh-456',
            'expires_at' => now()->addHour(),
        ]);

        GoogleCalendar::create([
            'user_id' => $user->id,
            'google_calendar_id' => null,
            'summary' => 'Famille — Calendrier',
            'timezone' => 'Europe/Paris',
            'is_enabled' => true,
        ]);

        $event = Event::withoutEvents(fn () => Event::create([
            'created_by_user_id' => $user->id,
            'title' => 'Dentiste',
            'start_at' => now()->addDays(2)->setTime(10, 0),
            'end_at' => now()->addDays(2)->setTime(11, 0),
            'all_day' => false,
            'visibility' => 'family',
            'status' => 'active',
        ]));

        (new SyncGoogleCalendarEvent($event->id, 'upsert'))->handle(app(GoogleCalendarClient::class));

        $this->assertDatabaseHas('google_calendars', [
            'user_id' => $user->id,
            'google_calendar_id' => 'cal_123',
        ]);

        $this->assertDatabaseHas('google_event_links', [
            'user_id' => $user->id,
            'event_id' => $event->id,
            'google_calendar_id' => 'cal_123',
            'google_event_id' => 'evt_456',
        ]);
    }
}
