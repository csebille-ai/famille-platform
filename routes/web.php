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
    $latestImages = CloudNode::query()
        ->with('uploader:id,name')
        ->where('type', 'file')
        ->whereNotNull('stored_path')
        ->where('mime', 'like', 'image/%')
        ->latest()
        ->limit(3)
        ->get();

    $latestVideos = Video::query()->with('creator:id,name')->latest()->limit(3)->get();

    $latestDocs = Resource::query()
        ->with(['concernedUser:id,name', 'creator:id,name'])
        ->latest()
        ->limit(3)
        ->get();

    $lastChatMessage = ChatMessage::query()->with('user:id,name')->latest()->first();

    $todayNewsItem = NewsItem::query()
        ->orderByDesc('published_at')
        ->orderByDesc('fetched_at')
        ->orderByDesc('id')
        ->first();

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

    $chatOnlineCount = (int) DB::table('chat_presences')
        ->where('last_seen_at', '>=', now()->subSeconds(45))
        ->count();

    $communLinks = [
        ['label' => 'Urgences', 'category' => 'Urgences'],
        ['label' => 'Maison', 'category' => 'Maison'],
        ['label' => 'Voyages', 'category' => 'Voyages'],
        ['label' => 'École', 'category' => 'École'],
        ['label' => 'Administratif', 'category' => 'Administratif'],
        ['label' => 'Recettes', 'category' => 'Recettes'],
    ];

    $buildFamilyMoments = function (): array {
        $cards = [];

        // Always include at least one visual (photo) to make the end of the dashboard a "reward".
        $today = now();

        $memoryPhoto = null;
        try {
            $memoryPhoto = CloudNode::query()
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
            $memoryPhoto = null;
        }

        $surprisePhoto = null;
        try {
            $surprisePhoto = CloudNode::query()
                ->with('uploader:id,name')
                ->where('type', 'file')
                ->whereNotNull('stored_path')
                ->where('mime', 'like', 'image/%')
                ->inRandomOrder()
                ->first();
        } catch (Throwable $e) {
            $surprisePhoto = null;
        }

        $photo = $memoryPhoto ?: $surprisePhoto;
        if ($photo) {
            $isMemory = (bool) $memoryPhoto;
            $years = $photo->created_at ? max(0, (int) $photo->created_at->diffInYears($today)) : 0;

            $title = $isMemory ? 'Souvenir du jour' : 'Photo surprise';
            $subtitle = $isMemory
                ? (($years > 0 ? 'Il y a ' . $years . ' an' . ($years > 1 ? 's' : '') . ' aujourd’hui' : 'Un souvenir du jour') . ' · ' . ($photo->uploader?->name ?: 'Famille'))
                : 'Un petit clin d’œil au hasard.';

            $cards[] = [
                'kind' => $isMemory ? 'memory' : 'surprise',
                'title' => $title,
                'text' => $subtitle,
                'image_url' => route('images.view', $photo),
                'href' => route('images.open', $photo),
                'cta' => $isMemory ? 'Voir le souvenir' : 'Voir la photo',
            ];
        }

        // Upcoming family event (if enabled).
        try {
            if (Schema::hasTable('events')) {
                $next = Event::query()
                    ->whereDate('starts_on', '>=', $today->toDateString())
                    ->orderBy('starts_on')
                    ->first();

                if ($next) {
                    $days = (int) $today->startOfDay()->diffInDays($next->starts_on, false);
                    $when = $days === 0 ? 'aujourd’hui' : ('dans ' . $days . ' jour' . ($days > 1 ? 's' : ''));
                    $label = $next->type ?: 'Événement';

                    $cards[] = [
                        'kind' => 'event',
                        'title' => $next->title,
                        'text' => $label . ' ' . $when,
                        'image_url' => null,
                        'href' => route('moments.index'),
                        'cta' => 'Voir',
                    ];
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Light daily tarot card (fun message, non mystique).
        $deck = (array) config('tarot.cards', []);
        if (!empty($deck) && count($cards) < 3) {
            $seed = crc32('tarot:' . $today->toDateString());
            $card = $deck[$seed % count($deck)] ?? null;

            if (is_array($card) && !empty($card['name'])) {
                $messages = [
                    'Aujourd’hui, on y va tranquillement et on avance quand même.',
                    'Version du jour: simple, efficace, sans se prendre la tête.',
                    'Petit rappel: on fait mieux avec une pause qu’avec un sprint.',
                    'On garde le cap: une petite action vaut mieux qu’un grand plan.',
                ];
                $msg = $messages[$seed % count($messages)];

                $cards[] = [
                    'kind' => 'tarot',
                    'title' => 'Carte du jour: ' . (string) $card['name'],
                    'text' => $msg,
                    'image_url' => null,
                    'href' => route('tarot.index'),
                    'cta' => 'Voir',
                ];
            }
        }

        return array_slice($cards, 0, 3);
    };

    $familyMoments = [];
    try {
        $familyMoments = Cache::remember(
            'dashboard.family_moments.' . now()->toDateString(),
            now()->addDay(),
            fn () => $buildFamilyMoments()
        );
    } catch (Throwable $e) {
        // If cache is misconfigured/unwritable in production, do not 500 the dashboard.
        $familyMoments = $buildFamilyMoments();
    }

    return view('dashboard', [
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
