<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventExternalLink;
use App\Models\GoogleCalendarAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarEventActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_creates_google_event_and_saves_mapping(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response(['id' => 'g-evt-1'], 200),
        ]);

        $user = User::factory()->create();
        GoogleCalendarAccount::create([
            'user_id' => $user->id,
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'token_type' => 'Bearer',
            'calendar_id' => 'primary',
        ]);

        $event = Event::create([
            'created_by_user_id' => $user->id,
            'title' => 'Test Event',
            'start_at' => now()->addDay(),
            'all_day' => false,
            'timezone' => config('app.timezone', 'UTC'),
            'visibility' => 'family',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('events.google_calendar.add', $event))
            ->assertRedirect(route('events.show', $event));

        $this->assertDatabaseHas('event_external_links', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'provider' => 'google',
            'external_event_id' => 'g-evt-1',
        ]);
    }

    public function test_add_twice_updates_existing_google_event(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response(['id' => 'g-evt-1'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/g-evt-1' => Http::response(['id' => 'g-evt-1'], 200),
        ]);

        $user = User::factory()->create();
        GoogleCalendarAccount::create([
            'user_id' => $user->id,
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
        ]);

        $event = Event::create([
            'created_by_user_id' => $user->id,
            'title' => 'Test Event',
            'start_at' => now()->addDay(),
            'all_day' => false,
            'timezone' => config('app.timezone', 'UTC'),
            'visibility' => 'family',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('events.google_calendar.add', $event));
        $this->actingAs($user)->post(route('events.google_calendar.add', $event));

        $this->assertSame(1, EventExternalLink::query()->where('event_id', $event->id)->where('user_id', $user->id)->where('provider', 'google')->count());
    }

    public function test_remove_deletes_google_event_and_mapping(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response(['id' => 'g-evt-1'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/g-evt-1' => Http::response(null, 204),
        ]);

        $user = User::factory()->create();
        GoogleCalendarAccount::create([
            'user_id' => $user->id,
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
        ]);

        $event = Event::create([
            'created_by_user_id' => $user->id,
            'title' => 'Test Event',
            'start_at' => now()->addDay(),
            'all_day' => false,
            'timezone' => config('app.timezone', 'UTC'),
            'visibility' => 'family',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('events.google_calendar.add', $event));

        $this->actingAs($user)
            ->post(route('events.google_calendar.remove', $event))
            ->assertRedirect(route('events.show', $event));

        $this->assertDatabaseMissing('event_external_links', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    public function test_expired_access_token_triggers_refresh(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response(['id' => 'g-evt-2'], 200),
        ]);

        $user = User::factory()->create();
        $acc = GoogleCalendarAccount::create([
            'user_id' => $user->id,
            'access_token' => 'old-access',
            'refresh_token' => 'refresh',
            'expires_at' => now()->subMinutes(5),
            'calendar_id' => 'primary',
        ]);

        $event = Event::create([
            'created_by_user_id' => $user->id,
            'title' => 'Test Event',
            'start_at' => now()->addDay(),
            'all_day' => false,
            'timezone' => config('app.timezone', 'UTC'),
            'visibility' => 'family',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('events.google_calendar.add', $event));

        $acc->refresh();
        $this->assertSame('new-access', (string) $acc->access_token);
    }
}
