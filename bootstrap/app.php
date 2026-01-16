<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\PostTooLargeException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
		$middleware->web(append: [
			\App\Http\Middleware\NoStoreForAuthenticated::class,
		]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Fichier trop volumineux pour la configuration serveur. Augmente post_max_size et upload_max_filesize (ex: 2048M).',
                ], 413);
            }

            return back()->withErrors([
                'upload' => 'Fichier trop volumineux pour la configuration serveur. Augmente post_max_size et upload_max_filesize (ex: 2048M) puis réessaie.',
            ]);
        });
    })->create();
