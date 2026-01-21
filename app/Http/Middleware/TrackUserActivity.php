<?php

namespace App\Http\Middleware;

use App\Models\ActivityEvent;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            $user = $request->user();
            if (!$user) {
                return $response;
            }

            // Skip noisy routes.
            $path = '/' . ltrim((string) $request->path(), '/');
            if (str_starts_with($path, '/build/') || str_starts_with($path, '/storage/')) {
                return $response;
            }
            if (in_array($path, ['/up'], true)) {
                return $response;
            }

            $now = CarbonImmutable::now();

            // Throttle last_seen writes.
            $prev = $user->last_seen_at ? CarbonImmutable::parse($user->last_seen_at) : null;
            if ($prev === null || $prev->diffInSeconds($now) >= 90) {
                $user->forceFill(['last_seen_at' => $now])->save();
            }

            // Log page views (GET only) with optional sampling.
            if ($request->isMethod('GET')) {
                $sample = (float) (config('ops.activity.page_view_sample', env('ACTIVITY_PAGE_VIEW_SAMPLE', 1)));
                if ($sample >= 1 || (mt_rand() / mt_getrandmax()) <= max(0.0, min(1.0, $sample))) {
                    ActivityEvent::create([
                        'created_at' => $now,
                        'user_id' => $user->id,
                        'type' => 'page_view',
                        'route' => $path,
                        'metadata' => [
                            'name' => $request->route()?->getName(),
                        ],
                    ]);
                }
            }
        } catch (\Throwable) {
            // Never break request flow.
        }

        return $response;
    }
}
