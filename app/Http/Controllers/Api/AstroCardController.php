<?php

namespace App\Http\Controllers\Api;

use App\Jobs\GenerateAstroCardJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AstroCardController
{
    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $signature = $user->astro_signature_json;
        if (!is_array($signature) || $signature === []) {
            return response()->json([
                'status' => 'error',
                'error' => 'Signature astro manquante.',
            ], 400);
        }

        if (($user->astro_card_status ?? null) === 'pending') {
            return response()->json([
                'status' => 'pending',
            ], 202);
        }

        // Basic anti-abuse: allow regenerate at most once per day for non-admins.
        $force = (bool) $request->boolean('force');
        $isAdmin = ($user->role ?? 'member') === 'admin';
        if (!$force && !$isAdmin && $user->astro_card_generated_at && $user->astro_card_generated_at->gt(now()->subDay())) {
            return response()->json([
                'status' => 'error',
                'error' => 'Réessaie plus tard (limite quotidienne).',
            ], 429);
        }

        $user->forceFill([
            'astro_card_style' => $user->astro_card_style ?: 'tarot_modern',
            'astro_card_status' => 'pending',
            'astro_card_error' => null,
        ])->save();

        GenerateAstroCardJob::dispatch((int) $user->id);

        return response()->json([
            'status' => 'pending',
        ], 202);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json([
            'status' => $user->astro_card_status,
            'image_url' => $user->astro_card_image_url,
            'generated_at' => optional($user->astro_card_generated_at)->toISOString(),
            'error' => $user->astro_card_error,
        ]);
    }
}
