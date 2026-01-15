<?php

namespace App\Http\Controllers\Api;

use App\Jobs\GenerateAvatarAstroJob;
use App\Models\User;
use App\Services\AvatarAstro\ArchetypeAndTraits;
use App\Services\AvatarAstro\AvatarAstroPromptBuilder;
use App\Services\AvatarAstro\AvatarSpecBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class AvatarAstroController
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
        abort_unless($request->user()?->can('manage-users') === true, 403);

        return $this->doGenerate($request, $user);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json($this->statusPayload($user, false));
    }

    public function statusForUser(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user() !== null, 401);
        abort_unless($request->user()?->can('manage-users') === true, 403);

        return response()->json($this->statusPayload($user, true));
    }

    private function doGenerate(Request $request, User $target): JsonResponse
    {
        if (!Schema::hasColumn('users', 'avatar_image_url')
            || !Schema::hasColumn('users', 'avatar_spec_json')
            || !Schema::hasColumn('users', 'avatar_version')
            || !Schema::hasColumn('users', 'avatar_updated_at')
            || !Schema::hasColumn('users', 'avatar_astro_status')) {
            return response()->json([
                'status' => 'error',
                'error' => 'Serveur non à jour (migration Avatar Astro manquante).',
            ], 500);
        }

        // Validate early: can we build a spec + prompt?
        try {
            $spec = app(AvatarSpecBuilder::class)->build($target);
            $meta = app(ArchetypeAndTraits::class)->build($target, $spec);
            app(AvatarAstroPromptBuilder::class)->build($target, $spec, $meta);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 400);
        }

        if (($target->avatar_astro_status ?? null) === 'pending') {
            return response()->json(['status' => 'pending'], 202);
        }

        // Rate limit (global) + daily limit (per target) for non-admin.
        $key = 'avatar-astro-generate:' . (string) ($request->user()?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'status' => 'error',
                'error' => 'Réessaie plus tard (limite).',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $force = (bool) $request->boolean('force');
        $actorIsAdmin = ($request->user()?->role ?? 'member') === 'admin';
        if (!$force && !$actorIsAdmin && $target->avatar_updated_at && $target->avatar_updated_at->gt(now()->subDay())) {
            return response()->json([
                'status' => 'error',
                'error' => 'Réessaie plus tard (limite quotidienne).',
            ], 429);
        }

        $target->forceFill([
            'avatar_astro_status' => 'pending',
            'avatar_astro_error' => null,
        ])->save();

        GenerateAvatarAstroJob::dispatch((int) $target->id);

        return response()->json(['status' => 'pending'], 202);
    }

    /**
     * @return array{status:mixed,image_url:mixed,image_display_url:mixed,updated_at:?string,error:mixed,overlay:array<string,mixed>|null}
     */
    private function statusPayload(User $user, bool $forAdminUser): array
    {
        $displayUrl = null;
        try {
            if (!empty($user->avatar_image_url)) {
                $displayUrl = $forAdminUser
                    ? route('avatar.astro.imageForUser', $user)
                    : route('avatar.astro.image');
            }
        } catch (\Throwable) {
            $displayUrl = null;
        }

        // UI overlay data is derived from stored spec + suranné traits (reliable; not AI).
        $overlay = null;
        try {
            $spec = is_array($user->avatar_spec_json ?? null) ? (array) $user->avatar_spec_json : [];
            if ($spec !== []) {
                $overlay = [
                    'sun_element' => $spec['sun_element'] ?? null,
                    'chinese_animal' => $spec['chinese_animal'] ?? null,
                    'life_path' => $spec['life_path'] ?? null,
                    'archetype_title' => $user->avatar_archetype_title ?? null,
                    'traits_surannes' => $user->avatar_traits_surannes ?? null,
                ];
            }
        } catch (\Throwable) {
            $overlay = null;
        }

        return [
            'status' => $user->avatar_astro_status,
            'image_url' => $user->avatar_image_url,
            'image_display_url' => $displayUrl,
            'updated_at' => optional($user->avatar_updated_at)->toISOString(),
            'error' => $user->avatar_astro_error,
            'overlay' => $overlay,
        ];
    }
}
