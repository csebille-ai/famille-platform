<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class NewsImportController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user() !== null, 401);
        abort_unless($request->user()?->can('manage-users') === true, 403);

        $exit = Artisan::call('news:import-rss');
        $out = trim((string) Artisan::output());

        return response()->json([
            'ok' => $exit === 0,
            'exit_code' => $exit,
            'output' => $out !== '' ? $out : null,
        ], $exit === 0 ? 200 : 500);
    }
}
