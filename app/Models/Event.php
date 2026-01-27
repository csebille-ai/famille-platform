<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Carbon\CarbonImmutable;

class Event extends Model
{
    protected $fillable = [
        'created_by_user_id',
        'title',
        'description',
        'location',
        'location_label',
        'location_lat',
        'location_lon',
        'household_key',
        'start_at',
        'end_at',
        'all_day',
        'timezone',
        'visibility',
        'is_important',
        'category',
        'color_tag',
        'notify',
        'reminder_minutes',
        'reminder_at',
        'reminded_at',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'all_day' => 'boolean',
        'is_important' => 'boolean',
        'notify' => 'boolean',
        'reminder_minutes' => 'integer',
        'reminder_at' => 'datetime',
        'reminded_at' => 'datetime',
        'location_lat' => 'float',
        'location_lon' => 'float',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isPrivate(): bool
    {
        return ($this->visibility ?? 'family') === 'private';
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $isAdmin = Gate::forUser($user)->allows('manage-users');
        if ($isAdmin) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', '=', 'family')
                ->orWhere(function (Builder $q2) use ($user) {
                    $q2->where('visibility', '=', 'private')
                        ->where('created_by_user_id', '=', $user->id);
                });
        });
    }

    public function googleCalendarUrl(): string
    {
        $title = trim((string) ($this->title ?? ''));
        $title = $title !== '' ? $title : 'Événement';

        [$startUtc, $endUtc] = $this->calendarUtcRange();

        $detailsParts = [];
        $desc = trim((string) ($this->description ?? ''));
        if ($desc !== '') {
            $detailsParts[] = $desc;
        }
        $detailsParts[] = $this->eventUrl();

        $params = [
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => $startUtc->format('Ymd\\THis\\Z') . '/' . $endUtc->format('Ymd\\THis\\Z'),
            'details' => implode("\n\n", $detailsParts),
        ];

        $location = trim((string) ($this->location ?? ''));
        if ($location !== '') {
            $params['location'] = $location;
        }

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }

    public function outlookCalendarUrl(): string
    {
        $title = trim((string) ($this->title ?? ''));
        $title = $title !== '' ? $title : 'Événement';

        [$startUtc, $endUtc] = $this->calendarUtcRange();

        $bodyParts = [];
        $desc = trim((string) ($this->description ?? ''));
        if ($desc !== '') {
            $bodyParts[] = $desc;
        }
        $bodyParts[] = $this->eventUrl();

        $params = [
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => $title,
            'body' => implode("\n\n", $bodyParts),
            'startdt' => $startUtc->toIso8601String(),
            'enddt' => $endUtc->toIso8601String(),
        ];

        $location = trim((string) ($this->location ?? ''));
        if ($location !== '') {
            $params['location'] = $location;
        }

        return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($params);
    }

    private function eventUrl(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        return $base . '/events/' . $this->id;
    }

    /**
     * Return a UTC start/end range suitable for deep-link calendar URLs.
     * Rule for all-day events: stable 09:00–10:00 local time (converted to UTC).
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function calendarUtcRange(): array
    {
        $tz = (string) ($this->timezone ?: config('app.timezone', 'UTC'));

        $start = $this->start_at ? CarbonImmutable::instance($this->start_at)->timezone($tz) : CarbonImmutable::now($tz);
        $end = $this->end_at ? CarbonImmutable::instance($this->end_at)->timezone($tz) : null;

        if ((bool) $this->all_day) {
            $startLocal = $start->setTime(9, 0);
            $endLocal = $start->setTime(10, 0);
            return [$startLocal->utc(), $endLocal->utc()];
        }

        $startUtc = $start->utc();
        $endUtc = $end ? $end->utc() : $startUtc->addMinutes(60);
        if ($endUtc->lessThan($startUtc)) {
            $endUtc = $startUtc->addMinutes(60);
        }
        return [$startUtc, $endUtc];
    }
}
