<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CloudNodeController;
use App\Http\Controllers\AstroProfileController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PlaylistItemController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TarotController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\Api\TarotDrawController;
use App\Http\Controllers\Api\TarotTtsController;
use App\Http\Controllers\Api\NewsIndexController;
use App\Http\Controllers\Api\ChatAttachmentController;
use App\Http\Controllers\Api\UploadsController;
use App\Models\CloudNode;
use App\Models\ChatMessage;
use App\Models\Event;
use App\Models\NewsItem;
use App\Models\Person;
use App\Models\User;
use App\Models\Video;
use App\Services\NextBirthday;
use App\Services\Uploads\R2UploadService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

Route::get('/api/geo/cities', \App\Http\Controllers\Api\GeoCitySearchController::class)
    ->middleware(['auth', 'verified', 'throttle:60,1']);

Route::post('/api/tarot/draw', TarotDrawController::class)
    ->middleware('throttle:tarot-draw')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/chat/{thread}/attachments', ChatAttachmentController::class)
    ->middleware(['auth', 'verified', 'throttle:30,1']);

Route::get('/api/uploads/quota', [UploadsController::class, 'quota'])
    ->middleware(['auth', 'verified', 'throttle:30,1']);

Route::post('/api/uploads/presign', [UploadsController::class, 'presign'])
    ->middleware(['auth', 'verified', 'throttle:30,1']);

Route::put('/api/uploads/local/put', [UploadsController::class, 'localPut'])
    ->middleware(['auth', 'verified', 'throttle:30,1'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/api/uploads/multipart/init', [UploadsController::class, 'multipartInit'])
    ->middleware(['auth', 'verified', 'throttle:30,1']);

Route::post('/api/uploads/multipart/complete', [UploadsController::class, 'multipartComplete'])
    ->middleware(['auth', 'verified', 'throttle:30,1']);

Route::post('/api/uploads/finalize', [UploadsController::class, 'finalize'])
    ->middleware(['auth', 'verified', 'throttle:30,1']);

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/famille', [FamilyController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('family.index');

Route::get('/famille/enfants/creer', [FamilyController::class, 'createChild'])
    ->middleware(['auth', 'verified'])
    ->name('family.children.create');

Route::post('/famille/enfants', [FamilyController::class, 'storeChild'])
    ->middleware(['auth', 'verified'])
    ->name('family.children.store');

Route::get('/famille/enfants/{person}/modifier', [FamilyController::class, 'editChild'])
    ->middleware(['auth', 'verified'])
    ->name('family.children.edit');

Route::patch('/famille/enfants/{person}', [FamilyController::class, 'updateChild'])
    ->middleware(['auth', 'verified'])
    ->name('family.children.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/creer', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/modifier', [EventController::class, 'edit'])->name('events.edit');
    Route::patch('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
});

Route::get('/ephemeride', function () {
    return view('ephemeris.show');
})->middleware(['auth', 'verified'])->name('ephemeris.show');

// Home (mobile-first). Keep route name 'dashboard' for backward compatibility.
Route::get('/home', function () {
    $feed = (string) request()->query('feed', 'all');

    $prettyTitle = function (?string $raw, string $fallback): string {
        $name = trim((string) $raw);
        if ($name === '') return $fallback;

        // Avoid surfacing raw filenames (e.g. IMG_1234.JPG, 20240101_120000.mp4).
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $looksLikeFilename = ($ext !== '' && strpos($name, ' ') === false);
        if ($looksLikeFilename) return $fallback;

        // Also avoid extremely long machine-ish names.
        if (mb_strlen($name) > 80) return $fallback;

        return $name;
    };

    $latestImages = collect();
    try {
        if (Schema::hasTable('cloud_nodes')) {
            $latestImages = CloudNode::query()
                ->with('uploader:id,name')
                ->where('type', 'file')
                ->whereNotNull('stored_path')
                ->where('mime', 'like', 'image/%')
                ->latest()
                ->limit(6)
                ->get();
        }
    } catch (Throwable $e) {
        $latestImages = collect();
    }

    $latestVideos = collect();
    try {
        if (Schema::hasTable('videos')) {
            // Home: show only "perso" videos (exclude Médiathèque films/séries).
            // Convention: Médiathèque uses category=films|series. Personal uploads use category=docs or NULL.
            $latestVideos = Video::query()
                ->with('creator:id,name')
                ->where(function ($q) {
                    $q->whereNull('category')
                        ->orWhere('category', '')
                        ->orWhere('category', 'docs');
                })
                ->latest()
                ->limit(6)
                ->get();
        }
    } catch (Throwable $e) {
        $latestVideos = collect();
    }

    // Docs feed removed.

    $todayNewsItem = null;
    try {
        if (Schema::hasTable('news_items')) {
            $todayNewsItem = NewsItem::query()
                ->orderByDesc('published_at')
                ->orderByDesc('fetched_at')
                ->orderByDesc('id')
                ->first();
        }
    } catch (Throwable $e) {
        $todayNewsItem = null;
    }

    $freshNewsAt = null;
    if ($todayNewsItem) {
        $freshNewsAt = $todayNewsItem->published_at
            ?: $todayNewsItem->fetched_at
            ?: $todayNewsItem->created_at;
    }
    $hasNewActu = false;
    if ($freshNewsAt) {
        $hasNewActu = $freshNewsAt->greaterThanOrEqualTo(now()->subHours(12));
    }
    session()->put('news.has_new', $hasNewActu);

    // Home is now “useful-first”: upcoming birthday + family activity + recent photos/videos.
    // Keep this scope minimal and cheap to compute.
    $familyActivity = [];
    try {
        $cutoff = now()->subHours(36);
        $items = collect();

        $titleCase = function (string $name): string {
            $name = trim($name);
            if ($name === '') return '';
            if (function_exists('mb_convert_case')) {
                return (string) mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
            }
            $lower = strtolower($name);
            return ucfirst($lower);
        };

        // 1) Recent chat messages.
        try {
            if (Schema::hasTable('chat_messages')) {
                $recentCount = (int) ChatMessage::query()
                    ->where('created_at', '>=', $cutoff)
                    ->count();

                if ($recentCount > 0) {
                    $last = ChatMessage::query()
                        ->with('user:id,name')
                        ->latest('created_at')
                        ->first(['id', 'user_id', 'body', 'created_at']);

                    $who = $last?->user?->name ? (string) $last->user->name : 'Quelqu’un';
                    $who = trim(explode(' ', trim($who))[0] ?? $who);
                    if ($who !== '' && $who !== 'Quelqu’un') {
                        $who = $titleCase($who);
                    }

                    $sentence = $who . ' a envoyé un message';
                    if ($recentCount > 1) {
                        $sentence .= ' (+' . ($recentCount - 1) . ')';
                    }

                    $items->push([
                        'kind' => 'chat',
                        'at' => $last?->created_at ?: now(),
                        'sentence' => $sentence,
                        'href' => route('chat.index'),
                    ]);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // 2) Upcoming event/announcement.
        try {
            if (Schema::hasTable('events')) {
                $today = now();
                $user = auth()->user();

                $hasStartAt = Schema::hasColumn('events', 'start_at');
                $hasStartsOn = Schema::hasColumn('events', 'starts_on');

                $next = Event::query();
                if ($user) {
                    $next = $next->visibleTo($user);
                }

                if (Schema::hasColumn('events', 'status')) {
                    $next = $next->where('status', 'active');
                }

                if ($hasStartAt) {
                    $next = $next
                        ->where('start_at', '>=', $today->copy()->subMinutes(1))
                        ->orderBy('start_at');
                } elseif ($hasStartsOn) {
                    $next = $next
                        ->whereDate('starts_on', '>=', $today->toDateString())
                        ->orderBy('starts_on');
                } else {
                    $next = null;
                }

                $next = $next ? $next->first() : null;
                if ($next) {
                    $at = $hasStartAt ? $next->start_at : $next->starts_on;
                    $items->push([
                        'kind' => 'event',
                        'at' => $at,
                        'sentence' => 'Événement : ' . (string) $next->title,
                        'href' => route('events.show', $next),
                    ]);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // 3) Actu refresh signal (if we have something fresh).
        if ($freshNewsAt && $freshNewsAt->greaterThanOrEqualTo(now()->subDays(3))) {
            $headline = null;
            try {
                $headline = $todayNewsItem?->title ? (string) $todayNewsItem->title : null;
                if (is_string($headline)) {
                    $headline = trim($headline);
                    if ($headline === '') $headline = null;
                }
            } catch (Throwable $e) {
                $headline = null;
            }

            $items->push([
                'kind' => 'actu',
                'at' => $freshNewsAt,
                'sentence' => $headline ? ('Actu : ' . $headline) : 'Actu famille : mise à jour récente',
                'href' => route('actu.index'),
            ]);
        }

        $familyActivity = $items
            ->sortByDesc('at')
            ->take(3)
            ->values()
            ->map(fn ($i) => [
                'kind' => (string) ($i['kind'] ?? 'item'),
                'at' => $i['at'] ?? null,
                'sentence' => (string) ($i['sentence'] ?? ''),
                'href' => (string) ($i['href'] ?? '#'),
            ])
            ->all();
    } catch (Throwable $e) {
        $familyActivity = [];
    }

    $latestAdds = collect()
        ->merge($latestImages->map(fn ($img) => [
            'key' => 'image:' . (int) $img->id,
            'type' => 'image',
            'title' => $prettyTitle((string) ($img->name ?? ''), 'Photo'),
            'by' => $img->uploader?->name ?? 'Quelqu’un',
            'at' => $img->created_at,
            'href' => route('media.photos.show', ['node' => $img, 'return' => request()->getRequestUri()]),
            'thumb_url' => route('images.view', $img),
        ]))
        ->merge($latestVideos->map(function ($v) use ($prettyTitle) {
            $durationSeconds = null;
            if (Schema::hasColumn('videos', 'duration_seconds')) {
                $durationSeconds = (int) ($v->duration_seconds ?? 0);
                if ($durationSeconds <= 0) $durationSeconds = null;
            }

            return [
                'key' => 'video:' . (int) $v->id,
                'type' => 'video',
                'title' => $prettyTitle((string) ($v->title ?? ''), 'Vidéo'),
                'by' => $v->creator?->name ?? 'Quelqu’un',
                'at' => $v->created_at,
                'href' => route('videos.show', $v),
                'poster_url' => $v->video_path ? route('videos.poster', $v) : null,
                'duration_seconds' => $durationSeconds,
            ];
        }))
        ->filter(fn ($x) => !empty($x['at']))
        ->sortByDesc('at')
        ->values();

    if ($feed === 'photos') {
        $latestAdds = $latestAdds->where('type', 'image')->values();
    } elseif ($feed === 'videos') {
        $latestAdds = $latestAdds->where('type', 'video')->values();
    } else {
        $feed = 'all';
    }

    $chatOnlineCount = 0;
    try {
        if (Schema::hasTable('chat_presences')) {
            $chatOnlineCount = (int) DB::table('chat_presences')
                ->where('last_seen_at', '>=', now()->subSeconds(45))
                ->count();
        }
    } catch (Throwable $e) {
        $chatOnlineCount = 0;
    }

    $todayBirthdays = [];
    $upcomingBirthdays = [];
    try {
        if (Schema::hasTable('people') && Schema::hasColumn('people', 'birth_date')) {
            $peopleWithDob = Person::query()
                ->whereNotNull('birth_date')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'user_id', 'first_name', 'last_name', 'birth_date', 'is_child', 'avatar_path']);

            $usersById = null;
            try {
                $userIds = $peopleWithDob->pluck('user_id')->filter()->unique()->values();
                if ($userIds->isNotEmpty() && Schema::hasTable('users')) {
                    $uCols = ['id'];
                    if (Schema::hasColumn('users', 'avatar_image_url')) {
                        $uCols[] = 'avatar_image_url';
                    }
                    if (Schema::hasColumn('users', 'avatar_updated_at')) {
                        $uCols[] = 'avatar_updated_at';
                    }
                    if (Schema::hasColumn('users', 'avatar_astro_status')) {
                        $uCols[] = 'avatar_astro_status';
                    }

                    $usersById = User::query()
                        ->whereIn('id', $userIds)
                        ->get($uCols)
                        ->keyBy('id');
                }
            } catch (Throwable $e) {
                $usersById = null;
            }

            $lists = app(NextBirthday::class)->dashboardForPeople($peopleWithDob, null, 10, $usersById);
            $todayBirthdays = $lists['todayBirthdays'] ?? [];
            $upcomingBirthdays = $lists['upcomingBirthdays'] ?? [];
        } elseif (Schema::hasTable('users') && Schema::hasColumn('users', 'date_of_birth')) {
            $cols = ['id', 'name', 'date_of_birth'];
            if (Schema::hasColumn('users', 'avatar_image_url')) {
                $cols[] = 'avatar_image_url';
            }
            if (Schema::hasColumn('users', 'avatar_updated_at')) {
                $cols[] = 'avatar_updated_at';
            }
            if (Schema::hasColumn('users', 'avatar_astro_status')) {
                $cols[] = 'avatar_astro_status';
            }

            $usersWithDob = User::query()
                ->whereNotNull('date_of_birth')
                ->orderBy('name')
                ->get($cols);

            $lists = app(NextBirthday::class)->dashboardForUsers($usersWithDob, null, 10);
            $todayBirthdays = $lists['todayBirthdays'] ?? [];
            $upcomingBirthdays = $lists['upcomingBirthdays'] ?? [];
        }
    } catch (Throwable $e) {
        $todayBirthdays = [];
        $upcomingBirthdays = [];
    }

    $upcomingEvents = collect();
    try {
        if (Schema::hasTable('events')) {
            $user = auth()->user();
            $hasStartAt = Schema::hasColumn('events', 'start_at');
            $hasStartsOn = Schema::hasColumn('events', 'starts_on');

            $q = Event::query();
            if ($user) {
                $q = $q->visibleTo($user);
            }

            if (Schema::hasColumn('events', 'status')) {
                $q = $q->where('status', 'active');
            }

            $today = now();
            if ($hasStartAt) {
                $q = $q
                    ->whereNotNull('start_at')
                    ->where('start_at', '>=', $today->copy()->startOfDay())
                    ->orderBy('start_at');
            } elseif ($hasStartsOn) {
                $q = $q
                    ->whereDate('starts_on', '>=', $today->toDateString())
                    ->orderBy('starts_on');
            } else {
                $q = null;
            }

            if ($q) {
                $upcomingEvents = $q
                    ->limit(3)
                    ->get();
            }
        }
    } catch (Throwable $e) {
        $upcomingEvents = collect();
    }

    $buildFamilyMoments = function (): array {
        $today = now();

        $pickMemoryPhoto = function () use ($today) {
            try {
                if (!Schema::hasTable('cloud_nodes')) {
                    return null;
                }
                return CloudNode::query()
                    ->with('uploader:id,name')
                    ->where('type', 'file')
                    ->whereNotNull('stored_path')
                    ->where('mime', 'like', 'image/%')
                    ->whereMonth('created_at', $today->month)
                    ->whereDay('created_at', $today->day)
                    ->whereYear('created_at', '!=', $today->year)
                    ->inRandomOrder()
                    ->first();
            } catch (Throwable $e) {
                return null;
            }
        };

        $pickSurprisePhoto = function () {
            try {
                if (!Schema::hasTable('cloud_nodes')) {
                    return null;
                }
                return CloudNode::query()
                    ->with('uploader:id,name')
                    ->where('type', 'file')
                    ->whereNotNull('stored_path')
                    ->where('mime', 'like', 'image/%')
                    ->inRandomOrder()
                    ->first();
            } catch (Throwable $e) {
                return null;
            }
        };

        // 1) Upcoming family event (if close).
        try {
            if (Schema::hasTable('events')) {
                $user = auth()->user();
                $hasStartAt = Schema::hasColumn('events', 'start_at');
                $hasStartsOn = Schema::hasColumn('events', 'starts_on');

                $q = Event::query();
                if ($user) {
                    $q = $q->visibleTo($user);
                }
                if (Schema::hasColumn('events', 'status')) {
                    $q = $q->where('status', 'active');
                }

                if ($hasStartAt) {
                    $q = $q
                        ->whereNotNull('start_at')
                        ->where('start_at', '>=', $today->copy()->startOfDay())
                        ->where('start_at', '<=', $today->copy()->addDays(7)->endOfDay())
                        ->orderBy('start_at');
                } elseif ($hasStartsOn) {
                    $q = $q
                        ->whereDate('starts_on', '>=', $today->toDateString())
                        ->whereDate('starts_on', '<=', $today->copy()->addDays(7)->toDateString())
                        ->orderBy('starts_on');
                } else {
                    $q = null;
                }

                $next = $q ? $q->first() : null;
                if ($next) {
                    $at = $hasStartAt ? $next->start_at : $next->starts_on;
                    $days = $at ? (int) $today->copy()->startOfDay()->diffInDays($at, false) : 0;
                    $when = $days === 0 ? 'aujourd’hui' : ('dans ' . $days . ' jour' . ($days > 1 ? 's' : ''));

                    return [[
                        'kind' => 'event',
                        'title' => $next->title,
                        'text' => 'Événement ' . $when,
                        'image_url' => null,
                        'href' => route('events.show', $next),
                        'cta' => 'Voir',
                    ]];
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // 2) Context of the day (only when it's a special feel).
        $dow = (int) $today->dayOfWeekIso; // 1..7
        $isWeekend = $dow >= 6;
        $isMonthStart = (int) $today->day === 1;
        $month = (int) $today->month;
        $season = match (true) {
            in_array($month, [12, 1, 2], true) => 'hiver',
            in_array($month, [3, 4, 5], true) => 'printemps',
            in_array($month, [6, 7, 8], true) => 'été',
            default => 'automne',
        };

        if ($isWeekend || $isMonthStart) {
            $seed = crc32('context:' . $today->toDateString());
            $messages = $isWeekend
                ? [
                    'Petit moment tranquille: on se fait simple et doux aujourd’hui.',
                    'Week-end mood: une pause, un sourire, et on profite.',
                    'Aujourd’hui on respire: pas besoin d’en faire trop.',
                ]
                : [
                    'Nouveau mois: une petite chose à faire, et c’est déjà bien.',
                    'Début de mois: on se garde un petit cap simple.',
                    'Un mois qui commence: on avance à notre rythme.',
                ];

            $msg = $messages[$seed % count($messages)];
            $photo = $pickSurprisePhoto();

            return [[
                'kind' => 'context',
                'title' => 'Petit moment du ' . $today->translatedFormat('EEEE'),
                'text' => $msg . ' (' . $season . ')',
                'image_url' => $photo ? route('images.view', $photo) : null,
                'href' => $photo ? route('media.photos.show', ['node' => $photo, 'return' => request()->getRequestUri()]) : route('chat.index'),
                'cta' => $photo ? 'Voir' : 'Écrire un mot',
            ]];
        }

        // 3) Simple weather signal via local news (tag meteo).
        try {
            if (Schema::hasTable('news_items')) {
                $weather = NewsItem::query()
                    ->where('tag', 'meteo')
                    ->orderByDesc('published_at')
                    ->orderByDesc('fetched_at')
                    ->first();

                if ($weather) {
                    return [[
                        'kind' => 'weather',
                        'title' => 'Météo du coin',
                        'text' => (string) ($weather->title ?: 'Un petit point météo'),
                        'image_url' => (string) ($weather->image_url ?: ''),
                        'href' => (string) ($weather->url ?: route('actu.index')),
                        'cta' => 'Voir',
                    ]];
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // 4) Memory.
        $memoryPhoto = $pickMemoryPhoto();
        if ($memoryPhoto) {
            $years = $memoryPhoto->created_at ? max(0, (int) $memoryPhoto->created_at->diffInYears($today)) : 0;
            $subtitle = ($years > 0 ? 'Il y a ' . $years . ' an' . ($years > 1 ? 's' : '') . ' aujourd’hui' : 'Un souvenir du jour')
                . ' · ' . ($memoryPhoto->uploader?->name ?: 'Famille');

            return [[
                'kind' => 'memory',
                'title' => 'Souvenir du jour',
                'text' => $subtitle,
                'image_url' => route('images.view', $memoryPhoto),
                'href' => route('media.photos.show', ['node' => $memoryPhoto, 'return' => request()->getRequestUri()]),
                'cta' => 'Voir le souvenir',
            ]];
        }

        // 5) Surprise (tarot preferred if it has a visual).
        $deck = (array) config('tarot.cards', []);
        $seed = crc32('surprise:' . $today->format('Y-m-d-H')); // changes hourly
        if (!empty($deck)) {
            $card = $deck[$seed % count($deck)] ?? null;
            if (is_array($card) && !empty($card['name'])) {
                $tarotImageUrl = null;
                if (!empty($card['file'])) {
                    $tarotImageUrl = 'https://opanoma.fr/tarot/' . ltrim((string) $card['file'], '/');
                }

                $messages = [
                    'Petit clin d’œil du jour: on avance tranquille.',
                    'Une idée simple pour aujourd’hui: faire une chose, pas dix.',
                    'Rappel doux: une pause, et on repart.',
                ];

                return [[
                    'kind' => 'tarot',
                    'title' => 'Carte du moment: ' . (string) $card['name'],
                    'text' => $messages[$seed % count($messages)],
                    'image_url' => $tarotImageUrl,
                    'href' => route('tarot.index'),
                    'cta' => 'Voir',
                ]];
            }
        }

        $photo = $pickSurprisePhoto();
        if ($photo) {
            return [[
                'kind' => 'surprise',
                'title' => 'Photo surprise',
                'text' => 'Un petit clin d’œil au hasard.',
                'image_url' => route('images.view', $photo),
                'href' => route('media.photos.show', ['node' => $photo, 'return' => request()->getRequestUri()]),
                'cta' => 'Voir',
            ]];
        }

        return [];
    };

    $familyMoments = [];
    try {
        $bucket = now()->format('Y-m-d-H');
        $familyMoments = Cache::remember(
            'dashboard.family_moments.' . $bucket,
            now()->addMinutes(65),
            fn () => $buildFamilyMoments()
        );
    } catch (Throwable $e) {
        // If cache is misconfigured/unwritable in production, do not 500 the dashboard.
        $familyMoments = $buildFamilyMoments();
    }

    $response = response()->view('dashboard_v2', [
        'latestImages' => $latestImages,
        'latestVideos' => $latestVideos,
        'todayNewsItem' => $todayNewsItem,
        'chatOnlineCount' => $chatOnlineCount,
        'familyMoments' => $familyMoments,
        'latestAdds' => $latestAdds,
        'feed' => $feed,
        'todayBirthdays' => $todayBirthdays,
        'upcomingBirthdays' => $upcomingBirthdays,
        'familyActivity' => $familyActivity,
        'upcomingEvents' => $upcomingEvents ?? collect(),
    ]);

    // Avoid stale HTML being served by proxies (LiteSpeed) after deploy.
    return $response
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0')
        ->header('X-LiteSpeed-Cache-Control', 'no-cache');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/anniversaires', function () {
    $birthdays = [];

    try {
        if (Schema::hasTable('people') && Schema::hasColumn('people', 'birth_date')) {
            $peopleWithDob = Person::query()
                ->whereNotNull('birth_date')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'birth_date']);

            $birthdays = app(NextBirthday::class)->upcomingForPeople($peopleWithDob);
        } elseif (Schema::hasTable('users') && Schema::hasColumn('users', 'date_of_birth')) {
            $usersWithDob = User::query()
                ->whereNotNull('date_of_birth')
                ->orderBy('name')
                ->get(['id', 'name', 'date_of_birth']);

            $birthdays = app(NextBirthday::class)->upcomingForUsers($usersWithDob);
        }
    } catch (Throwable $e) {
        $birthdays = [];
    }

    return view('birthdays.index', ['birthdays' => $birthdays]);
})->middleware(['auth', 'verified'])->name('birthdays.index');

Route::get('/dashboard', fn () => redirect()->route('dashboard'))
    ->middleware(['auth', 'verified']);

Route::get('/media', function () {
    $tab = (string) request()->query('tab', '');
    $tab = strtolower(trim($tab));
    if ($tab === 'images') {
        $tab = 'photos';
    }
    if (!in_array($tab, ['photos', 'videos'], true)) {
        $tab = 'photos';
    }

    $encodeCursor = function ($createdAt, int $id): string {
        $payload = [
            't' => $createdAt ? $createdAt->getTimestamp() : 0,
            'id' => $id,
        ];
        return rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    };

    $imagesItems = [];
    $imagesNextCursor = null;
    try {
        if (Schema::hasTable('cloud_nodes')) {
            $hasImageFocal = Schema::hasColumn('cloud_nodes', 'focal_x') && Schema::hasColumn('cloud_nodes', 'focal_y');
            $rows = CloudNode::query()
                ->with('uploader:id,name')
                ->whereNotNull('stored_path')
                ->where('mime', 'like', 'image/%')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(24)
                ->get();

            $imagesItems = $rows->map(fn ($img) => [
                'id' => (int) $img->id,
                'type' => 'image',
                'title' => 'Photo',
                'name' => (string) ($img->name ?? ''),
                'by' => (string) ($img->uploader?->name ?? 'Quelqu’un'),
                'at' => $img->created_at?->toIso8601String(),
                'at_human' => $img->created_at?->diffForHumans(),
                'thumb_url' => route('images.view', $img),
                'open_url' => route('media.photos.show', ['node' => $img, 'return' => route('media.index', ['tab' => 'photos'])]),
                'focal_x' => $hasImageFocal ? (is_null($img->focal_x) ? null : (float) $img->focal_x) : null,
                'focal_y' => $hasImageFocal ? (is_null($img->focal_y) ? null : (float) $img->focal_y) : null,
            ])->values()->all();

            if ($rows->count() === 24) {
                $last = $rows->last();
                if ($last) {
                    $imagesNextCursor = $encodeCursor($last->created_at, (int) $last->id);
                }
            }
        }
    } catch (Throwable $e) {
        $imagesItems = [];
        $imagesNextCursor = null;
    }

    $videosItems = [];
    $videosNextCursor = null;
    try {
        if (Schema::hasTable('videos')) {
            $hasDuration = Schema::hasColumn('videos', 'duration_seconds');
            $hasVideoFocal = Schema::hasColumn('videos', 'focal_x') && Schema::hasColumn('videos', 'focal_y');
            $rows = Video::query()
                ->with('creator:id,name')
                // /media (tab=videos) is for personal videos only.
                ->where(function ($q) {
                    $q->whereNull('category')
                        ->orWhere('category', '')
                        ->orWhere('category', 'docs');
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(24)
                ->get();

            $videosItems = $rows->map(fn ($v) => [
                'id' => (int) $v->id,
                'type' => 'video',
                'title' => (string) ($v->title ?? 'Vidéo'),
                'by' => (string) ($v->creator?->name ?? 'Quelqu’un'),
                'at' => $v->created_at?->toIso8601String(),
                'at_human' => $v->created_at?->diffForHumans(),
                'poster_url' => $v->video_path ? route('videos.poster', $v) : null,
                'duration_seconds' => $hasDuration ? (int) ($v->duration_seconds ?? 0) : null,
                'open_url' => route('videos.show', $v),
                'focal_x' => $hasVideoFocal ? (is_null($v->focal_x) ? null : (float) $v->focal_x) : null,
                'focal_y' => $hasVideoFocal ? (is_null($v->focal_y) ? null : (float) $v->focal_y) : null,
            ])->values()->all();

            if ($rows->count() === 24) {
                $last = $rows->last();
                if ($last) {
                    $videosNextCursor = $encodeCursor($last->created_at, (int) $last->id);
                }
            }
        }
    } catch (Throwable $e) {
        $videosItems = [];
        $videosNextCursor = null;
    }

    $response = response()->view('media.index', [
        'tab' => $tab,
        'imagesItems' => $imagesItems,
        'imagesNextCursor' => $imagesNextCursor,
        'videosItems' => $videosItems,
        'videosNextCursor' => $videosNextCursor,
        'pageSize' => 24,
    ]);

    // Avoid stale HTML being served by proxies (LiteSpeed) after deploy.
    return $response
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0')
        ->header('X-LiteSpeed-Cache-Control', 'no-cache');
})->middleware(['auth', 'verified'])
    ->name('media.index');

// Unified photo viewer destination (replaces legacy /galerie/{node}/ouvrir)
Route::get('/media/photos/{node}', [ImageController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('media.photos.show');

Route::get('/mediatheque', function () {
    $tab = strtolower(trim((string) request()->query('tab', '')));
    if (!in_array($tab, ['films', 'series'], true)) {
        $tab = 'films';
    }

    $encodeCursor = function ($createdAt, int $id): string {
        $payload = [
            't' => $createdAt ? $createdAt->getTimestamp() : 0,
            'id' => $id,
        ];
        return rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    };

    $buildItems = function (string $category) use ($encodeCursor): array {
        if (!Schema::hasTable('videos')) {
            return [[], null];
        }

        $hasDuration = Schema::hasColumn('videos', 'duration_seconds');
        $hasVideoFocal = Schema::hasColumn('videos', 'focal_x') && Schema::hasColumn('videos', 'focal_y');

        $limit = 24;
        $rows = Video::query()
            ->with('creator:id,name')
            ->where('category', $category)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $items = $rows->map(fn ($v) => [
            'id' => (int) $v->id,
            'type' => 'video',
            'title' => (string) ($v->title ?? ($category === 'series' ? 'Série' : 'Film')),
            'by' => (string) ($v->creator?->name ?? 'Quelqu’un'),
            'at' => $v->created_at?->toIso8601String(),
            'at_human' => $v->created_at?->diffForHumans(),
            'poster_url' => $v->video_path ? route('videos.poster', $v) : null,
            'duration_seconds' => $hasDuration ? (int) ($v->duration_seconds ?? 0) : null,
            'open_url' => route('videos.show', $v),
            'focal_x' => $hasVideoFocal ? (is_null($v->focal_x) ? null : (float) $v->focal_x) : null,
            'focal_y' => $hasVideoFocal ? (is_null($v->focal_y) ? null : (float) $v->focal_y) : null,
        ])->values()->all();

        $nextCursor = null;
        if ($rows->count() === $limit) {
            $last = $rows->last();
            if ($last) {
                $nextCursor = $encodeCursor($last->created_at, (int) $last->id);
            }
        }

        return [$items, $nextCursor];
    };

    [$filmsItems, $filmsNextCursor] = $buildItems('films');
    [$seriesItems, $seriesNextCursor] = $buildItems('series');

    $response = response()->view('mediatheque.index', [
        'tab' => $tab,
        'filmsItems' => $filmsItems,
        'seriesItems' => $seriesItems,
        'filmsNextCursor' => $filmsNextCursor,
        'seriesNextCursor' => $seriesNextCursor,
        'pageSize' => 24,
    ]);

    // Force bypass of any HTML page cache (LiteSpeed/proxies).
    return $response
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0')
        ->header('X-LiteSpeed-Cache-Control', 'no-cache');
})->middleware(['auth', 'verified'])
    ->name('mediatheque.index');

Route::get('/api/media', function () {
    $type = strtolower((string) request()->query('type', ''));
    if (!in_array($type, ['image', 'video'], true)) {
        return response()->json(['message' => 'Invalid type'], 422);
    }

    $limit = (int) request()->query('limit', 24);
    if ($limit <= 0) $limit = 24;
    if ($limit > 48) $limit = 48;

    $cursorRaw = (string) request()->query('cursor', '');
    $cursor = null;
    if ($cursorRaw !== '') {
        $b64 = strtr($cursorRaw, '-_', '+/');
        $b64 .= str_repeat('=', (4 - (strlen($b64) % 4)) % 4);
        $decoded = base64_decode($b64, true);
        if ($decoded !== false) {
            $json = json_decode($decoded, true);
            if (is_array($json) && isset($json['t'], $json['id'])) {
                $cursor = [
                    't' => (int) $json['t'],
                    'id' => (int) $json['id'],
                ];
            }
        }
    }

    $encodeCursor = function ($createdAt, int $id): string {
        $payload = [
            't' => $createdAt ? $createdAt->getTimestamp() : 0,
            'id' => $id,
        ];
        return rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    };

    if ($type === 'image') {
        if (!Schema::hasTable('cloud_nodes')) {
            return response()->json(['items' => [], 'next_cursor' => null]);
        }

        $hasImageFocal = Schema::hasColumn('cloud_nodes', 'focal_x') && Schema::hasColumn('cloud_nodes', 'focal_y');

        $q = CloudNode::query()
            ->with('uploader:id,name')
            ->whereNotNull('stored_path')
            ->where('mime', 'like', 'image/%')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($cursor) {
            $dt = \Carbon\Carbon::createFromTimestamp($cursor['t']);
            $q->where(function ($w) use ($cursor) {
                $dt = \Carbon\Carbon::createFromTimestamp($cursor['t']);
                $w->where('created_at', '<', $dt)
                    ->orWhere(function ($w2) use ($cursor) {
                        $dt = \Carbon\Carbon::createFromTimestamp($cursor['t']);
                        $w2->where('created_at', '=', $dt)
                            ->where('id', '<', $cursor['id']);
                    });
            });
        }

        $rows = $q->limit($limit)->get();
        $items = $rows->map(fn ($img) => [
            'id' => (int) $img->id,
            'type' => 'image',
            'title' => 'Photo',
            'name' => (string) ($img->name ?? ''),
            'by' => (string) ($img->uploader?->name ?? 'Quelqu’un'),
            'at' => $img->created_at?->toIso8601String(),
            'at_human' => $img->created_at?->diffForHumans(),
            'thumb_url' => route('images.view', $img),
            'open_url' => route('media.photos.show', ['node' => $img, 'return' => route('media.index', ['tab' => 'photos'])]),
            'focal_x' => $hasImageFocal ? (is_null($img->focal_x) ? null : (float) $img->focal_x) : null,
            'focal_y' => $hasImageFocal ? (is_null($img->focal_y) ? null : (float) $img->focal_y) : null,
        ])->values();

        $next = null;
        if ($rows->count() === $limit) {
            $last = $rows->last();
            if ($last) {
                $next = $encodeCursor($last->created_at, (int) $last->id);
            }
        }

        return response()->json(['items' => $items, 'next_cursor' => $next]);
    }

    // video
    if (!Schema::hasTable('videos')) {
        return response()->json(['items' => [], 'next_cursor' => null]);
    }

    $hasVideoFocal = Schema::hasColumn('videos', 'focal_x') && Schema::hasColumn('videos', 'focal_y');
    $category = strtolower(trim((string) request()->query('category', '')));
    if (!in_array($category, ['', 'films', 'series'], true)) {
        return response()->json(['message' => 'Invalid category'], 422);
    }

    $q = Video::query()
        ->with('creator:id,name')
        ->orderByDesc('created_at')
        ->orderByDesc('id');

    if ($category !== '') {
        // Explicit category used by Médiathèque infinite scrolling.
        $q->where('category', $category);
    } else {
        // Default "video" feed (used by /media) must not include Médiathèque items.
        $q->where(function ($w) {
            $w->whereNull('category')
                ->orWhere('category', '')
                ->orWhere('category', 'docs');
        });
    }

    if ($cursor) {
        $q->where(function ($w) use ($cursor) {
            $dt = \Carbon\Carbon::createFromTimestamp($cursor['t']);
            $w->where('created_at', '<', $dt)
                ->orWhere(function ($w2) use ($cursor) {
                    $dt = \Carbon\Carbon::createFromTimestamp($cursor['t']);
                    $w2->where('created_at', '=', $dt)
                        ->where('id', '<', $cursor['id']);
                });
        });
    }

    $rows = $q->limit($limit)->get();
    $items = $rows->map(fn ($v) => [
        'id' => (int) $v->id,
        'type' => 'video',
        'title' => (string) ($v->title ?? 'Vidéo'),
        'by' => (string) ($v->creator?->name ?? 'Quelqu’un'),
        'at' => $v->created_at?->toIso8601String(),
        'at_human' => $v->created_at?->diffForHumans(),
        'poster_url' => $v->video_path ? route('videos.poster', $v) : null,
        'duration_seconds' => Schema::hasColumn('videos', 'duration_seconds') ? (int) ($v->duration_seconds ?? 0) : null,
        'open_url' => route('videos.show', $v),
        'focal_x' => $hasVideoFocal ? (is_null($v->focal_x) ? null : (float) $v->focal_x) : null,
        'focal_y' => $hasVideoFocal ? (is_null($v->focal_y) ? null : (float) $v->focal_y) : null,
    ])->values();

    $next = null;
    if ($rows->count() === $limit) {
        $last = $rows->last();
        if ($last) {
            $next = $encodeCursor($last->created_at, (int) $last->id);
        }
    }

    return response()->json(['items' => $items, 'next_cursor' => $next]);
})->middleware(['auth', 'verified']);

Route::get('/moments', function () {
    $moments = collect();
    try {
        if (Schema::hasTable('events')) {
            $user = auth()->user();
            $hasStartAt = Schema::hasColumn('events', 'start_at');
            $hasStartsOn = Schema::hasColumn('events', 'starts_on');

            $q = Event::query();
            if ($user) {
                $q = $q->visibleTo($user);
            }
            if (Schema::hasColumn('events', 'status')) {
                $q = $q->where('status', 'active');
            }

            if ($hasStartAt) {
                $q = $q->whereNotNull('start_at')
                    ->where('start_at', '>=', now()->copy()->startOfDay())
                    ->orderBy('start_at');
            } elseif ($hasStartsOn) {
                $q = $q->whereDate('starts_on', '>=', now()->toDateString())
                    ->orderBy('starts_on');
            } else {
                $q = null;
            }

            $moments = $q ? $q->limit(50)->get() : collect();
        }
    } catch (Throwable $e) {
        $moments = collect();
    }

    $momentsForUi = $moments->map(function (Event $e) {
        $date = $e->start_at ?? ($e->starts_on ?? null);
        $subtitle = $e->category ?? ($e->type ?? null);
        return [
            'date_label' => $date ? $date->translatedFormat('j M Y') : '',
            'title' => $e->title,
            'subtitle' => $subtitle,
        ];
    });

    return view('moments.index', ['moments' => $momentsForUi]);
})->middleware(['auth', 'verified'])->name('moments.index');

Route::get('/visio', function () {
    $response = response()->view('visio.index');

    // Force bypass of any HTML page cache (LiteSpeed/proxies).
    return $response
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0')
        ->header('X-LiteSpeed-Cache-Control', 'no-cache');
})->middleware(['auth', 'verified'])
    ->name('visio.index');

Route::get('/visio/{room}', function (string $room) {
    $domain = (string) (config('visio.jitsi_domain') ?? 'meet.jit.si');
    $domain = preg_replace('#^https?://#i', '', trim($domain));
    $domain = rtrim((string) $domain, '/');

    $url = 'https://' . $domain . '/' . rawurlencode($room);

    // Avoid embedding Jitsi: open in a new tab/window instead.
    $response = redirect()->away($url);

    // Force bypass of any HTML page cache (LiteSpeed/proxies).
    return $response
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0')
        ->header('X-LiteSpeed-Cache-Control', 'no-cache');
})->where('room', '[A-Za-z0-9_-]{3,64}')
    ->middleware(['auth', 'verified'])
    ->name('visio.room');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
        Route::get('/me/astro', [AstroProfileController::class, 'show'])->name('astro.show');

    Route::resource('playlists', PlaylistController::class);
    Route::get('playlists/{playlist}/items/search', [PlaylistItemController::class, 'search'])->name('playlists.items.search');
    Route::post('playlists/{playlist}/items', [PlaylistItemController::class, 'store'])->name('playlists.items.store');
    Route::delete('playlists/{playlist}/items/{item}', [PlaylistItemController::class, 'destroy'])->name('playlists.items.destroy');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/poll', [ChatController::class, 'poll'])->name('chat.poll');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');



    // Admin-only diagnostics (helps debug “server not updating” issues).
    $diagHandler = function () {
        Gate::authorize('manage-users');

        $paths = [
            'routes_web' => base_path('routes/web.php'),
            'videos_index' => resource_path('views/videos/index.blade.php'),
            'cached_routes' => app()->getCachedRoutesPath(),
            'cached_config' => app()->getCachedConfigPath(),
            'cached_services' => app()->getCachedServicesPath(),
            'cached_packages' => app()->getCachedPackagesPath(),
        ];

        $mtimes = [];
        foreach ($paths as $key => $path) {
            $mtimes[$key] = file_exists($path) ? filemtime($path) : null;
        }

        $opcache = null;
        if (function_exists('opcache_get_configuration')) {
            $cfg = opcache_get_configuration();
            $directives = $cfg['directives'] ?? [];
            $opcache = [
                'enabled' => (bool) ($directives['opcache.enable'] ?? false),
                'validate_timestamps' => $directives['opcache.validate_timestamps'] ?? null,
                'revalidate_freq' => $directives['opcache.revalidate_freq'] ?? null,
            ];
        }

        \Illuminate\Support\Facades\Log::info('diag.ping', [
            'path' => base_path(),
            'env' => config('app.env'),
        ]);

        $baseUserIniPath = base_path('.user.ini');
        $publicUserIniPath = public_path('.user.ini');

        $readPreview = static function (string $path): ?string {
            if (!is_file($path) || !is_readable($path)) {
                return null;
            }

            $contents = @file_get_contents($path);
            if (!is_string($contents)) {
                return null;
            }

            $contents = str_replace(["\r\n", "\r"], "\n", $contents);
            $contents = preg_replace('/\x00+/', '', $contents) ?? $contents;
            $contents = trim($contents);

            if ($contents === '') {
                return '';
            }

            $max = 1200;
            if (strlen($contents) > $max) {
                return substr($contents, 0, $max) . "\n…(truncated)";
            }

            return $contents;
        };

        $baseUserIni = [
            'exists' => file_exists($baseUserIniPath),
            'path' => $baseUserIniPath,
            'size_bytes' => is_file($baseUserIniPath) ? @filesize($baseUserIniPath) : null,
            'mtime' => is_file($baseUserIniPath) ? @filemtime($baseUserIniPath) : null,
            'sha1' => is_file($baseUserIniPath) ? @sha1_file($baseUserIniPath) : null,
            'preview' => $readPreview($baseUserIniPath),
        ];

        $publicUserIni = [
            'exists' => file_exists($publicUserIniPath),
            'path' => $publicUserIniPath,
            'size_bytes' => is_file($publicUserIniPath) ? @filesize($publicUserIniPath) : null,
            'mtime' => is_file($publicUserIniPath) ? @filemtime($publicUserIniPath) : null,
            'sha1' => is_file($publicUserIniPath) ? @sha1_file($publicUserIniPath) : null,
            'preview' => $readPreview($publicUserIniPath),
        ];

        $redactPresignedUrl = static function (?string $url): ?string {
            if ($url === null || trim($url) === '') {
                return null;
            }

            $u = parse_url($url);
            if (!is_array($u)) {
                return null;
            }

            $query = [];
            parse_str((string) ($u['query'] ?? ''), $query);

            if (isset($query['X-Amz-Signature'])) {
                $query['X-Amz-Signature'] = 'REDACTED';
            }

            if (isset($query['X-Amz-Credential'])) {
                $cred = (string) $query['X-Amz-Credential'];
                $parts = explode('/', $cred);
                $accessKey = (string) ($parts[0] ?? '');
                if ($accessKey !== '') {
                    $masked = str_repeat('*', max(0, strlen($accessKey) - 4)) . substr($accessKey, -4);
                    $parts[0] = $masked;
                    $query['X-Amz-Credential'] = implode('/', $parts);
                }
            }

            $scheme = (string) ($u['scheme'] ?? 'https');
            $host = (string) ($u['host'] ?? '');
            $path = (string) ($u['path'] ?? '');
            $out = $scheme . '://' . $host . $path;
            if (!empty($query)) {
                ksort($query);
                $out .= '?' . http_build_query($query);
            }
            return $out;
        };

        $r2 = null;
        try {
            $r2 = app(R2UploadService::class);
        } catch (Throwable $e) {
            $r2 = null;
        }

        $r2Info = null;
        if ($r2) {
            $endpoint = (string) $r2->endpoint();
            $bucket = (string) $r2->bucket();
            $publicBase = (string) config('uploads.r2_public_base_url');

            $missing = [];
            if (trim((string) env('R2_ACCESS_KEY_ID', '')) === '') $missing[] = 'R2_ACCESS_KEY_ID';
            if (trim((string) env('R2_SECRET_ACCESS_KEY', '')) === '') $missing[] = 'R2_SECRET_ACCESS_KEY';
            if (trim((string) env('R2_BUCKET', '')) === '') $missing[] = 'R2_BUCKET';
            if (trim($endpoint) === '') $missing[] = 'R2_ENDPOINT or R2_ACCOUNT_ID';
            if (trim($publicBase) === '') $missing[] = 'R2_PUBLIC_BASE_URL';

            $test = null;
            $testEnabled = (string) request()->query('r2_test', '') === '1';
            if ($testEnabled && empty($missing)) {
                $t0 = microtime(true);
                $key = $r2->buildObjectKey('media', 'video', 'diag-test.txt');

                $uploadId = null;
                $abortOk = null;
                $error = null;
                try {
                    $uploadId = $r2->createMultipartUpload($key, 'application/octet-stream');
                    $r2->abortMultipartUpload($key, $uploadId);
                    $abortOk = true;
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                    $abortOk = false;
                }

                $test = [
                    'enabled' => true,
                    'ms' => (int) round((microtime(true) - $t0) * 1000),
                    'key' => $key,
                    'upload_id_prefix' => $uploadId ? substr((string) $uploadId, 0, 6) . '…' : null,
                    'abort_ok' => $abortOk,
                    'error' => $error,
                ];
            } else {
                $test = [
                    'enabled' => $testEnabled,
                    'note' => $testEnabled ? 'Test skipped due to missing config.' : 'Add ?r2_test=1 to run a signed multipart init+abort test.',
                ];
            }

            $presigned = null;
            if (empty($missing)) {
                try {
                    $key = $r2->buildObjectKey('media', 'photo', 'diag-presign.jpg');
                    $url = $r2->presignPutObject($key, 'image/jpeg', 900);
                    $presigned = [
                        'ok' => true,
                        'key' => $key,
                        'url_redacted' => $redactPresignedUrl($url),
                    ];
                } catch (Throwable $e) {
                    $presigned = [
                        'ok' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $r2Info = [
                'endpoint' => $endpoint,
                'bucket' => $bucket,
                'public_base_url' => $publicBase,
                'region' => (string) env('R2_REGION', 'auto'),
                'uploads_config' => [
                    'max_upload_bytes' => (int) config('uploads.max_upload_bytes'),
                    'multipart_threshold_bytes' => (int) config('uploads.multipart_threshold_bytes'),
                    'multipart_part_size_bytes' => (int) config('uploads.multipart_part_size_bytes'),
                    'quota_bytes' => (int) config('uploads.quota_bytes'),
                ],
                'missing' => $missing,
                'presign' => $presigned,
                'test' => $test,
            ];
        }

        $response = response()->json([
            'now' => now()->toIso8601String(),
            'base_path' => base_path(),
            'host' => request()->getHost(),
            'https' => request()->isSecure(),
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
            'request' => [
                'method' => request()->getMethod(),
                'uri' => request()->getRequestUri(),
                'content_length' => request()->server('CONTENT_LENGTH'),
                'content_type' => request()->header('Content-Type'),
            ],
            'app_env' => config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'php_sapi' => PHP_SAPI,
            'php_ini_loaded_file' => function_exists('php_ini_loaded_file') ? php_ini_loaded_file() : null,
            'php_ini_scanned_files' => function_exists('php_ini_scanned_files') ? php_ini_scanned_files() : null,
            'user_ini' => [
                'filename' => ini_get('user_ini.filename'),
                'cache_ttl' => ini_get('user_ini.cache_ttl'),
                'base_exists' => file_exists(base_path('.user.ini')),
                'public_exists' => file_exists(public_path('.user.ini')),
                'public_path' => public_path('.user.ini'),
                'base' => $baseUserIni,
                'public' => $publicUserIni,
            ],
            'php_ini' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'max_execution_time' => ini_get('max_execution_time'),
                'max_input_time' => ini_get('max_input_time'),
                'memory_limit' => ini_get('memory_limit'),
                'file_uploads' => ini_get('file_uploads'),
                'upload_tmp_dir' => ini_get('upload_tmp_dir'),
                'sys_temp_dir' => ini_get('sys_temp_dir'),
                'sys_get_temp_dir' => function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : null,
            ],
            'disk' => [
                'free_base_mb' => @disk_free_space(base_path()) ? (int) floor(@disk_free_space(base_path()) / 1024 / 1024) : null,
                'free_public_mb' => @disk_free_space(public_path()) ? (int) floor(@disk_free_space(public_path()) / 1024 / 1024) : null,
                'free_storage_mb' => @disk_free_space(storage_path()) ? (int) floor(@disk_free_space(storage_path()) / 1024 / 1024) : null,
            ],
            'r2' => $r2Info,
            'opcache' => $opcache,
            'mtimes' => $mtimes,
        ]);

        // Force bypass of any HTML/API cache (LiteSpeed/proxies).
        return $response
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('X-LiteSpeed-Cache-Control', 'no-cache');
    };

    // Primary route (double underscore).
    Route::get('/__diag', $diagHandler)->name('diag.index');
    // Aliases (some servers/WAF rules dislike “hidden” paths).
    Route::get('/_diag', $diagHandler);
    Route::get('/diag', $diagHandler);

    $opcacheResetHandler = function () {
        Gate::authorize('manage-users');

        $ok = null;
        if (function_exists('opcache_reset')) {
            $ok = (bool) opcache_reset();
        }

        \Illuminate\Support\Facades\Log::warning('diag.opcache_reset', [
            'ok' => $ok,
            'path' => base_path(),
        ]);

        return response()->json([
            'ok' => $ok,
            'note' => 'If ok=true, PHP-FPM OPcache was reset for this pool.',
        ]);
    };

    Route::post('/__opcache/reset', $opcacheResetHandler)->middleware('throttle:2,1')->name('diag.opcache.reset');
    Route::post('/_opcache/reset', $opcacheResetHandler)->middleware('throttle:2,1');
    Route::post('/opcache/reset', $opcacheResetHandler)->middleware('throttle:2,1');

    Route::get('videos/{video}/stream', [VideoController::class, 'stream'])->name('videos.stream');
    Route::get('videos/{video}/poster', [VideoController::class, 'poster'])->name('videos.poster');
    Route::post('videos/{video}/poster', [VideoController::class, 'storePoster'])->name('videos.poster.store');

    Route::get('videos/classify/{node}', [VideoController::class, 'classifyFromCloud'])->name('videos.classify');
    Route::post('videos/classify/{node}', [VideoController::class, 'storeFromCloudClassification'])->name('videos.classify.store');

    // Backward-compat: some older cached views referenced route('videos.import')
    Route::post('videos/import', [VideoController::class, 'store'])->name('videos.import');

    // The dedicated video upload page is deprecated; uploads happen in Cloud.
    Route::get('videos/create', function () {
        return redirect()->route('cloud.index');
    })->name('videos.create');

    Route::resource('videos', VideoController::class)->except(['create']);

    Route::get('/cloud/create', function () {
        return redirect()
            ->route('cloud.index')
            ->with('status', 'Déplacé vers le nouveau Cloud.');
    })->name('cloud.create.legacy');

    // Legacy gallery entry points: redirect to /media (single destination)
    Route::get('/galerie', function () {
        return redirect()->route('media.index', ['tab' => 'photos']);
    })->name('images.index');
    // The dedicated image upload page is deprecated; uploads happen in Cloud.
    Route::get('/galerie/importer', function () {
        return redirect()->route('cloud.index');
    })->name('images.create');
    Route::post('/galerie', [ImageController::class, 'store'])->name('images.store');
    Route::get('/galerie/{node}/ouvrir', function (CloudNode $node) {
        $return = request()->query('return');
        $selectedUser = request()->query('user');

        $params = ['node' => $node];
        if (is_string($return) && trim($return) !== '') {
            $params['return'] = $return;
        }
        if (is_numeric($selectedUser) && (int) $selectedUser !== 0) {
            $params['user'] = (int) $selectedUser;
        }

        return redirect()->route('media.photos.show', $params, 301);
    })->name('images.open');
    Route::get('/galerie/{node}', [ImageController::class, 'view'])->name('images.view');
    Route::post('/galerie/{node}/like', [ImageController::class, 'toggleLike'])->name('images.like');
    Route::delete('/galerie/{node}', [ImageController::class, 'destroy'])->name('images.destroy');

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    Route::get('/actu', function () {
        session()->put('news.has_new', false);
        return view('actu.index');
    })->name('actu.index');

    Route::get('/api/news', NewsIndexController::class)
        ->name('news.index');

    Route::post('/api/news/import', \App\Http\Controllers\Api\NewsImportController::class)
        ->middleware(['can:manage-users', 'throttle:6,1'])
        ->name('news.import');

    Route::get('/tarot', [TarotController::class, 'index'])->name('tarot.index');
    Route::post('/tarot/draw', [TarotController::class, 'draw'])->middleware('throttle:tarot-draw')->name('tarot.draw');
    Route::post('/tarot/reset', [TarotController::class, 'reset'])->name('tarot.reset');
    Route::post('/tarot/save', [TarotController::class, 'save'])->name('tarot.save');
    Route::get('/tarot/historique', [TarotController::class, 'history'])->name('tarot.history');
    Route::get('/tarot/historique/{reading}', [TarotController::class, 'show'])->name('tarot.history.show');

    Route::post('/api/tarot/tts', TarotTtsController::class)
        ->middleware('throttle:tarot-draw')
        ->name('tarot.tts');

    Route::post('/api/avatar-astro/generate', [\App\Http\Controllers\Api\AvatarAstroController::class, 'generate'])
        ->middleware('throttle:avatar-astro-generate')
        ->name('avatar.astro.generate');

    Route::get('/api/avatar-astro/status', [\App\Http\Controllers\Api\AvatarAstroController::class, 'status'])
        ->name('avatar.astro.status');

    Route::get('/avatar-astro/image', [\App\Http\Controllers\AvatarAstroImageController::class, 'show'])
        ->name('avatar.astro.image');

    // Authenticated users can view another member's avatar (used on profile pages).
    Route::get('/users/{user}/avatar-astro/image', [\App\Http\Controllers\AvatarAstroImageController::class, 'showForUserPublic'])
        ->name('avatar.astro.imagePublic');

    Route::get('/admin/users/{user}/avatar-astro/image', [\App\Http\Controllers\AvatarAstroImageController::class, 'showForUser'])
        ->middleware(['can:manage-users'])
        ->name('avatar.astro.imageForUser');

    // Admin override: generate/check Avatar Astro for any user.
    Route::post('/api/admin/users/{user}/avatar-astro/generate', [\App\Http\Controllers\Api\AvatarAstroController::class, 'generateForUser'])
        ->middleware(['can:manage-users', 'throttle:avatar-astro-generate'])
        ->name('avatar.astro.generateForUser');

    Route::get('/api/admin/users/{user}/avatar-astro/status', [\App\Http\Controllers\Api\AvatarAstroController::class, 'statusForUser'])
        ->middleware(['can:manage-users'])
        ->name('avatar.astro.statusForUser');

    Route::get('/cloud', [CloudNodeController::class, 'index'])->name('cloud.index');
    Route::post('/cloud/folders', [CloudNodeController::class, 'storeFolder'])->name('cloud.folders.store');
    Route::post('/cloud/files', [CloudNodeController::class, 'storeFile'])->name('cloud.files.store');

    Route::post('/cloud/uploads/init', [CloudNodeController::class, 'uploadInit'])->name('cloud.uploads.init');
    Route::post('/cloud/uploads/chunk', [CloudNodeController::class, 'uploadChunk'])->name('cloud.uploads.chunk');
    Route::post('/cloud/uploads/complete', [CloudNodeController::class, 'uploadComplete'])->name('cloud.uploads.complete');

    Route::get('/cloud/files/{node}/download', [CloudNodeController::class, 'download'])->name('cloud.files.download');
    Route::get('/cloud/files/{node}/preview', [CloudNodeController::class, 'preview'])->name('cloud.files.preview');
    Route::get('/cloud/files/{node}/view', [CloudNodeController::class, 'view'])->name('cloud.files.view');
    Route::patch('/cloud/nodes/{node}/rename', [CloudNodeController::class, 'rename'])->name('cloud.nodes.rename');
    Route::post('/cloud/nodes/move', [CloudNodeController::class, 'move'])->name('cloud.nodes.move');
        Route::get('/cloud/trash', [CloudNodeController::class, 'trash'])->name('cloud.trash');
        Route::get('/cloud/audit', [CloudNodeController::class, 'auditIndex'])->name('cloud.audit');
        Route::patch('/cloud/trash/{id}/restore', [CloudNodeController::class, 'restore'])->name('cloud.trash.restore');
        Route::delete('/cloud/trash/{id}/purge', [CloudNodeController::class, 'purge'])->name('cloud.trash.purge');
    Route::delete('/cloud/nodes/{node}', [CloudNodeController::class, 'destroy'])->name('cloud.nodes.destroy');

    // Legacy/shortcut aliases (older links/bookmarks)
    Route::get('/users', function () {
        Gate::authorize('manage-users');
        return redirect()->route('admin.users.index');
    })->name('users.index');

    Route::get('/users/create', function () {
        Gate::authorize('manage-users');
        return redirect()->route('admin.users.create');
    })->name('users.create');

    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('admin.users.create');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
        Route::get('/users/{user}/astro', [AdminUserController::class, 'astro'])->name('admin.users.astro');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
        Route::post('/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::post('/users/invites/send-pending', [AdminUserController::class, 'sendPendingInvites'])->name('admin.users.invites.sendPending');
        Route::post('/users/{user}/invite', [AdminUserController::class, 'resendInvite'])->name('admin.users.invite');
        Route::post('/users/{user}/invite-link', [AdminUserController::class, 'inviteLink'])->name('admin.users.inviteLink');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('admin.users.role');
    });
});

require __DIR__.'/auth.php';
