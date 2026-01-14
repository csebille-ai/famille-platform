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
            // IMPORTANT: flux-1-schnell clamps prompt to 2048 chars.
            // Put variation at the top so it is never truncated away.
            $variation = $this->variationBlock($variationNonce);
            $promptCard = $variation . "\n\n" . ltrim((string) ($built['prompt_card'] ?? $built['prompt']));
            $promptIcon = $variation . "\n\n" . ltrim((string) ($built['prompt_icon'] ?? $built['prompt']));

            $seed = (string) $built['seed'] . '|' . $variationNonce;
            $steps = random_int(4, 7);

            $generatedCard = $images->generateImage($promptCard, [
                'aspect_ratio' => '2:3',
                'size' => '1024x1536',
                'seed' => $seed,
                'steps' => $steps,
                'negative_prompt' => $built['negative_prompt'] ?? null,
            ]);

            $cardBytes = (string) ($generatedCard['bytes'] ?? '');
            if ($cardBytes === '') {
                throw new \RuntimeException('Image provider (card): bytes vides.');
            }

            $generatedIcon = $images->generateImage($promptIcon, [
                'aspect_ratio' => '1:1',
                'size' => '1024x1024',
                'seed' => $seed . '|icon',
                'steps' => $steps,
                'negative_prompt' => $built['negative_prompt'] ?? null,
            ]);

            $iconBytes = (string) ($generatedIcon['bytes'] ?? '');
            if ($iconBytes === '') {
                // Icon is nice-to-have; don't fail the whole job.
                $iconBytes = $cardBytes;
            }

            $lifePath = (int) ($built['life_path'] ?? 0);
            $bannerText = (string) ($built['banner_text'] ?? '');

            $cardPngBytes = $this->postProcessCardPng($cardBytes, $lifePath, $bannerText);
            $iconPngBytes = $this->postProcessIconPng($iconBytes);

            $ts = now()->format('YmdHis');
            $uniq = $ts . '-' . bin2hex(random_bytes(3));
            $cardKey = sprintf('astro/cards/%d/blason-card-%s.png', (int) $user->id, $uniq);
            $iconKey = sprintf('astro/cards/%d/blason-icon-%s.png', (int) $user->id, $uniq);

            $disk = Storage::disk('r2');

            $disk->put($cardKey, $cardPngBytes, [
                'visibility' => 'public',
                'ContentType' => 'image/png',
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
                'astro_card_prompt' => $promptCard,
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

    private function postProcessCardPng(string $imageBytes, int $lifePath, string $bannerText): string
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Serveur: GD manquant (imagecreatefromstring indisponible).');
        }

        $src = @imagecreatefromstring($imageBytes);
        if (!$src) {
            throw new \RuntimeException("Impossible de décoder l'image (card)." );
        }

        $w = (int) imagesx($src);
        $h = (int) imagesy($src);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($src);
            throw new \RuntimeException("Image invalide (card)." );
        }

        $padded = $this->safePadImage($src, $w, $h, 0.92);
        imagedestroy($src);

        // Overlay: life path number (medallion) + banner text.
        $font = $this->findTtfFontPath();
        $lifeText = trim((string) $lifePath);
        $bannerText = trim(preg_replace('/\s+/u', ' ', $bannerText) ?? '');

        $white = imagecolorallocate($padded, 255, 255, 255);
        $shadow = imagecolorallocatealpha($padded, 0, 0, 0, 70);

        // Medallion center: bottom-middle.
        if ($lifeText !== '' && $lifeText !== '0') {
            $cx = (int) round($w * 0.50);
            $cy = (int) round($h * 0.82);
            $fontSize = (int) round(min($w, $h) * 0.085);
            $this->drawCenteredText($padded, $lifeText, $cx, $cy, $fontSize, $white, $shadow, $font);
        }

        // Banner text: near the bottom.
        if ($bannerText !== '') {
            $maxWidth = (int) round($w * 0.76);
            $baseSize = (int) round($w * 0.050);
            $lines = $this->wrapTextTwoLines($bannerText, $maxWidth, $baseSize, $font);

            if (count($lines) === 1) {
                $this->drawCenteredText($padded, $lines[0], (int) round($w * 0.50), (int) round($h * 0.935), $baseSize, $white, $shadow, $font);
            } else {
                $lineGap = (int) round($baseSize * 1.15);
                $yMid = (int) round($h * 0.935);
                $this->drawCenteredText($padded, $lines[0], (int) round($w * 0.50), $yMid - (int) round($lineGap * 0.55), $baseSize, $white, $shadow, $font);
                $this->drawCenteredText($padded, $lines[1], (int) round($w * 0.50), $yMid + (int) round($lineGap * 0.55), $baseSize, $white, $shadow, $font);
            }
        }

        return $this->encodePng($padded);
    }

    private function postProcessIconPng(string $imageBytes): string
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Serveur: GD manquant (imagecreatefromstring indisponible).');
        }

        $src = @imagecreatefromstring($imageBytes);
        if (!$src) {
            throw new \RuntimeException("Impossible de décoder l'image (icon)." );
        }

        $w = (int) imagesx($src);
        $h = (int) imagesy($src);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($src);
            throw new \RuntimeException("Image invalide (icon)." );
        }

        // Square-safe padding: keep everything fully visible.
        $padded = $this->safePadImage($src, $w, $h, 0.90);
        imagedestroy($src);

        return $this->encodePng($padded);
    }

    /**
     * Scale down and center onto same-size canvas to guarantee margins.
     *
     * @param \GdImage $src
     * @return \GdImage
     */
    private function safePadImage($src, int $w, int $h, float $scale)
    {
        $scale = max(0.50, min(0.98, $scale));

        $dst = imagecreatetruecolor($w, $h);
        if (!$dst) {
            throw new \RuntimeException('Impossible de créer le canvas pour padding.');
        }

        // Fill with a background sampled from top-left pixel.
        $rgb = imagecolorat($src, 0, 0);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $bg = imagecolorallocate($dst, $r, $g, $b);
        imagefilledrectangle($dst, 0, 0, $w, $h, $bg);

        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $dx = (int) floor(($w - $nw) / 2);
        $dy = (int) floor(($h - $nh) / 2);

        $ok = imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $nw, $nh, $w, $h);
        if (!$ok) {
            imagedestroy($dst);
            throw new \RuntimeException('Impossible de padding/resample l\'image.');
        }

        return $dst;
    }

    private function encodePng($img): string
    {
        ob_start();
        imagepng($img, null, 8);
        imagedestroy($img);
        $png = ob_get_clean();

        if (!is_string($png) || $png === '') {
            throw new \RuntimeException('Impossible d\'encoder l\'image en PNG.');
        }

        return $png;
    }

    private function findTtfFontPath(): ?string
    {
        $candidates = [
            // Common on Linux hosts.
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
        ];

        foreach ($candidates as $path) {
            if (@is_file($path) && @is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function drawCenteredText($img, string $text, int $cx, int $cy, int $fontSize, int $color, int $shadowColor, ?string $fontPath): void
    {
        $fontSize = max(10, $fontSize);
        $text = trim($text);
        if ($text === '') {
            return;
        }

        if ($fontPath && function_exists('imagettfbbox') && function_exists('imagettftext')) {
            $box = imagettfbbox($fontSize, 0, $fontPath, $text);
            if (is_array($box)) {
                $minX = min($box[0], $box[2], $box[4], $box[6]);
                $maxX = max($box[0], $box[2], $box[4], $box[6]);
                $minY = min($box[1], $box[3], $box[5], $box[7]);
                $maxY = max($box[1], $box[3], $box[5], $box[7]);
                $tw = (int) ($maxX - $minX);
                $th = (int) ($maxY - $minY);

                $x = (int) round($cx - ($tw / 2) - $minX);
                $y = (int) round($cy + ($th / 2) - $maxY);

                // Shadow then text.
                imagettftext($img, $fontSize, 0, $x + 2, $y + 2, $shadowColor, $fontPath, $text);
                imagettftext($img, $fontSize, 0, $x, $y, $color, $fontPath, $text);
                return;
            }
        }

        // Fallback (ASCII only).
        $ascii = (string) \Illuminate\Support\Str::of($text)->ascii();
        $font = 5;
        $tw = imagefontwidth($font) * strlen($ascii);
        $th = imagefontheight($font);
        $x = (int) round($cx - ($tw / 2));
        $y = (int) round($cy - ($th / 2));
        imagestring($img, $font, $x + 1, $y + 1, $ascii, $shadowColor);
        imagestring($img, $font, $x, $y, $ascii, $color);
    }

    /**
     * @return list<string>
     */
    private function wrapTextTwoLines(string $text, int $maxWidth, int $fontSize, ?string $fontPath): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // If it already fits, keep one line.
        if ($this->measureTextWidth($text, $fontSize, $fontPath) <= $maxWidth) {
            return [$text];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        if (count($words) < 2) {
            return [$text];
        }

        // Try best split point.
        $best = [$text];
        $bestScore = PHP_INT_MAX;
        for ($i = 1; $i < count($words); $i++) {
            $l1 = trim(implode(' ', array_slice($words, 0, $i)));
            $l2 = trim(implode(' ', array_slice($words, $i)));
            if ($l1 === '' || $l2 === '') {
                continue;
            }
            $w1 = $this->measureTextWidth($l1, $fontSize, $fontPath);
            $w2 = $this->measureTextWidth($l2, $fontSize, $fontPath);
            $overflow = max(0, $w1 - $maxWidth) + max(0, $w2 - $maxWidth);
            $balance = abs($w1 - $w2);
            $score = ($overflow * 1000) + $balance;

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = [$l1, $l2];
            }
        }

        // If both lines still overflow, shrink a bit by truncating the longest.
        if (count($best) === 2) {
            [$l1, $l2] = $best;
            $l1 = $this->truncateToWidth($l1, $maxWidth, $fontSize, $fontPath);
            $l2 = $this->truncateToWidth($l2, $maxWidth, $fontSize, $fontPath);
            return [$l1, $l2];
        }

        return $best;
    }

    private function measureTextWidth(string $text, int $fontSize, ?string $fontPath): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        if ($fontPath && function_exists('imagettfbbox')) {
            $box = imagettfbbox($fontSize, 0, $fontPath, $text);
            if (is_array($box)) {
                $minX = min($box[0], $box[2], $box[4], $box[6]);
                $maxX = max($box[0], $box[2], $box[4], $box[6]);
                return (int) ($maxX - $minX);
            }
        }

        $ascii = (string) \Illuminate\Support\Str::of($text)->ascii();
        return imagefontwidth(5) * strlen($ascii);
    }

    private function truncateToWidth(string $text, int $maxWidth, int $fontSize, ?string $fontPath): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if ($this->measureTextWidth($text, $fontSize, $fontPath) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '…';
        $t = $text;
        while (mb_strlen($t, 'UTF-8') > 3) {
            $t = mb_substr($t, 0, mb_strlen($t, 'UTF-8') - 1, 'UTF-8');
            $candidate = rtrim($t) . $ellipsis;
            if ($this->measureTextWidth($candidate, $fontSize, $fontPath) <= $maxWidth) {
                return $candidate;
            }
        }

        return $text;
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
