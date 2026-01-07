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
use App\Models\CloudNode;
use App\Models\Video;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $latestImages = CloudNode::query()
        ->where('type', 'file')
        ->whereNotNull('stored_path')
        ->where('mime', 'like', 'image/%')
        ->latest()
        ->limit(6)
        ->get();

    $latestVideos = Video::query()
        ->latest()
        ->limit(6)
        ->get();

    return view('dashboard', [
        'latestImages' => $latestImages,
        'latestVideos' => $latestVideos,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('resources/{resource}/open', [ResourceController::class, 'open'])->name('resources.open');
    Route::get('resources/{resource}/download', [ResourceController::class, 'download'])->name('resources.download');
    Route::resource('resources', ResourceController::class);

    Route::resource('playlists', PlaylistController::class);
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
    Route::post('/galerie', [ImageController::class, 'store'])->name('images.store');
    Route::get('/galerie/{node}', [ImageController::class, 'view'])->name('images.view');
    Route::post('/galerie/{node}/like', [ImageController::class, 'toggleLike'])->name('images.like');
    Route::delete('/galerie/{node}', [ImageController::class, 'destroy'])->name('images.destroy');

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
