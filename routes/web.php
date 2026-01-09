<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CloudNodeController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PlaylistItemController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TarotController;
use App\Http\Controllers\Api\TarotDrawController;
use App\Http\Controllers\Api\TarotTtsController;
use App\Http\Controllers\Api\NewsIndexController;
use App\Models\CloudNode;
use App\Models\ChatMessage;
use App\Models\Event;
use App\Models\NewsItem;
use App\Models\Resource;
use App\Models\Video;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::post('/api/tarot/draw', TarotDrawController::class)
    ->middleware('throttle:tarot-draw')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    $latestImages = collect();
    try {
        if (Schema::hasTable('cloud_nodes')) {
            $latestImages = CloudNode::query()
                ->with('uploader:id,name')
                ->where('type', 'file')
                ->whereNotNull('stored_path')
                ->where('mime', 'like', 'image/%')
                ->latest()
                ->limit(3)
                ->get();
        }
    } catch (Throwable $e) {
        $latestImages = collect();
    }

    $latestVideos = collect();
    try {
        if (Schema::hasTable('videos')) {
            $latestVideos = Video::query()->with('creator:id,name')->latest()->limit(3)->get();
        }
    } catch (Throwable $e) {
        $latestVideos = collect();
    }

    $latestDocs = collect();
    try {
        if (Schema::hasTable('resources')) {
            $latestDocs = Resource::query()
                ->with(['concernedUser:id,name', 'creator:id,name'])
                ->latest()
                ->limit(3)
                ->get();
        }
    } catch (Throwable $e) {
        $latestDocs = collect();
    }

    $lastChatMessage = null;
    try {
        if (Schema::hasTable('chat_messages')) {
            $lastChatMessage = ChatMessage::query()->with('user:id,name')->latest()->first();
        }
    } catch (Throwable $e) {
        $lastChatMessage = null;
    }

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

    $mediaCandidates = collect([
        [
            'type' => 'image',
            'model' => $latestImages->first(),
            'at' => $latestImages->first()?->created_at,
        ],
        [
            'type' => 'video',
            'model' => $latestVideos->first(),
            'at' => $latestVideos->first()?->created_at,
        ],
        [
            'type' => 'doc',
            'model' => $latestDocs->first(),
            'at' => $latestDocs->first()?->created_at,
        ],
    ])
        ->filter(fn ($c) => !empty($c['model']) && !empty($c['at']))
        ->sortByDesc('at')
        ->values();

    $todayMedia = $mediaCandidates->first();

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

    $communLinks = [
        ['label' => 'Urgences', 'category' => 'Urgences'],
        ['label' => 'Maison', 'category' => 'Maison'],
        ['label' => 'Voyages', 'category' => 'Voyages'],
        ['label' => 'École', 'category' => 'École'],
        ['label' => 'Administratif', 'category' => 'Administratif'],
        ['label' => 'Recettes', 'category' => 'Recettes'],
    ];

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
                $next = Event::query()
                    ->whereDate('starts_on', '>=', $today->toDateString())
                    ->whereDate('starts_on', '<=', $today->copy()->addDays(7)->toDateString())
                    ->orderBy('starts_on')
                    ->first();

                if ($next) {
                    $days = (int) $today->copy()->startOfDay()->diffInDays($next->starts_on, false);
                    $when = $days === 0 ? 'aujourd’hui' : ('dans ' . $days . ' jour' . ($days > 1 ? 's' : ''));
                    $label = $next->type ?: 'Événement';

                    return [[
                        'kind' => 'event',
                        'title' => $next->title,
                        'text' => $label . ' ' . $when,
                        'image_url' => null,
                        'href' => route('moments.index'),
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
                'href' => $photo ? route('images.open', $photo) : route('chat.index'),
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
                'href' => route('images.open', $memoryPhoto),
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
                'href' => route('images.open', $photo),
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

    return view('dashboard_v2', [
        'latestImages' => $latestImages,
        'latestVideos' => $latestVideos,
        'latestDocs' => $latestDocs,
        'lastChatMessage' => $lastChatMessage,
        'todayNewsItem' => $todayNewsItem,
        'todayMedia' => $todayMedia,
        'chatOnlineCount' => $chatOnlineCount,
        'communLinks' => $communLinks,
        'familyMoments' => $familyMoments,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/moments', function () {
    $moments = collect();
    try {
        if (Schema::hasTable('events')) {
            $moments = Event::query()
                ->whereDate('starts_on', '>=', now()->toDateString())
                ->orderBy('starts_on')
                ->limit(50)
                ->get();
        }
    } catch (Throwable $e) {
        $moments = collect();
    }

    $momentsForUi = $moments->map(function (Event $e) {
        $date = $e->starts_on;
        return [
            'date_label' => $date ? $date->translatedFormat('j M Y') : '',
            'title' => $e->title,
            'subtitle' => $e->type,
        ];
    });

    return view('moments.index', ['moments' => $momentsForUi]);
})->middleware(['auth', 'verified'])->name('moments.index');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('resources/{resource}/files/{file}/open', [ResourceController::class, 'openFile'])->name('resources.files.open');
    Route::get('resources/{resource}/files/{file}/preview', [ResourceController::class, 'previewFile'])->name('resources.files.preview');
    Route::get('resources/{resource}/files/{file}/download', [ResourceController::class, 'downloadFile'])->name('resources.files.download');

    Route::get('resources/{resource}/open', [ResourceController::class, 'open'])->name('resources.open');
    Route::get('resources/{resource}/preview', [ResourceController::class, 'preview'])->name('resources.preview');
    Route::get('resources/{resource}/download', [ResourceController::class, 'download'])->name('resources.download');
    Route::resource('resources', ResourceController::class);

    Route::resource('playlists', PlaylistController::class);
    Route::get('playlists/{playlist}/items/search', [PlaylistItemController::class, 'search'])->name('playlists.items.search');
    Route::post('playlists/{playlist}/items', [PlaylistItemController::class, 'store'])->name('playlists.items.store');
    Route::delete('playlists/{playlist}/items/{item}', [PlaylistItemController::class, 'destroy'])->name('playlists.items.destroy');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/poll', [ChatController::class, 'poll'])->name('chat.poll');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');

    Route::get('videos/{video}/stream', [VideoController::class, 'stream'])->name('videos.stream');
    Route::get('videos/{video}/poster', [VideoController::class, 'poster'])->name('videos.poster');
    Route::resource('videos', VideoController::class);

    Route::get('/cloud/create', function () {
        return redirect()
            ->route('images.index')
            ->with('status', 'Déplacé vers le nouveau Cloud.');
    })->name('cloud.create.legacy');

    Route::get('/galerie', [ImageController::class, 'index'])->name('images.index');
    Route::get('/galerie/importer', [ImageController::class, 'create'])->name('images.create');
    Route::post('/galerie', [ImageController::class, 'store'])->name('images.store');
    Route::get('/galerie/{node}/ouvrir', [ImageController::class, 'show'])->name('images.open');
    Route::get('/galerie/{node}', [ImageController::class, 'view'])->name('images.view');
    Route::post('/galerie/{node}/like', [ImageController::class, 'toggleLike'])->name('images.like');
    Route::delete('/galerie/{node}', [ImageController::class, 'destroy'])->name('images.destroy');

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    Route::get('/actu', function () {
        return view('actu.index');
    })->name('actu.index');

    Route::get('/api/news', NewsIndexController::class)
        ->name('news.index');

    Route::get('/tarot', [TarotController::class, 'index'])->name('tarot.index');
    Route::post('/tarot/draw', [TarotController::class, 'draw'])->middleware('throttle:tarot-draw')->name('tarot.draw');
    Route::post('/tarot/reset', [TarotController::class, 'reset'])->name('tarot.reset');
    Route::post('/tarot/save', [TarotController::class, 'save'])->name('tarot.save');
    Route::get('/tarot/historique', [TarotController::class, 'history'])->name('tarot.history');
    Route::get('/tarot/historique/{reading}', [TarotController::class, 'show'])->name('tarot.history.show');

    Route::post('/api/tarot/tts', TarotTtsController::class)
        ->middleware('throttle:tarot-draw')
        ->name('tarot.tts');

    Route::get('/cloud', [CloudNodeController::class, 'index'])->name('cloud.index');
    Route::post('/cloud/folders', [CloudNodeController::class, 'storeFolder'])->name('cloud.folders.store');
    Route::post('/cloud/files', [CloudNodeController::class, 'storeFile'])->name('cloud.files.store');
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

    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::post('/users/{user}/invite', [AdminUserController::class, 'resendInvite'])->name('admin.users.invite');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('admin.users.role');
    });
});

require __DIR__.'/auth.php';
