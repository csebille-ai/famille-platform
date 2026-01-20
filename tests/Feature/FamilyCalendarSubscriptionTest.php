<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyCalendarSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_subscribed_user_sees_add_to_calendar_on_event_show(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'created_by_user_id' => $user->id,
            'title' => 'Test Event',
            'description' => 'Hello',
            'location' => 'Paris',
            'start_at' => now()->addDay(),
            'end_at' => null,
            'all_day' => false,
            'timezone' => config('app.timezone', 'UTC'),
            'visibility' => 'family',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Ajouter à mon agenda');
    }

    public function test_subscribed_user_does_not_see_add_to_calendar_on_event_show_and_feed_is_available(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'created_by_user_id' => $user->id,
            'title' => 'Test Event',
            'start_at' => now()->addDay(),
            'all_day' => false,
            'timezone' => config('app.timezone', 'UTC'),
            'visibility' => 'family',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post('/calendar/family/subscribe')->assertRedirect(route('events.index'));

        $user->refresh();
        $sub = $user->calendarSubscription;
        $this->assertNotNull($sub);
        $this->assertTrue((bool) $sub->is_enabled);

        $this->actingAs($user)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertDontSee('Ajouter à mon agenda');

        $this->get('/calendar/family/' . $sub->token . '.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR')
            ->assertSee('UID:event-' . $event->id);
    }

    public function test_unsubscribe_rotates_token_and_invalidates_old_feed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/calendar/family/subscribe')->assertRedirect(route('events.index'));
        $user->refresh();

        $oldToken = (string) $user->calendarSubscription?->token;
        $this->assertNotSame('', $oldToken);

        $this->actingAs($user)->post('/calendar/family/unsubscribe')->assertRedirect(route('events.index'));
        $user->refresh();

        $newToken = (string) $user->calendarSubscription?->token;
        $this->assertNotSame('', $newToken);
        $this->assertNotSame($oldToken, $newToken);

        $this->get('/calendar/family/' . $oldToken . '.ics')->assertNotFound();
    }
}
