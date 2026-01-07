<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $userId = Auth::id();
        if ($userId === null) {
            abort(403);
        }

        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
            'userAgent' => ['nullable', 'string', 'max:255'],
        ]);

        $endpoint = (string) $validated['endpoint'];
        $p256dh = (string) $validated['keys']['p256dh'];
        $auth = (string) $validated['keys']['auth'];
        $contentEncoding = isset($validated['contentEncoding']) ? (string) $validated['contentEncoding'] : null;
        $userAgent = isset($validated['userAgent']) ? (string) $validated['userAgent'] : null;

        PushSubscription::updateOrCreate(
            ['endpoint' => $endpoint],
            [
                'user_id' => $userId,
                'public_key' => $p256dh,
                'auth_token' => $auth,
                'content_encoding' => $contentEncoding,
                'user_agent' => $userAgent,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        $userId = Auth::id();
        if ($userId === null) {
            abort(403);
        }

        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $endpoint = (string) $validated['endpoint'];

        PushSubscription::query()
            ->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
