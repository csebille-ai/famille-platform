<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class NewsImportController
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($request->user() !== null, 401);
        abort_unless($request->user()?->can('manage-users') === true, 403);

        $exit = Artisan::call('news:import-rss');
        $out = trim((string) Artisan::output());

        if (!$request->expectsJson()) {
            $msg = $exit === 0
                ? 'Actus mises à jour.'
                : 'Échec de la mise à jour des actus.';

            if ($out !== '') {
                $msg .= ' ' . mb_substr($out, 0, 500);
            }

            $redirect = redirect()->back();
            if ($exit === 0) {
                return $redirect->with('status', $msg);
            }

            return $redirect->withErrors(['news' => $msg]);
        }

        return response()->json([
            'ok' => $exit === 0,
            'exit_code' => $exit,
            'output' => $out !== '' ? $out : null,
        ], $exit === 0 ? 200 : 500);
    }
}
