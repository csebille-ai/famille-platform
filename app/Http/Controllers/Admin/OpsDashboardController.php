<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\AppError;
use App\Models\ChatMessage;
use App\Models\CloudAuditLog;
use App\Models\NewsItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OpsDashboardController extends Controller
{
    public function overview(Request $request): View
    {
        Gate::authorize('manage-users');

        $since24h = CarbonImmutable::now()->subHours(24);

        $kpis = [
            'active_24h' => User::query()->where('is_active', true)->where('last_seen_at', '>=', $since24h)->count(),
            'logins_24h' => ActivityEvent::query()->where('type', 'login')->where('created_at', '>=', $since24h)->count(),
            'actions_24h' => ActivityEvent::query()->where('created_at', '>=', $since24h)->count(),
            'uploads_24h' => CloudAuditLog::query()->where('action', 'upload_file')->where('created_at', '>=', $since24h)->count(),
            'chat_messages_24h' => ChatMessage::query()->where('created_at', '>=', $since24h)->count(),
            'errors_24h' => AppError::query()->whereIn('level', ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])->where('created_at', '>=', $since24h)->count(),
        ];

        $latestEvents = ActivityEvent::query()
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $latestErrors = AppError::query()
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $news = [
            'sources_enabled' => 0,
            'latest_fetched_at' => null,
            'latest_published_at' => null,
            'count' => 0,
            'import_last_started_at' => null,
            'import_last_finished_at' => null,
            'import_last_status' => null,
            'import_last_total' => null,
            'scheduler_heartbeat_at' => null,
        ];
        try {
            $sources = (array) config('news.sources', []);
            $sources = array_values(array_filter($sources, fn ($v) => is_array($v)));
            $news['sources_enabled'] = count(array_filter($sources, fn ($s) => ($s['enabled'] ?? true) === true));

            $heartbeat = Cache::get('ops.scheduler.heartbeat_at');
            if (is_string($heartbeat) && $heartbeat !== '') {
                $news['scheduler_heartbeat_at'] = CarbonImmutable::parse($heartbeat);
            }

            $started = Cache::get('ops.news_import.last_started_at');
            if (is_string($started) && $started !== '') {
                $news['import_last_started_at'] = CarbonImmutable::parse($started);
            }

            $finished = Cache::get('ops.news_import.last_finished_at');
            if (is_string($finished) && $finished !== '') {
                $news['import_last_finished_at'] = CarbonImmutable::parse($finished);
            }

            $status = Cache::get('ops.news_import.last_status');
            if (is_string($status) && $status !== '') {
                $news['import_last_status'] = $status;
            }

            $total = Cache::get('ops.news_import.last_total');
            if (is_int($total) || is_float($total) || (is_string($total) && $total !== '' && is_numeric($total))) {
                $news['import_last_total'] = (int) $total;
            }

            if (Schema::hasTable('news_items')) {
                $latest = NewsItem::query()
                    ->orderByDesc('fetched_at')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->first(['fetched_at', 'published_at']);

                $news['latest_fetched_at'] = $latest?->fetched_at;
                $news['latest_published_at'] = $latest?->published_at;
                $news['count'] = NewsItem::query()->count();
            }
        } catch (\Throwable) {
            // ignore
        }

        return view('admin.ops.overview', [
            'kpis' => $kpis,
            'latestEvents' => $latestEvents,
            'latestErrors' => $latestErrors,
            'news' => $news,
        ]);
    }

    public function activity(Request $request): View
    {
        Gate::authorize('manage-users');

        $period = (string) $request->query('period', '24h');
        $type = trim((string) $request->query('type', ''));
        $userId = (int) $request->query('user_id', 0);

        $since = match ($period) {
            '7d' => CarbonImmutable::now()->subDays(7),
            '30d' => CarbonImmutable::now()->subDays(30),
            default => CarbonImmutable::now()->subHours(24),
        };

        $q = ActivityEvent::query()
            ->with(['user:id,name'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at');

        if ($type !== '') {
            $q->where('type', $type);
        }
        if ($userId > 0) {
            $q->where('user_id', $userId);
        }

        $events = $q->paginate(50)->withQueryString();

        $users = User::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        $types = ActivityEvent::query()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->limit(200)
            ->pluck('type')
            ->all();

        return view('admin.ops.activity', [
            'events' => $events,
            'users' => $users,
            'types' => $types,
            'filters' => [
                'period' => $period,
                'type' => $type,
                'user_id' => $userId,
            ],
        ]);
    }

    public function errors(Request $request): View
    {
        Gate::authorize('manage-users');

        $period = (string) $request->query('period', '24h');
        $level = trim((string) $request->query('level', ''));

        $since = match ($period) {
            '7d' => CarbonImmutable::now()->subDays(7),
            '30d' => CarbonImmutable::now()->subDays(30),
            default => CarbonImmutable::now()->subHours(24),
        };

        $q = AppError::query()
            ->with(['user:id,name'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at');

        if ($level !== '') {
            $q->where('level', $level);
        }

        $errors = $q->paginate(50)->withQueryString();

        $levels = AppError::query()
            ->select('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level')
            ->all();

        return view('admin.ops.errors', [
            'errors' => $errors,
            'levels' => $levels,
            'filters' => [
                'period' => $period,
                'level' => $level,
            ],
        ]);
    }
}
