<?php

namespace App\Http\Controllers;

use App\Models\CalendarSubscription;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CalendarFamilyController extends Controller
{
    public function subscribe(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $sub = CalendarSubscription::query()->where('user_id', $user->id)->first();
        if (!$sub) {
            $sub = new CalendarSubscription();
            $sub->user_id = $user->id;
        }

        // If re-enabling, rotate the token.
        $needsRotate = !$sub->exists || !(bool) $sub->is_enabled;
        if ($needsRotate) {
            $sub->token = self::generateToken();
        }

        $sub->is_enabled = true;
        $sub->revoked_at = null;
        $sub->save();

        return redirect()->route('events.index')->with('status', 'Calendrier Famille activé.');
    }

    public function unsubscribe(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $sub = CalendarSubscription::query()->where('user_id', $user->id)->first();
        if (!$sub) {
            return redirect()->route('events.index');
        }

        // Disable + rotate token for immediate revocation.
        $sub->is_enabled = false;
        $sub->revoked_at = now();
        $sub->token = self::generateToken();
        $sub->save();

        return redirect()->route('events.index')->with('status', 'Calendrier Famille désactivé.');
    }

    public function feed(Request $request, string $token): Response
    {
        $token = trim($token);
        if ($token === '') {
            abort(404);
        }

        /** @var CalendarSubscription|null $sub */
        $sub = CalendarSubscription::query()
            ->where('token', $token)
            ->where('is_enabled', true)
            ->first();

        if (!$sub) {
            abort(404);
        }

        /** @var User|null $user */
        $user = User::query()->find($sub->user_id);
        if (!$user) {
            abort(404);
        }

        $events = Event::query()
            ->visibleTo($user)
            ->whereIn('status', ['active', 'cancelled'])
            ->orderBy('start_at')
            ->limit(500)
            ->get();

        // Mark as used without touching updated_at (avoid noisy ETag changes).
        try {
            DB::table('calendar_subscriptions')->where('id', $sub->id)->update([
                'last_used_at' => now(),
            ]);
        } catch (\Throwable) {
            // ignore
        }

        $host = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: $request->getHost() ?: 'famille');
        $nowUtc = CarbonImmutable::now('UTC');

        $lastModified = $events->max(fn ($e) => optional($e->updated_at)->getTimestamp() ?: 0);
        $etagSeed = $sub->id . '|' . $sub->token . '|' . (string) $lastModified;
        $etag = '"' . sha1($etagSeed) . '"';

        $ifNoneMatch = (string) $request->headers->get('If-None-Match', '');
        if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=300')
                ->header('Content-Type', 'text/calendar; charset=utf-8');
        }

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//Famille//Calendrier Famille//FR';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:' . self::icsEscape('Calendrier Famille');
        $lines[] = 'X-WR-TIMEZONE:UTC';

        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . self::icsEscape('event-' . $event->id . '@' . $host);
            $lines[] = 'DTSTAMP:' . $nowUtc->format('Ymd\\THis\\Z');

            $lm = $event->updated_at ? CarbonImmutable::instance($event->updated_at)->utc() : $nowUtc;
            $lines[] = 'LAST-MODIFIED:' . $lm->format('Ymd\\THis\\Z');

            $status = (($event->status ?? 'active') === 'cancelled') ? 'CANCELLED' : 'CONFIRMED';
            $lines[] = 'STATUS:' . $status;

            $summary = trim((string) ($event->title ?? ''));
            $lines[] = 'SUMMARY:' . self::icsEscape($summary !== '' ? $summary : 'Événement');

            $location = trim((string) ($event->location ?? ''));
            if ($location !== '') {
                $lines[] = 'LOCATION:' . self::icsEscape($location);
            }

            $eventUrl = rtrim((string) config('app.url'), '/') . '/events/' . $event->id;
            $descParts = [];
            $desc = trim((string) ($event->description ?? ''));
            if ($desc !== '') {
                $descParts[] = $desc;
            }
            $descParts[] = $eventUrl;
            $lines[] = 'DESCRIPTION:' . self::icsEscape(implode("\n\n", $descParts));
            $lines[] = 'URL:' . self::icsEscape($eventUrl);

            [$dtStartLine, $dtEndLine] = self::buildDtLines($event);
            $lines[] = $dtStartLine;
            $lines[] = $dtEndLine;

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $body = self::icsFoldLines($lines);

        return response($body, 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Cache-Control', 'private, max-age=300')
            ->header('ETag', $etag);
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function buildDtLines(Event $event): array
    {
        $tz = (string) ($event->timezone ?: config('app.timezone', 'UTC'));

        $start = $event->start_at ? CarbonImmutable::instance($event->start_at)->timezone($tz) : null;
        $end = $event->end_at ? CarbonImmutable::instance($event->end_at)->timezone($tz) : null;

        if (!$start) {
            $start = CarbonImmutable::now($tz);
        }

        if ((bool) $event->all_day) {
            $startDate = $start->toDateString();
            $endDate = $end ? $end->toDateString() : $startDate;

            // DTEND is exclusive for all-day events.
            $exclusiveEnd = CarbonImmutable::parse($endDate, $tz)->addDay();

            $dtStart = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $startDate);
            $dtEnd = 'DTEND;VALUE=DATE:' . $exclusiveEnd->format('Ymd');
            return [$dtStart, $dtEnd];
        }

        // Timed events: UTC Z.
        $startUtc = $start->utc();
        $endUtc = $end ? $end->utc() : $startUtc->addMinutes(60);

        if ($endUtc->lessThan($startUtc)) {
            $endUtc = $startUtc->addMinutes(60);
        }

        $dtStart = 'DTSTART:' . $startUtc->format('Ymd\\THis\\Z');
        $dtEnd = 'DTEND:' . $endUtc->format('Ymd\\THis\\Z');
        return [$dtStart, $dtEnd];
    }

    private static function generateToken(): string
    {
        // >= 32 bytes of entropy.
        return Str::random(64);
    }

    private static function icsEscape(string $value): string
    {
        $v = str_replace('\\', '\\\\', $value);
        $v = str_replace("\r\n", "\n", $v);
        $v = str_replace("\r", "\n", $v);
        $v = str_replace("\n", "\\n", $v);
        $v = str_replace(';', '\\;', $v);
        $v = str_replace(',', '\\,', $v);
        return $v;
    }

    /**
     * Fold iCalendar lines at ~75 octets (simple best-effort).
     *
     * @param list<string> $lines
     */
    private static function icsFoldLines(array $lines): string
    {
        $out = [];
        foreach ($lines as $line) {
            $line = (string) $line;
            if ($line === '') {
                $out[] = '';
                continue;
            }

            // Best-effort folding by characters (not exact bytes), acceptable for UTF-8 here.
            while (mb_strlen($line, 'UTF-8') > 75) {
                $chunk = mb_substr($line, 0, 75, 'UTF-8');
                $out[] = $chunk;
                $line = ' ' . mb_substr($line, 75, null, 'UTF-8');
            }
            $out[] = $line;
        }

        return implode("\r\n", $out) . "\r\n";
    }
}
