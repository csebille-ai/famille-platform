<?php

namespace App\Http\Controllers\Api;

use App\Jobs\GenerateAstroCardJob;
use App\Models\User;
use App\Services\AstroCardPromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
        // If the DB isn't migrated yet, fail fast with a helpful error.
        if (!Schema::hasColumn('users', 'astro_signature_json') || !Schema::hasColumn('users', 'astro_card_status')) {
            return response()->json([
                'status' => 'error',
                'error' => 'Serveur non à jour (migration Astro Card manquante).',
            ], 500);
        }

        $signature = $this->normalizeSignature($target);
        if ($signature === []) {
            return response()->json([
                'status' => 'error',
                'error' => 'Signature astro manquante. Renseigne ta date/heure/lieu de naissance puis recalcul la fiche astrale.',
            ], 400);
        }

        // Validate early so we don't enqueue jobs that will fail instantly.
        try {
            app(AstroCardPromptBuilder::class)->build($target, $signature);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
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
     * Returns a structured signature (canonical), trying in this order:
     * 1) users.astro_signature_json
     * 2) derived from astroProfile (and persisted back to users.astro_signature_json)
     *
     * @return array<string,mixed>
     */
    private function normalizeSignature(User $target): array
    {
        $sig = $target->astro_signature_json;
        if (is_array($sig) && $sig !== []) {
            return $sig;
        }

        $target->loadMissing('astroProfile');
        $p = $target->astroProfile;
        if (!$p) {
            return [];
        }

        $derived = [
            'sun_sign' => $p->western_sign ?? null,
            'ascendant' => $p->ascendant_sign ?? null,
            'chinese' => [
                'polarity' => $p->chinese_yin_yang ?? null,
                'element' => $p->chinese_element ?? null,
                'animal' => $p->chinese_animal ?? null,
            ],
            'life_path' => $p->life_path ?? null,
            'archetype' => $p->archetype ?? null,
            'talents' => (array) ($p->talents ?? []),
            'vigilance' => $p->weakness ?? null,
        ];

        // Persist for future calls (canonical source of truth).
        $target->forceFill([
            'astro_signature_json' => $derived,
        ])->save();

        return $derived;
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
