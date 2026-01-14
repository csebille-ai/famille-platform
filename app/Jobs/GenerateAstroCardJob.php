<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Astro\Images\ImageProvider;
use App\Services\AstroCardPromptBuilder;
use Illuminate\Support\Str;
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
            $sunSign = (string) ($built['sun_sign'] ?? '');

            $cardPngBytes = $this->postProcessCardPng($cardBytes, $sunSign, $lifePath, $bannerText);
            $iconPngBytes = $this->postProcessIconPng($iconBytes, $sunSign);

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

    private function postProcessCardPng(string $imageBytes, string $sunSign, int $lifePath, string $bannerText): string
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

        // Overlay: sun sign pictogram (shield) + life path number (medallion) + banner text.
        $font = $this->findTtfFontPath();
        $lifeText = trim((string) $lifePath);
        $bannerText = trim(preg_replace('/\s+/u', ' ', $bannerText) ?? '');

        $sunKey = $this->key($sunSign);
        if ($sunKey !== '') {
            $this->overlaySunSignOnShield($padded, $w, $h, $sunKey);
        }

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

    private function postProcessIconPng(string $imageBytes, string $sunSign): string
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

        $sunKey = $this->key($sunSign);
        if ($sunKey !== '') {
            $this->overlaySunSignOnShield($padded, $w, $h, $sunKey);
        }

        return $this->encodePng($padded);
    }

    private function overlaySunSignOnShield($img, int $w, int $h, string $sunKey): void
    {
        // Approximate shield box (works with our constrained composition).
        $left = (int) round($w * 0.34);
        $right = (int) round($w * 0.66);
        $top = (int) round($h * 0.24);
        $bottom = (int) round($h * 0.66);

        $cx = (int) round(($left + $right) / 2);
        $cy = (int) round(($top + $bottom) / 2);
        $bw = max(1, $right - $left);
        $bh = max(1, $bottom - $top);

        // Draw a subtle white wash behind glyph to cover model drift.
        $wash = imagecolorallocatealpha($img, 255, 255, 255, 55);
        imagefilledellipse($img, $cx, $cy, (int) round($bw * 0.78), (int) round($bh * 0.62), $wash);

        $stroke = imagecolorallocate($img, 10, 15, 25);
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, 95);

        $thickness = (int) max(3, round(min($bw, $bh) * 0.035));
        $this->drawZodiacGlyph($img, $sunKey, $cx, $cy, (int) round($bw * 0.70), (int) round($bh * 0.55), $stroke, $shadow, $thickness);
    }

    private function drawZodiacGlyph($img, string $sunKey, int $cx, int $cy, int $gw, int $gh, int $stroke, int $shadow, int $thickness): void
    {
        $gw = max(40, $gw);
        $gh = max(40, $gh);

        $draw = function (callable $fn) use ($img, $shadow, $stroke, $thickness) {
            imagesetthickness($img, $thickness);
            // shadow pass
            $fn(2, 2, $shadow);
            // stroke pass
            $fn(0, 0, $stroke);
            imagesetthickness($img, 1);
        };

        $k = $sunKey;

        // Normalize common french keys.
        if ($k === 'belier') $k = 'aries';
        if ($k === 'taureau') $k = 'taurus';
        if ($k === 'gemeaux') $k = 'gemini';
        if ($k === 'vierge') $k = 'virgo';
        if ($k === 'balance') $k = 'libra';
        if ($k === 'scorpion') $k = 'scorpio';
        if ($k === 'sagittaire') $k = 'sagittarius';
        if ($k === 'capricorne') $k = 'capricorn';
        if ($k === 'verseau') $k = 'aquarius';
        if ($k === 'poissons') $k = 'pisces';

        // Fallback: try mapping via prompt's expected set.
        $allowed = ['aries','taurus','gemini','cancer','leo','virgo','libra','scorpio','sagittarius','capricorn','aquarius','pisces'];
        if (!in_array($k, $allowed, true)) {
            $k = 'taurus';
        }

        $x0 = (int) round($cx - ($gw / 2));
        $x1 = (int) round($cx + ($gw / 2));
        $y0 = (int) round($cy - ($gh / 2));
        $y1 = (int) round($cy + ($gh / 2));

        $draw(function (int $dx, int $dy, int $col) use ($img, $k, $gw, $gh, $x0, $x1, $y0, $y1) {
            // Avoid PHP capture warnings by recomputing center.
            $cxx = (int) round(($x0 + $x1) / 2) + $dx;
            $cyy = (int) round(($y0 + $y1) / 2) + $dy;
            $x0d = $x0 + $dx;
            $x1d = $x1 + $dx;
            $y0d = $y0 + $dy;
            $y1d = $y1 + $dy;

            switch ($k) {
                case 'taurus':
                    // Bull head + horns.
                    imageellipse($img, $cxx, (int) round($cyy + $gh * 0.10), (int) round($gw * 0.48), (int) round($gh * 0.42), $col);
                    imagearc($img, (int) round($cxx - $gw * 0.18), (int) round($cyy - $gh * 0.05), (int) round($gw * 0.34), (int) round($gh * 0.34), 210, 360, $col);
                    imagearc($img, (int) round($cxx + $gw * 0.18), (int) round($cyy - $gh * 0.05), (int) round($gw * 0.34), (int) round($gh * 0.34), 180, 330, $col);
                    break;

                case 'capricorn':
                    // Stylized sea-goat: horn + spine + tail.
                    $hx = (int) round($cxx - $gw * 0.10);
                    $hy = (int) round($cyy - $gh * 0.18);
                    imagearc($img, $hx, $hy, (int) round($gw * 0.40), (int) round($gh * 0.55), 230, 40, $col);
                    imageline($img, (int) round($cxx - $gw * 0.10), (int) round($cyy - $gh * 0.05), (int) round($cxx - $gw * 0.10), (int) round($cyy + $gh * 0.20), $col);
                    imageline($img, (int) round($cxx - $gw * 0.10), (int) round($cyy + $gh * 0.20), (int) round($cxx + $gw * 0.12), (int) round($cyy + $gh * 0.05), $col);
                    imagearc($img, (int) round($cxx + $gw * 0.18), (int) round($cyy + $gh * 0.05), (int) round($gw * 0.35), (int) round($gh * 0.35), 20, 250, $col);
                    imagearc($img, (int) round($cxx + $gw * 0.28), (int) round($cyy + $gh * 0.15), (int) round($gw * 0.30), (int) round($gh * 0.30), 200, 20, $col);
                    break;

                case 'pisces':
                    // Two fish arcs + connecting line.
                    imagearc($img, (int) round($cxx - $gw * 0.18), $cyy, (int) round($gw * 0.45), (int) round($gh * 0.75), 300, 60, $col);
                    imagearc($img, (int) round($cxx + $gw * 0.18), $cyy, (int) round($gw * 0.45), (int) round($gh * 0.75), 120, 240, $col);
                    imageline($img, (int) round($cxx - $gw * 0.10), $cyy, (int) round($cxx + $gw * 0.10), $cyy, $col);
                    break;

                case 'virgo':
                    // Virgo: simplified wheat + loop.
                    imageline($img, (int) round($cxx - $gw * 0.20), (int) round($cyy - $gh * 0.20), (int) round($cxx - $gw * 0.20), (int) round($cyy + $gh * 0.22), $col);
                    imageline($img, (int) round($cxx - $gw * 0.02), (int) round($cyy - $gh * 0.20), (int) round($cxx - $gw * 0.02), (int) round($cyy + $gh * 0.22), $col);
                    imageline($img, (int) round($cxx + $gw * 0.16), (int) round($cyy - $gh * 0.10), (int) round($cxx + $gw * 0.16), (int) round($cyy + $gh * 0.22), $col);
                    imagearc($img, (int) round($cxx + $gw * 0.20), (int) round($cyy + $gh * 0.10), (int) round($gw * 0.30), (int) round($gh * 0.35), 220, 20, $col);
                    // small wheat ticks
                    for ($i = -2; $i <= 2; $i++) {
                        $yy = (int) round($cyy - $gh * 0.10 + ($i * $gh * 0.06));
                        imageline($img, (int) round($cxx - $gw * 0.20), $yy, (int) round($cxx - $gw * 0.28), (int) round($yy - $gh * 0.03), $col);
                    }
                    break;

                case 'aries':
                    // Ram horns.
                    imagearc($img, (int) round($cxx - $gw * 0.16), (int) round($cyy - $gh * 0.02), (int) round($gw * 0.40), (int) round($gh * 0.55), 230, 30, $col);
                    imagearc($img, (int) round($cxx + $gw * 0.16), (int) round($cyy - $gh * 0.02), (int) round($gw * 0.40), (int) round($gh * 0.55), 150, 310, $col);
                    imageline($img, (int) round($cxx - $gw * 0.02), (int) round($cyy - $gh * 0.05), (int) round($cxx - $gw * 0.02), (int) round($cyy + $gh * 0.18), $col);
                    imageline($img, (int) round($cxx + $gw * 0.02), (int) round($cyy - $gh * 0.05), (int) round($cxx + $gw * 0.02), (int) round($cyy + $gh * 0.18), $col);
                    break;

                case 'gemini':
                    // Two pillars + caps.
                    imageline($img, (int) round($cxx - $gw * 0.14), (int) round($cyy - $gh * 0.25), (int) round($cxx - $gw * 0.14), (int) round($cyy + $gh * 0.25), $col);
                    imageline($img, (int) round($cxx + $gw * 0.14), (int) round($cyy - $gh * 0.25), (int) round($cxx + $gw * 0.14), (int) round($cyy + $gh * 0.25), $col);
                    imagearc($img, $cxx, (int) round($cyy - $gh * 0.25), (int) round($gw * 0.55), (int) round($gh * 0.20), 0, 180, $col);
                    imagearc($img, $cxx, (int) round($cyy + $gh * 0.25), (int) round($gw * 0.55), (int) round($gh * 0.20), 180, 360, $col);
                    break;

                case 'cancer':
                    // Two opposing crescents.
                    imagearc($img, (int) round($cxx - $gw * 0.10), (int) round($cyy - $gh * 0.05), (int) round($gw * 0.50), (int) round($gh * 0.50), 40, 220, $col);
                    imagearc($img, (int) round($cxx + $gw * 0.10), (int) round($cyy + $gh * 0.05), (int) round($gw * 0.50), (int) round($gh * 0.50), 220, 40, $col);
                    break;

                case 'leo':
                    // Mane-like loop + tail.
                    imagearc($img, (int) round($cxx - $gw * 0.05), (int) round($cyy - $gh * 0.05), (int) round($gw * 0.55), (int) round($gh * 0.55), 40, 360, $col);
                    imageline($img, (int) round($cxx + $gw * 0.20), (int) round($cyy + $gh * 0.05), (int) round($cxx + $gw * 0.28), (int) round($cyy + $gh * 0.18), $col);
                    imagearc($img, (int) round($cxx + $gw * 0.32), (int) round($cyy + $gh * 0.22), (int) round($gw * 0.25), (int) round($gh * 0.25), 180, 360, $col);
                    break;

                case 'libra':
                    // Horizon + arch.
                    imageline($img, (int) round($cxx - $gw * 0.28), (int) round($cyy + $gh * 0.12), (int) round($cxx + $gw * 0.28), (int) round($cyy + $gh * 0.12), $col);
                    imagearc($img, $cxx, (int) round($cyy + $gh * 0.12), (int) round($gw * 0.60), (int) round($gh * 0.45), 180, 360, $col);
                    imageline($img, (int) round($cxx - $gw * 0.28), (int) round($cyy - $gh * 0.02), (int) round($cxx + $gw * 0.28), (int) round($cyy - $gh * 0.02), $col);
                    break;

                case 'scorpio':
                    // M-like with stinger.
                    imageline($img, (int) round($cxx - $gw * 0.22), (int) round($cyy - $gh * 0.22), (int) round($cxx - $gw * 0.22), (int) round($cyy + $gh * 0.22), $col);
                    imageline($img, (int) round($cxx - $gw * 0.22), (int) round($cyy + $gh * 0.05), (int) round($cxx - $gw * 0.02), (int) round($cyy + $gh * 0.22), $col);
                    imageline($img, (int) round($cxx - $gw * 0.02), (int) round($cyy + $gh * 0.22), (int) round($cxx + $gw * 0.12), (int) round($cyy + $gh * 0.05), $col);
                    imageline($img, (int) round($cxx + $gw * 0.12), (int) round($cyy - $gh * 0.22), (int) round($cxx + $gw * 0.12), (int) round($cyy + $gh * 0.12), $col);
                    imageline($img, (int) round($cxx + $gw * 0.12), (int) round($cyy + $gh * 0.12), (int) round($cxx + $gw * 0.22), (int) round($cyy + $gh * 0.22), $col);
                    imageline($img, (int) round($cxx + $gw * 0.22), (int) round($cyy + $gh * 0.22), (int) round($cxx + $gw * 0.22), (int) round($cyy + $gh * 0.10), $col);
                    // arrow head
                    imageline($img, (int) round($cxx + $gw * 0.22), (int) round($cyy + $gh * 0.10), (int) round($cxx + $gw * 0.30), (int) round($cyy + $gh * 0.12), $col);
                    imageline($img, (int) round($cxx + $gw * 0.22), (int) round($cyy + $gh * 0.10), (int) round($cxx + $gw * 0.26), (int) round($cyy + $gh * 0.02), $col);
                    break;

                case 'sagittarius':
                    // Arrow.
                    imageline($img, (int) round($cxx - $gw * 0.25), (int) round($cyy + $gh * 0.20), (int) round($cxx + $gw * 0.25), (int) round($cyy - $gh * 0.20), $col);
                    imageline($img, (int) round($cxx + $gw * 0.16), (int) round($cyy - $gh * 0.26), (int) round($cxx + $gw * 0.25), (int) round($cyy - $gh * 0.20), $col);
                    imageline($img, (int) round($cxx + $gw * 0.25), (int) round($cyy - $gh * 0.20), (int) round($cxx + $gw * 0.19), (int) round($cyy - $gh * 0.12), $col);
                    imageline($img, (int) round($cxx - $gw * 0.08), (int) round($cyy - $gh * 0.02), (int) round($cxx - $gw * 0.08), (int) round($cyy - $gh * 0.22), $col);
                    imageline($img, (int) round($cxx - $gw * 0.18), (int) round($cyy - $gh * 0.02), (int) round($cxx + $gw * 0.02), (int) round($cyy - $gh * 0.02), $col);
                    break;

                case 'aquarius':
                    // Two waves.
                    for ($row = 0; $row < 2; $row++) {
                        $yy = (int) round($cyy - $gh * 0.10 + ($row * $gh * 0.20));
                        $step = (int) max(6, round($gw / 6));
                        $amp = (int) round($gh * 0.06);
                        $px = (int) round($cxx - $gw * 0.30);
                        $py = $yy;
                        for ($x = (int) round($cxx - $gw * 0.30); $x <= (int) round($cxx + $gw * 0.30); $x += $step) {
                            $phase = ($x - (int) round($cxx - $gw * 0.30)) / max(1, (int) ($gw * 0.60));
                            $y = (int) round($yy + sin($phase * M_PI * 2) * $amp);
                            imageline($img, $px, $py, $x, $y, $col);
                            $px = $x;
                            $py = $y;
                        }
                    }
                    break;
            }
        });
    }

    private function key(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }

        $v = (string) Str::of($v)->lower()->ascii();
        $v = (string) preg_replace('/[^a-z0-9]+/', '', $v);

        return $v;
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
