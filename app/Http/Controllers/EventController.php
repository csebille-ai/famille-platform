<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $scope = strtolower(trim((string) $request->query('scope', 'upcoming')));
        if (!in_array($scope, ['upcoming', 'past'], true)) {
            $scope = 'upcoming';
        }

        $filter = strtolower(trim((string) $request->query('filter', 'all')));
        if (!in_array($filter, ['all', 'important', 'family', 'personal', 'mine'], true)) {
            $filter = 'all';
        }

        $now = CarbonImmutable::now(config('app.timezone', 'UTC'));

        $q = Event::query()
            ->visibleTo($user)
            ->whereIn('status', ['active', 'cancelled']);

        if ($scope === 'upcoming') {
            $q->where('start_at', '>=', $now->subHours(2));
            $q->orderByDesc('is_important')->orderBy('start_at');
        } else {
            $q->where('start_at', '<', $now->subHours(2));
            $q->orderByDesc('start_at');
        }

        if ($filter === 'important') {
            $q->where('is_important', true);
        } elseif ($filter === 'family') {
            $q->where('visibility', 'family');
        } elseif ($filter === 'personal') {
            $q->where('category', 'personal');
        } elseif ($filter === 'mine') {
            $q->where('created_by_user_id', $user->id);
        }

        $events = $q->paginate(20)->withQueryString();

        return view('events.index', compact('events', 'scope', 'filter'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Event::class);

        $defaults = [
            'visibility' => 'family',
            'category' => 'family',
            'is_important' => false,
            'notify' => true,
            'reminder_minutes' => 0,
            'all_day' => false,
        ];

        return view('events.create', ['defaults' => $defaults]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $this->authorize('create', Event::class);

        $data = $request->validated();

        $event = new Event();
        $event->created_by_user_id = $request->user()->id;
        $this->fillEventFromForm($event, $data);
        $event->status = 'active';
        $event->save();

        return redirect()->route('events.show', $event)->with('status', 'Événement créé.');
    }

    public function show(Request $request, Event $event): View
    {
        $this->authorize('view', $event);

        return view('events.show', ['event' => $event]);
    }

    public function edit(Request $request, Event $event): View
    {
        $this->authorize('update', $event);

        return view('events.edit', ['event' => $event]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $data = $request->validated();
        $this->fillEventFromForm($event, $data);

        $isAdmin = Gate::forUser($request->user())->allows('manage-users');
        if ($isAdmin && isset($data['status']) && is_string($data['status'])) {
            $event->status = $data['status'];
        }

        $event->save();

        return redirect()->route('events.show', $event)->with('status', 'Événement mis à jour.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return redirect()->route('events.index')->with('status', 'Événement supprimé.');
    }

    /**
     * @param array<string,mixed> $data
     */
    private function fillEventFromForm(Event $event, array $data): void
    {
        $tz = (string) config('app.timezone', 'UTC');

        $allDay = (bool) ($data['all_day'] ?? false);
        $date = (string) ($data['date'] ?? '');
        $time = (string) ($data['time'] ?? '');

        $startTime = $allDay ? '00:00' : ($time !== '' ? $time : '00:00');
        $startAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $startTime, $tz);

        $endAt = null;
        $addEnd = (bool) ($data['add_end'] ?? false);
        if ($addEnd) {
            $endDate = (string) ($data['end_date'] ?? $date);
            $endTime = (string) ($data['end_time'] ?? ($allDay ? '23:59' : $startTime));
            $endAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $endDate . ' ' . $endTime, $tz);
            if ($endAt->lessThan($startAt)) {
                $endAt = $startAt;
            }
        } elseif ($allDay) {
            // Optional: keep end_at empty; UI can treat all_day as a single-day event.
            $endAt = null;
        }

        $event->title = (string) ($data['title'] ?? '');
        $event->description = ($data['description'] ?? null) !== null ? (string) $data['description'] : null;
        $event->location = ($data['location'] ?? null) !== null ? (string) $data['location'] : null;
        $event->category = ($data['category'] ?? null) !== null ? (string) $data['category'] : null;
        $event->visibility = (string) ($data['visibility'] ?? 'family');
        $event->is_important = (bool) ($data['is_important'] ?? false);

        $event->all_day = $allDay;
        $event->timezone = $tz;
        $event->start_at = $startAt;
        $event->end_at = $endAt;

        $notify = (bool) ($data['notify'] ?? true);
        $event->notify = $notify;
        $event->reminder_minutes = $notify ? (isset($data['reminder_minutes']) ? (int) $data['reminder_minutes'] : 0) : null;

        if ($notify) {
            $minutes = $event->reminder_minutes;
            $event->reminder_at = $minutes !== null ? $startAt->subMinutes($minutes) : $startAt;
        } else {
            $event->reminder_at = null;
        }

        // Reset reminder status whenever timing settings change.
        $event->reminded_at = null;

        // Auto-derive color tag if not set.
        if (empty($event->color_tag)) {
            $event->color_tag = match ($event->category) {
                'family' => 'teal',
                'personal' => 'coral',
                'school' => 'sky',
                'travel' => 'amber',
                'medical' => 'rose',
                'admin' => 'slate',
                default => 'teal',
            };
        }
    }
}
