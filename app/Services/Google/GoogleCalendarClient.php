<?php

namespace App\Services\Google;

use App\Models\Event;
use App\Models\EventExternalLink;
use App\Models\GoogleCalendarAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class GoogleCalendarClient
{
    private const AUTH_BASE = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    public function buildAuthorizeUrl(User $user, string $state): string
    {
        $clientId = (string) config('services.google_calendar.client_id');
        $redirectUri = (string) config('services.google_calendar.redirect');

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
            'login_hint' => (string) ($user->email ?? ''),
        ];

        return self::AUTH_BASE . '?' . http_build_query($params);
    }

    /**
     * @return array{access_token:string,refresh_token?:string,expires_in?:int,scope?:string,token_type?:string}
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $clientId = (string) config('services.google_calendar.client_id');
        $clientSecret = (string) config('services.google_calendar.client_secret');
        $redirectUri = (string) config('services.google_calendar.redirect');

        $resp = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$resp->ok()) {
            throw new \RuntimeException('Google token exchange failed.');
        }

        $data = $resp->json();
        if (!is_array($data) || !isset($data['access_token']) || !is_string($data['access_token'])) {
            throw new \RuntimeException('Google token exchange returned invalid payload.');
        }

        return $data;
    }

    public function getValidAccessToken(GoogleCalendarAccount $account): string
    {
        $now = CarbonImmutable::now('UTC');
        $expiresAt = $account->expires_at ? CarbonImmutable::instance($account->expires_at)->utc() : null;

        if ($account->revoked_at !== null) {
            throw new \RuntimeException('Google account revoked.');
        }

        if ($expiresAt !== null && $expiresAt->greaterThan($now->addSeconds(60))) {
            return (string) $account->access_token;
        }

        $refreshToken = (string) ($account->refresh_token ?? '');
        if ($refreshToken === '') {
            throw new \RuntimeException('Missing refresh token.');
        }

        $clientId = (string) config('services.google_calendar.client_id');
        $clientSecret = (string) config('services.google_calendar.client_secret');

        $resp = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$resp->ok()) {
            $account->revoked_at = now();
            $account->access_token = '';
            $account->refresh_token = '';
            $account->expires_at = null;
            $account->save();

            throw new \RuntimeException('Google token refresh failed.');
        }

        $data = $resp->json();
        if (!is_array($data) || !isset($data['access_token']) || !is_string($data['access_token'])) {
            throw new \RuntimeException('Google token refresh returned invalid payload.');
        }

        $account->access_token = $data['access_token'];
        $expiresIn = isset($data['expires_in']) && is_numeric($data['expires_in']) ? (int) $data['expires_in'] : 3600;
        $account->expires_at = now()->addSeconds($expiresIn);
        if (isset($data['scope']) && is_string($data['scope'])) {
            $account->scope = $data['scope'];
        }
        if (isset($data['token_type']) && is_string($data['token_type'])) {
            $account->token_type = $data['token_type'];
        }
        $account->save();

        return (string) $account->access_token;
    }

    /**
     * Create or update the Google Calendar event for a user.
     */
    public function createOrUpdateEvent(User $user, Event $event): EventExternalLink
    {
        $account = $user->googleCalendarAccount;
        if (!$account || !$account->isConnected()) {
            throw new \RuntimeException('Google Calendar not connected.');
        }

        $token = $this->getValidAccessToken($account);
        $calendarId = (string) ($account->calendar_id ?: 'primary');

        $payload = $this->mapEventPayload($event);

        $link = EventExternalLink::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->first();

        if ($link) {
            $url = self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($link->external_event_id);
            $resp = Http::withToken($token)->timeout(15)->put($url, $payload);

            if ($resp->status() === 401) {
                $account->revoked_at = now();
                $account->save();
                throw new \RuntimeException('Google authorization revoked.');
            }

            if (!$resp->ok()) {
                throw new \RuntimeException('Google event update failed.');
            }

            $link->external_calendar_id = $calendarId;
            $link->save();
            return $link;
        }

        $url = self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events';
        $resp = Http::withToken($token)->timeout(15)->post($url, $payload);

        if ($resp->status() === 401) {
            $account->revoked_at = now();
            $account->save();
            throw new \RuntimeException('Google authorization revoked.');
        }

        if (!$resp->ok()) {
            throw new \RuntimeException('Google event creation failed.');
        }

        $data = $resp->json();
        $externalId = is_array($data) && isset($data['id']) && is_string($data['id']) ? $data['id'] : '';
        if ($externalId === '') {
            throw new \RuntimeException('Google event creation returned invalid payload.');
        }

        return EventExternalLink::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'provider' => 'google',
            'external_event_id' => $externalId,
            'external_calendar_id' => $calendarId,
        ]);
    }

    public function removeEvent(User $user, Event $event): void
    {
        $account = $user->googleCalendarAccount;
        if (!$account || !$account->isConnected()) {
            return;
        }

        $link = EventExternalLink::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->first();

        if (!$link) {
            return;
        }

        $token = $this->getValidAccessToken($account);
        $calendarId = (string) ($link->external_calendar_id ?: $account->calendar_id ?: 'primary');

        $url = self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($link->external_event_id);
        $resp = Http::withToken($token)->timeout(15)->delete($url);

        // If already gone, treat as success.
        if ($resp->status() === 404) {
            $link->delete();
            return;
        }

        if ($resp->status() === 401) {
            $account->revoked_at = now();
            $account->save();
            throw new \RuntimeException('Google authorization revoked.');
        }

        if (!$resp->successful()) {
            throw new \RuntimeException('Google event deletion failed.');
        }

        $link->delete();
    }

    /**
     * @return array<string,mixed>
     */
    private function mapEventPayload(Event $event): array
    {
        $title = trim((string) ($event->title ?? ''));
        $title = $title !== '' ? $title : 'Événement';

        $base = rtrim((string) config('app.url'), '/');
        $eventUrl = $base . '/events/' . $event->id;

        $descParts = [];
        $desc = trim((string) ($event->description ?? ''));
        if ($desc !== '') {
            $descParts[] = $desc;
        }
        $descParts[] = $eventUrl;

        $tz = (string) ($event->timezone ?: config('app.timezone', 'UTC'));
        $start = $event->start_at ? CarbonImmutable::instance($event->start_at)->timezone($tz) : CarbonImmutable::now($tz);
        $end = $event->end_at ? CarbonImmutable::instance($event->end_at)->timezone($tz) : null;

        if ((bool) $event->all_day) {
            $startDate = $start->toDateString();
            $endDate = $end ? $end->toDateString() : $startDate;
            $exclusiveEnd = CarbonImmutable::parse($endDate, $tz)->addDay();

            $startPayload = ['date' => $startDate];
            $endPayload = ['date' => $exclusiveEnd->toDateString()];
        } else {
            $endLocal = $end ?: $start->addMinutes(60);
            if ($endLocal->lessThan($start)) {
                $endLocal = $start->addMinutes(60);
            }

            $startPayload = ['dateTime' => $start->toRfc3339String(), 'timeZone' => $tz];
            $endPayload = ['dateTime' => $endLocal->toRfc3339String(), 'timeZone' => $tz];
        }

        $payload = [
            'summary' => $title,
            'description' => implode("\n\n", $descParts),
            'start' => $startPayload,
            'end' => $endPayload,
            'source' => [
                'title' => 'Famille',
                'url' => $eventUrl,
            ],
        ];

        $location = trim((string) ($event->location ?? ''));
        if ($location !== '') {
            $payload['location'] = $location;
        }

        return $payload;
    }
}
