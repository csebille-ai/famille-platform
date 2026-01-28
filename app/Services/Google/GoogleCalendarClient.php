<?php

namespace App\Services\Google;

use App\Models\Event;
use App\Models\GoogleAccount;
use App\Models\GoogleCalendar;
use App\Models\GoogleEventLink;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class GoogleCalendarClient
{
    private const AUTH_BASE = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/calendar';

    private function resolveRedirectUri(): string
    {
        $redirectUri = trim((string) config('services.google_calendar.redirect'));
        if ($redirectUri !== '') {
            return $redirectUri;
        }

        // Fallback to the app route so OAuth doesn't break when env is missing.
        $fallback = trim((string) route('oauth.google.calendar.callback'));
        if ($fallback === '') {
            throw new \RuntimeException('Google OAuth redirect URI is not configured. Set GOOGLE_REDIRECT_URI or APP_URL.');
        }

        return $fallback;
    }

    private function requireNonEmpty(string $value, string $name): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new \RuntimeException("Missing Google OAuth configuration: {$name}.");
        }

        return $value;
    }

    public function buildAuthorizeUrl(User $user, string $state): string
    {
        $clientId = $this->requireNonEmpty((string) config('services.google_calendar.client_id'), 'GOOGLE_CLIENT_ID');
        $redirectUri = $this->requireNonEmpty($this->resolveRedirectUri(), 'GOOGLE_REDIRECT_URI/APP_URL');

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
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
        $clientId = $this->requireNonEmpty((string) config('services.google_calendar.client_id'), 'GOOGLE_CLIENT_ID');
        $clientSecret = $this->requireNonEmpty((string) config('services.google_calendar.client_secret'), 'GOOGLE_CLIENT_SECRET');
        $redirectUri = $this->requireNonEmpty($this->resolveRedirectUri(), 'GOOGLE_REDIRECT_URI/APP_URL');

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

    public function getValidAccessToken(GoogleAccount $account): string
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
     * Ensure the dedicated "Famille — Calendrier" exists for the user.
     */
    public function ensureDedicatedFamilyCalendar(User $user): GoogleCalendar
    {
        $account = $user->googleAccount;
        if (!$account || !$account->isConnected()) {
            throw new \RuntimeException('Google Calendar not connected.');
        }

        $cal = GoogleCalendar::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'summary' => (string) config('services.google_calendar.family_calendar_summary', 'Famille — Calendrier'),
                'timezone' => (string) config('services.google_calendar.default_tz', config('app.timezone', 'UTC')),
                'is_enabled' => true,
            ]
        );

        if (!$cal->is_enabled) {
            return $cal;
        }

        if (is_string($cal->google_calendar_id) && $cal->google_calendar_id !== '') {
            return $cal;
        }

        $token = $this->getValidAccessToken($account);
        $payload = [
            'summary' => (string) ($cal->summary ?: config('services.google_calendar.family_calendar_summary', 'Famille — Calendrier')),
            'timeZone' => (string) ($cal->timezone ?: config('services.google_calendar.default_tz', config('app.timezone', 'UTC'))),
        ];

        $url = self::API_BASE . '/calendars';
        $resp = Http::withToken($token)->timeout(15)->post($url, $payload);

        if ($resp->status() === 401) {
            $account->revoked_at = now();
            $account->save();
            throw new \RuntimeException('Google authorization revoked.');
        }

        if (!$resp->ok()) {
            throw new \RuntimeException('Google calendar creation failed.');
        }

        $data = $resp->json();
        $calendarId = is_array($data) && isset($data['id']) && is_string($data['id']) ? $data['id'] : '';
        if ($calendarId === '') {
            throw new \RuntimeException('Google calendar creation returned invalid payload.');
        }

        $cal->google_calendar_id = $calendarId;
        $cal->save();

        return $cal;
    }

    public function upsertEvent(User $user, Event $event): GoogleEventLink
    {
        $account = $user->googleAccount;
        if (!$account || !$account->isConnected() || !$user->hasGoogleCalendarSyncEnabled()) {
            throw new \RuntimeException('Google calendar sync not enabled.');
        }

        $cal = $this->ensureDedicatedFamilyCalendar($user);
        $calendarId = (string) ($cal->google_calendar_id ?? '');
        if ($calendarId === '') {
            throw new \RuntimeException('Missing dedicated Google calendar id.');
        }

        $token = $this->getValidAccessToken($account);
        $payload = $this->mapEventPayload($event, (string) ($cal->timezone ?: config('services.google_calendar.default_tz', config('app.timezone', 'UTC'))));

        $link = GoogleEventLink::query()->where('event_id', $event->id)->where('user_id', $user->id)->first();
        if ($link) {
            $url = self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($link->google_event_id);
            $resp = Http::withToken($token)->timeout(15)->put($url, $payload);

            \Illuminate\Support\Facades\Log::info('GoogleCalendarClient: event update attempt', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'calendar_id' => $calendarId,
                'google_event_id' => $link->google_event_id,
                'http_status' => $resp->status(),
                'payload' => $payload,
                'response_body' => $resp->body(),
            ]);

            if ($resp->status() === 401) {
                $account->revoked_at = now();
                $account->save();
                throw new \RuntimeException('Google authorization revoked.');
            }

            if ($resp->status() === 404) {
                // Remote was deleted: recreate.
                $link->delete();
                return $this->upsertEvent($user, $event);
            }

            if (!$resp->ok()) {
                throw new \RuntimeException('Google event update failed.');
            }

            $link->google_calendar_id = $calendarId;
            $link->last_pushed_at = now();
            $link->save();
            return $link;
        }

        $url = self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events';
        $resp = Http::withToken($token)->timeout(15)->post($url, $payload);

        \Illuminate\Support\Facades\Log::info('GoogleCalendarClient: event creation attempt', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'calendar_id' => $calendarId,
            'http_status' => $resp->status(),
            'payload' => $payload,
            'response_body' => $resp->body(),
        ]);

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

        return GoogleEventLink::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'google_event_id' => $externalId,
            'google_calendar_id' => $calendarId,
            'last_pushed_at' => now(),
        ]);
    }

    public function deleteEventIfLinked(User $user, Event $event): void
    {
        $account = $user->googleAccount;
        if (!$account || !$account->isConnected()) {
            return;
        }

        $link = GoogleEventLink::query()->where('event_id', $event->id)->where('user_id', $user->id)->first();
        if (!$link) {
            return;
        }

        $calendarId = (string) ($link->google_calendar_id ?: optional($user->googleCalendar)->google_calendar_id);
        if ($calendarId === '') {
            $link->delete();
            return;
        }

        $token = $this->getValidAccessToken($account);

        $url = self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($link->google_event_id);
        $resp = Http::withToken($token)->timeout(15)->delete($url);

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

    public function deleteDedicatedCalendar(User $user): void
    {
        $account = $user->googleAccount;
        $cal = $user->googleCalendar;

        if (!$account || !$account->isConnected() || !$cal || !is_string($cal->google_calendar_id) || $cal->google_calendar_id === '') {
            return;
        }

        $token = $this->getValidAccessToken($account);
        $url = self::API_BASE . '/calendars/' . rawurlencode($cal->google_calendar_id);
        $resp = Http::withToken($token)->timeout(15)->delete($url);

        if ($resp->status() === 404) {
            return;
        }

        if ($resp->status() === 401) {
            $account->revoked_at = now();
            $account->save();
            throw new \RuntimeException('Google authorization revoked.');
        }

        if (!$resp->successful()) {
            throw new \RuntimeException('Google calendar deletion failed.');
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function mapEventPayload(Event $event, string $defaultTz): array
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

        $tz = (string) ($event->timezone ?: $defaultTz ?: config('app.timezone', 'UTC'));
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
