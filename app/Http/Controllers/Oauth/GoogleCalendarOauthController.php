<?php

namespace App\Http\Controllers\Oauth;

use App\Models\EventExternalLink;
use App\Models\GoogleCalendarAccount;
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

        $account = GoogleCalendarAccount::query()->firstOrNew(['user_id' => $user->id]);

        $account->access_token = $accessToken;
        if ($refreshToken !== null && $refreshToken !== '') {
            $account->refresh_token = $refreshToken;
        }
        $account->expires_at = $expiresIn !== null ? CarbonImmutable::now('UTC')->addSeconds($expiresIn) : null;
        $account->scope = $scope;
        $account->token_type = $tokenType;
        $account->calendar_id = $account->calendar_id ?: 'primary';
        $account->revoked_at = null;
        $account->save();

        return redirect()->route('profile.edit')->with('status', 'google-calendar-connected');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $account = GoogleCalendarAccount::query()->where('user_id', $user->id)->first();
        if ($account) {
            $account->revoked_at = now();
            $account->access_token = '';
            $account->refresh_token = '';
            $account->expires_at = null;
            $account->scope = null;
            $account->token_type = null;
            $account->save();
        }

        EventExternalLink::query()
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->delete();

        return redirect()->route('profile.edit')->with('status', 'google-calendar-disconnected');
    }
}
