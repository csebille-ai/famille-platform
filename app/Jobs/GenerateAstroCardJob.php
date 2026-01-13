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

            // Workers AI flux-1-schnell can be effectively deterministic for identical inputs.
            // Add a small, safe variation block (no text rendering) + a unique seed fragment.
            $variationNonce = bin2hex(random_bytes(4));
            $prompt = rtrim($built['prompt']) . "\n\n" . $this->variationBlock($variationNonce);
            $seed = (string) $built['seed'] . '|' . $variationNonce;
            $steps = random_int(4, 7);

            $generated = $images->generateImage($prompt, [
                'aspect_ratio' => '2:3',
                'size' => '1024x1536',
                'seed' => $seed,
                'steps' => $steps,
                'negative_prompt' => $built['negative_prompt'] ?? null,
            ]);

            $bytes = (string) ($generated['bytes'] ?? '');
            $mime = (string) ($generated['mime'] ?? 'image/png');
            $ext = (string) ($generated['ext'] ?? 'png');
            if ($bytes === '') {
                throw new \RuntimeException('Image provider: bytes vides.');
            }

            $iconPngBytes = $this->makeSquareIconPng($bytes, 1024);

            $ts = now()->format('YmdHis');
            $uniq = $ts . '-' . bin2hex(random_bytes(3));
            $cardKey = sprintf('astro/cards/%d/blason-card-%s.%s', (int) $user->id, $uniq, $ext);
            $iconKey = sprintf('astro/cards/%d/blason-icon-%s.png', (int) $user->id, $uniq);

            $disk = Storage::disk('r2');

            $disk->put($cardKey, $bytes, [
                'visibility' => 'public',
                'ContentType' => $mime,
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            $disk->put($iconKey, $iconPngBytes, [
                'visibility' => 'public',
                'ContentType' => 'image/png',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            $cardUrl = trim((string) $disk->url($cardKey));
            $iconUrl = trim((string) $disk->url($iconKey));

            // Prefer the disk URL if it's already absolute.
            if ($cardUrl === '' || !preg_match('#^https?://#i', $cardUrl)) {
                $base = trim((string) (config('filesystems.disks.r2.url') ?: config('uploads.r2_public_base_url')));
                if ($base !== '') {
                    $cardUrl = rtrim($base, '/') . '/' . ltrim($cardKey, '/');
                }
            }

            if ($iconUrl === '' || !preg_match('#^https?://#i', $iconUrl)) {
                $base = trim((string) (config('filesystems.disks.r2.url') ?: config('uploads.r2_public_base_url')));
                if ($base !== '') {
                    $iconUrl = rtrim($base, '/') . '/' . ltrim($iconKey, '/');
                }
            }

            $user->forceFill([
                'astro_card_status' => 'ready',
                'astro_card_image_url' => $cardUrl,
                'astro_card_icon_url' => $iconUrl,
                'astro_card_prompt' => $prompt,
                'astro_card_seed' => $seed,
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

    private function makeSquareIconPng(string $imageBytes, int $targetSize): string
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Serveur: GD manquant (imagecreatefromstring indisponible).');
        }

        $src = @imagecreatefromstring($imageBytes);
        if (!$src) {
            throw new \RuntimeException("Impossible de décoder l'image pour créer l'icône.");
        }

        $w = (int) imagesx($src);
        $h = (int) imagesy($src);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($src);
            throw new \RuntimeException("Image invalide pour créer l'icône.");
        }

        $side = min($w, $h);
        $srcX = (int) floor(($w - $side) / 2);
        $srcY = (int) floor(($h - $side) / 2);

        $dst = imagecreatetruecolor($targetSize, $targetSize);
        if (!$dst) {
            imagedestroy($src);
            throw new \RuntimeException("Impossible de créer le canvas de l'icône.");
        }

        // Preserve alpha for PNG.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $targetSize, $targetSize, $transparent);

        $ok = imagecopyresampled(
            $dst,
            $src,
            0,
            0,
            $srcX,
            $srcY,
            $targetSize,
            $targetSize,
            $side,
            $side
        );
        imagedestroy($src);

        if (!$ok) {
            imagedestroy($dst);
            throw new \RuntimeException("Impossible de redimensionner l'icône.");
        }

        ob_start();
        imagepng($dst, null, 8);
        imagedestroy($dst);
        $png = ob_get_clean();

        if (!is_string($png) || $png === '') {
            throw new \RuntimeException("Impossible d'encoder l'icône en PNG.");
        }

        return $png;
    }

    private function variationBlock(string $nonce): string
    {
        // Keep this short to avoid prompt clamping (flux-1-schnell max 2048).
        $crowns = [
            'simple laurel crown',
            'fleur-de-lys crown',
            'rounded jewel crown',
            'minimal modern crown',
        ];
        $borders = [
            'thin double outline border',
            'subtle dotted border',
            'clean beveled border',
            'soft ribbon frame border',
        ];
        $palettes = [
            'warm gold + teal accents',
            'soft pastel enamel accents',
            'royal blue + gold accents',
            'coral + mint accents',
        ];

        $crown = $crowns[array_rand($crowns)];
        $border = $borders[array_rand($borders)];
        $palette = $palettes[array_rand($palettes)];

        return implode("\n", [
            'VARIATION (must NOT appear as text in the image):',
            "- crown style: {$crown}",
            "- border style: {$border}",
            "- accent palette: {$palette}",
            "- variation id: {$nonce} (internal, do NOT render)",
        ]);
    }
}
