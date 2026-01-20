<?php

namespace Tests\Feature;

use App\Models\GoogleCalendarAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarOauthTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_start_redirects_to_google_and_sets_state(): void
    {
        $user = User::factory()->create();

        $resp = $this->actingAs($user)->get('/oauth/google/calendar/start');
        $resp->assertRedirect();

        $this->assertNotNull(session('google_calendar_oauth_state'));
        $this->assertStringContainsString('accounts.google.com', $resp->headers->get('Location') ?? '');
    }

    public function test_oauth_callback_exchanges_code_and_saves_tokens(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-123',
                'refresh_token' => 'refresh-456',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/calendar.events',
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/oauth/google/calendar/start');
        $state = (string) session('google_calendar_oauth_state');
        $this->assertNotSame('', $state);

        $resp = $this->actingAs($user)->get('/oauth/google/calendar/callback?state=' . urlencode($state) . '&code=abc');
        $resp->assertRedirect(route('profile.edit'));

        $user->refresh();
        $acc = GoogleCalendarAccount::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($acc);
        $this->assertTrue($acc->isConnected());
    }
}
