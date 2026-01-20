<?php

namespace App\Http\Controllers\Oauth;

use App\Jobs\InitialGoogleCalendarSync;
use App\Models\GoogleAccount;
use App\Models\GoogleCalendar;
use App\Models\GoogleEventLink;
use App\Models\User;
use App\Services\Google\GoogleCalendarClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleCalendarOauthController
{
    public function start(Request $request, GoogleCalendarClient $client): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $state = Str::random(64);
        $request->session()->put('google_calendar_oauth_state', $state);
        $request->session()->put('google_calendar_oauth_state_ts', time());

        $url = $client->buildAuthorizeUrl($user, $state);
        return redirect()->away($url);
    }

    public function callback(Request $request, GoogleCalendarClient $client): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $state = (string) $request->query('state', '');
        $expected = (string) $request->session()->pull('google_calendar_oauth_state', '');

        if ($state === '' || $expected === '' || !hash_equals($expected, $state)) {
            abort(419);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('profile.edit')->with('status', 'google-calendar-error');
        }

        $tokens = $client->exchangeCodeForTokens($code);

        $accessToken = (string) ($tokens['access_token'] ?? '');
        $refreshToken = isset($tokens['refresh_token']) && is_string($tokens['refresh_token']) ? $tokens['refresh_token'] : null;
        $expiresIn = isset($tokens['expires_in']) && is_numeric($tokens['expires_in']) ? (int) $tokens['expires_in'] : null;
        $scope = isset($tokens['scope']) && is_string($tokens['scope']) ? $tokens['scope'] : null;
        $tokenType = isset($tokens['token_type']) && is_string($tokens['token_type']) ? $tokens['token_type'] : null;

        $account = GoogleAccount::query()->firstOrNew(['user_id' => $user->id]);

        $account->access_token = $accessToken;
        if ($refreshToken !== null && $refreshToken !== '') {
            $account->refresh_token = $refreshToken;
        }
        $account->expires_at = $expiresIn !== null ? CarbonImmutable::now('UTC')->addSeconds($expiresIn) : null;
        $account->scope = $scope;
        $account->token_type = $tokenType;
        $account->revoked_at = null;
        $account->save();

        // Ensure per-user calendar row exists and create the dedicated calendar immediately.
        GoogleCalendar::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'summary' => (string) config('services.google_calendar.family_calendar_summary', 'Famille — Calendrier'),
                'timezone' => (string) config('services.google_calendar.default_tz', config('app.timezone', 'UTC')),
                'is_enabled' => true,
            ]
        );

        try {
            $client->ensureDedicatedFamilyCalendar($user);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('profile.edit')->with('status', 'google-calendar-error');
        }

        InitialGoogleCalendarSync::dispatch($user->id);

        return redirect()->route('profile.edit')->with('status', 'google-calendar-connected');
    }

    public function toggleSync(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cal = GoogleCalendar::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'summary' => (string) config('services.google_calendar.family_calendar_summary', 'Famille — Calendrier'),
                'timezone' => (string) config('services.google_calendar.default_tz', config('app.timezone', 'UTC')),
                'is_enabled' => true,
            ]
        );

        $cal->is_enabled = !$cal->is_enabled;
        $cal->save();

        return redirect()->route('profile.edit')->with('status', $cal->is_enabled ? 'google-calendar-sync-enabled' : 'google-calendar-sync-disabled');
    }

    public function resync(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->hasGoogleCalendarSyncEnabled()) {
            return redirect()->route('profile.edit')->with('status', 'google-calendar-sync-disabled');
        }

        InitialGoogleCalendarSync::dispatch($user->id);
        return redirect()->route('profile.edit')->with('status', 'google-calendar-resync-started');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Best-effort cleanup on Google side.
        try {
            app(GoogleCalendarClient::class)->deleteDedicatedCalendar($user);
        } catch (\Throwable $e) {
            report($e);
        }

        $account = GoogleAccount::query()->where('user_id', $user->id)->first();
        if ($account) {
            $account->revoked_at = now();
            $account->access_token = '';
            $account->refresh_token = '';
            $account->expires_at = null;
            $account->scope = null;
            $account->token_type = null;
            $account->save();
        }

        GoogleEventLink::query()->where('user_id', $user->id)->delete();

        $cal = GoogleCalendar::query()->where('user_id', $user->id)->first();
        if ($cal) {
            $cal->google_calendar_id = null;
            $cal->is_enabled = false;
            $cal->save();
        }

        return redirect()->route('profile.edit')->with('status', 'google-calendar-disconnected');
    }
}
