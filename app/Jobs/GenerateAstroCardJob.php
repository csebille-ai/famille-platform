<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Astro\Images\ImageProvider;
use App\Services\AstroCardPromptBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAstroCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId)
    {
    }

    public function handle(AstroCardPromptBuilder $builder, ImageProvider $images): void
    {
        $user = User::query()->find($this->userId);
        if (!$user) {
            return;
        }

        try {
            $signature = $user->astro_signature_json;
            if (!is_array($signature) || $signature === []) {
                throw new \RuntimeException('Signature astro manquante.');
            }

            $built = $builder->build($user, $signature);

            $generated = $images->generateImage($built['prompt'], [
                'aspect_ratio' => '2:3',
                'size' => '1024x1536',
                'seed' => $built['seed'],
            ]);

            $bytes = (string) ($generated['bytes'] ?? '');
            $mime = (string) ($generated['mime'] ?? 'image/png');
            $ext = (string) ($generated['ext'] ?? 'png');
            if ($bytes === '') {
                throw new \RuntimeException('Image provider: bytes vides.');
            }

            $key = sprintf('astro/cards/%d/tarot_modern-%s.%s', (int) $user->id, now()->format('YmdHis'), $ext);

            $disk = Storage::disk('r2');
            $disk->put($key, $bytes, [
                'visibility' => 'public',
                'ContentType' => $mime,
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            $url = (string) $disk->url($key);
            if (trim($url) === '') {
                $base = trim((string) config('uploads.r2_public_base_url'));
                if ($base !== '') {
                    $url = rtrim($base, '/') . '/' . ltrim($key, '/');
                }
            }

            $user->forceFill([
                'astro_card_status' => 'ready',
                'astro_card_image_url' => $url,
                'astro_card_prompt' => $built['prompt'],
                'astro_card_seed' => $built['seed'],
                'astro_card_generated_at' => now(),
                'astro_card_error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('GenerateAstroCardJob failed', [
                'user_id' => $this->userId,
                'exception' => $e,
            ]);

            $msg = $e->getMessage();
            if (mb_strlen($msg, 'UTF-8') > 2000) {
                $msg = mb_substr($msg, 0, 2000, 'UTF-8') . '…';
            }

            $user->forceFill([
                'astro_card_status' => 'error',
                'astro_card_error' => $msg,
            ])->save();
        }
    }
}
