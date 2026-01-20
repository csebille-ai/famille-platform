<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\Google\GoogleCalendarClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleCalendarEventController extends Controller
{
    public function add(Request $request, Event $event, GoogleCalendarClient $client): RedirectResponse
    {
        $this->authorize('view', $event);

        /** @var User $user */
        $user = $request->user();

        try {
            $client->createOrUpdateEvent($user, $event);
            return redirect()->route('events.show', $event)->with('status', 'google-calendar-event-added');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('events.show', $event)->with('status', 'google-calendar-event-error');
        }
    }

    public function remove(Request $request, Event $event, GoogleCalendarClient $client): RedirectResponse
    {
        $this->authorize('view', $event);

        /** @var User $user */
        $user = $request->user();

        try {
            $client->removeEvent($user, $event);
            return redirect()->route('events.show', $event)->with('status', 'google-calendar-event-removed');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('events.show', $event)->with('status', 'google-calendar-event-error');
        }
    }

}
