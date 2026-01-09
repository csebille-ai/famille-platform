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
use App\Models\Resource;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

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

    $latestVideos = Video::query()->latest()->limit(2)->get();

    $latestDocs = Resource::query()
        ->with('concernedUser:id,name')
        ->latest()
        ->limit(3)
        ->get();

    $lastChatMessage = ChatMessage::query()->with('user:id,name')->latest()->first();

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

    $activity = collect();

    $recentImages = CloudNode::query()
        ->with('uploader:id,name')
        ->where('type', 'file')
        ->whereNotNull('stored_path')
        ->where('mime', 'like', 'image/%')
        ->latest()
        ->limit(5)
        ->get();

    foreach ($recentImages as $img) {
        $actor = $img->uploader?->name ?: 'Quelqu’un';
        $activity->push([
            'at' => $img->created_at,
            'text' => $actor . ' a ajouté une photo',
            'href' => route('images.open', $img),
        ]);
    }

    $recentVideos = DB::table('videos')
        ->leftJoin('users', 'users.id', '=', 'videos.created_by')
        ->orderByDesc('videos.created_at')
        ->limit(5)
        ->get(['videos.id', 'videos.title', 'videos.created_at', 'users.name as user_name']);

    foreach ($recentVideos as $v) {
        $actor = $v->user_name ?: 'Quelqu’un';
        $activity->push([
            'at' => $v->created_at,
            'text' => $actor . ' a ajouté une vidéo : ' . (string) $v->title,
            'href' => route('videos.show', ['video' => $v->id]),
        ]);
    }

    $recentDocs = DB::table('resources')
        ->leftJoin('users', 'users.id', '=', 'resources.created_by')
        ->orderByDesc('resources.created_at')
        ->limit(5)
        ->get(['resources.id', 'resources.title', 'resources.created_at', 'users.name as user_name']);

    foreach ($recentDocs as $r) {
        $actor = $r->user_name ?: 'Quelqu’un';
        $activity->push([
            'at' => $r->created_at,
            'text' => $actor . ' a ajouté un document : ' . (string) $r->title,
            'href' => route('resources.show', ['resource' => $r->id]),
        ]);
    }

    $activity = $activity
        ->filter(fn ($a) => !empty($a['at']))
        ->sortByDesc('at')
        ->values()
        ->take(5);

    return view('dashboard', [
        'latestImages' => $latestImages,
        'latestVideos' => $latestVideos,
        'latestDocs' => $latestDocs,
        'lastChatMessage' => $lastChatMessage,
        'chatOnlineCount' => $chatOnlineCount,
        'communLinks' => $communLinks,
        'activity' => $activity,
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

    Route::get('/cloud', function () {
        return redirect()->route('images.index');
    })->name('cloud.index');
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
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('admin.users.role');
    });
});

require __DIR__.'/auth.php';
