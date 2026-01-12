<?php

namespace App\Http\Controllers\Api;

use App\Jobs\GenerateAstroCardJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AstroCardController
{
    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $this->doGenerate($request, $user);
    }

    public function generateForUser(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user() !== null, 401);

        // Gate middleware already enforced; this is a double-safety.
        abort_unless($request->user()?->can('manage-users') === true, 403);

        return $this->doGenerate($request, $user);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json($this->statusPayload($user));
    }

    public function statusForUser(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user() !== null, 401);
        abort_unless($request->user()?->can('manage-users') === true, 403);

        return response()->json($this->statusPayload($user));
    }

    private function doGenerate(Request $request, User $target): JsonResponse
    {
        $signature = $target->astro_signature_json;
        if (!is_array($signature) || $signature === []) {
            return response()->json([
                'status' => 'error',
                'error' => 'Signature astro manquante.',
            ], 400);
        }

        if (($target->astro_card_status ?? null) === 'pending') {
            return response()->json([
                'status' => 'pending',
            ], 202);
        }

        // Basic anti-abuse: allow regenerate at most once per day for non-admins.
        $force = (bool) $request->boolean('force');
        $actorIsAdmin = ($request->user()?->role ?? 'member') === 'admin';
        if (!$force && !$actorIsAdmin && $target->astro_card_generated_at && $target->astro_card_generated_at->gt(now()->subDay())) {
            return response()->json([
                'status' => 'error',
                'error' => 'Réessaie plus tard (limite quotidienne).',
            ], 429);
        }

        $target->forceFill([
            'astro_card_style' => $target->astro_card_style ?: 'tarot_modern',
            'astro_card_status' => 'pending',
            'astro_card_error' => null,
        ])->save();

        GenerateAstroCardJob::dispatch((int) $target->id);

        return response()->json([
            'status' => 'pending',
        ], 202);
    }

    /**
     * @return array{status:mixed,image_url:mixed,generated_at:?string,error:mixed}
     */
    private function statusPayload(User $user): array
    {
        return [
            'status' => $user->astro_card_status,
            'image_url' => $user->astro_card_image_url,
            'generated_at' => optional($user->astro_card_generated_at)->toISOString(),
            'error' => $user->astro_card_error,
        ];
    }
}
