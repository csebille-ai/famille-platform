<?php

namespace Tests\Feature;

use App\Jobs\InitialGoogleCalendarSync;
use App\Models\GoogleAccount;
use App\Models\GoogleCalendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarOauthTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_start_redirects_to_google_and_sets_state(): void
    {
        config()->set('services.google_calendar.redirect', '');

        $user = User::factory()->create();

        $resp = $this->actingAs($user)->get('/oauth/google/calendar/start');
        $resp->assertRedirect();

        $this->assertNotNull(session('google_calendar_oauth_state'));
        $location = (string) ($resp->headers->get('Location') ?? '');
        $this->assertStringContainsString('accounts.google.com', $location);

        $query = parse_url($location, PHP_URL_QUERY);
        parse_str((string) $query, $params);
        $this->assertArrayHasKey('redirect_uri', $params);
        $this->assertNotSame('', (string) $params['redirect_uri']);
    }

    public function test_oauth_callback_exchanges_code_and_saves_tokens(): void
    {
        Bus::fake();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-123',
                'refresh_token' => 'refresh-456',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/calendar',
                'token_type' => 'Bearer',
            ], 200),
            'https://www.googleapis.com/calendar/v3/calendars' => Http::response([
                'id' => 'cal_123',
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/oauth/google/calendar/start');
        $state = (string) session('google_calendar_oauth_state');
        $this->assertNotSame('', $state);

        $resp = $this->actingAs($user)->get('/oauth/google/calendar/callback?state=' . urlencode($state) . '&code=abc');
        $resp->assertRedirect(route('profile.edit'));

        $user->refresh();

        $acc = GoogleAccount::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($acc);
        $this->assertTrue($acc->isConnected());

        $cal = GoogleCalendar::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($cal);
        $this->assertSame('cal_123', $cal->google_calendar_id);

        Bus::assertDispatched(InitialGoogleCalendarSync::class);
    }
}
