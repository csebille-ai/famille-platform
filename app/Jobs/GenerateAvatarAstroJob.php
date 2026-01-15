<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Astro\Images\ImageProvider;
use App\Services\AvatarAstro\ArchetypeAndTraits;
use App\Services\AvatarAstro\AvatarAstroPromptBuilder;
use App\Services\AvatarAstro\AvatarSpecBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAvatarAstroJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId)
    {
    }

    public function handle(AvatarSpecBuilder $specBuilder, ArchetypeAndTraits $metaBuilder, AvatarAstroPromptBuilder $promptBuilder, ImageProvider $images): void
    {
        $user = User::query()->find($this->userId);
        if (!$user) {
            return;
        }

        try {
            $spec = $specBuilder->build($user);
            $meta = $metaBuilder->build($user, $spec);

            $built = $promptBuilder->build($user, $spec, $meta);
            $prompt = (string) ($built['prompt'] ?? '');
            $negative = (string) ($built['negative_prompt'] ?? '');
            $seed = (string) ($built['seed'] ?? '');

            if (trim($prompt) === '') {
                throw new \RuntimeException('Prompt avatar vide.');
            }

            $generated = $images->generateImage($prompt, [
                'aspect_ratio' => '1:1',
                'size' => '1024x1024',
                'seed' => $seed,
                'steps' => random_int(4, 7),
                'negative_prompt' => $negative !== '' ? $negative : null,
            ]);

            $bytes = (string) ($generated['bytes'] ?? '');
            if ($bytes === '') {
                throw new \RuntimeException('Image provider: bytes vides.');
            }

            $ts = now()->format('YmdHis');
            $uniq = $ts . '-' . bin2hex(random_bytes(3));
            $key = sprintf('astro/avatars/%d/avatar-astro-%s.png', (int) $user->id, $uniq);

            $disk = Storage::disk('r2');
            $disk->put($key, $bytes, [
                'visibility' => 'public',
                'ContentType' => 'image/png',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            $url = trim((string) $disk->url($key));
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                $base = trim((string) (config('filesystems.disks.r2.url') ?: config('uploads.r2_public_base_url')));
                if ($base !== '') {
                    $url = rtrim($base, '/') . '/' . ltrim($key, '/');
                }
            }

            $user->forceFill([
                'avatar_astro_status' => 'ready',
                'avatar_astro_error' => null,
                'avatar_image_url' => $url,
                'avatar_spec_json' => $spec,
                'avatar_archetype_title' => $meta['archetype_title'] ?? null,
                'avatar_traits_canon' => $meta['traits_canon'] ?? null,
                'avatar_traits_surannes' => $meta['traits_surannes'] ?? null,
                'avatar_version' => AvatarSpecBuilder::VERSION,
                'avatar_updated_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('GenerateAvatarAstroJob failed', [
                'user_id' => $this->userId,
                'exception' => $e,
            ]);

            $msg = $e->getMessage();
            if (mb_strlen($msg, 'UTF-8') > 2000) {
                $msg = mb_substr($msg, 0, 2000, 'UTF-8') . '…';
            }

            $user->forceFill([
                'avatar_astro_status' => 'error',
                'avatar_astro_error' => $msg,
            ])->save();
        }
    }
}
