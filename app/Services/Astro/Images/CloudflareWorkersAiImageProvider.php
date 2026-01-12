<?php

namespace App\Services\Astro\Images;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class CloudflareWorkersAiImageProvider implements ImageProvider
{
    public function generateImage(string $prompt, array $opts = []): array
    {
        $accountId = trim((string) config('services.cloudflare.account_id'));
        $token = trim((string) config('services.cloudflare.api_token'));
        $baseUrl = rtrim((string) config('services.cloudflare.ai_base_url', 'https://api.cloudflare.com/client/v4'), '/');
        $model = (string) config('services.cloudflare.ai_image_model', '@cf/stabilityai/stable-diffusion-xl-base-1.0');

        if ($accountId === '') {
            throw new \RuntimeException('CLOUDFLARE_ACCOUNT_ID manquant');
        }
        if ($token === '') {
            throw new \RuntimeException('CLOUDFLARE_API_TOKEN manquant');
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new \InvalidArgumentException('Prompt vide.');
        }

        // Workers AI models are identified like @cf/... and are part of the URL path.
        // IMPORTANT: do NOT URL-encode slashes, otherwise Cloudflare may not match the route.
        $modelEncoded = $this->encodeModelForPath($model);
        $url = "{$baseUrl}/accounts/{$accountId}/ai/run/{$modelEncoded}";

        // Size handling: Workers AI commonly uses width/height.
        $size = (string) ($opts['size'] ?? '1024x1536');
        [$w, $h] = $this->parseSize($size);

        $seed = $opts['seed'] ?? null;
        $seedInt = null;
        if (is_string($seed) && $seed !== '') {
            // Derive a stable int seed from string hash.
            $seedInt = hexdec(substr(sha1($seed), 0, 8));
        }

        $payload = [
            'prompt' => $prompt,
            'width' => $w,
            'height' => $h,
        ];

        $negative = $opts['negative_prompt'] ?? null;
        if (is_string($negative) && trim($negative) !== '') {
            $payload['negative_prompt'] = trim($negative);
        }

        if ($seedInt !== null) {
            $payload['seed'] = $seedInt;
        }

        try {
            $resp = Http::timeout(120)
                ->retry(1, 250)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json, image/*',
                ])
                ->post($url, $payload)
                ->throw();
        } catch (RequestException $e) {
            $msg = $e->response?->json('errors.0.message')
                ?? $e->response?->json('error.message')
                ?? $e->getMessage();
            $code = $e->response?->status();
            $prefix = $code ? "Erreur Cloudflare Workers AI ({$code}): " : 'Erreur Cloudflare Workers AI: ';
            throw new \RuntimeException($prefix . (string) $msg, previous: $e);
        }

        $contentType = strtolower((string) $resp->header('Content-Type'));
        $body = $resp->body();

        // Some Workers AI responses can be raw image bytes.
        if (str_starts_with($contentType, 'image/')) {
            $ext = $this->extFromMime($contentType);
            return [
                'bytes' => $body,
                'mime' => $contentType,
                'ext' => $ext,
            ];
        }

        // Otherwise, try JSON shapes that contain base64 image.
        $json = $resp->json();
        $b64 = null;

        if (is_array($json)) {
            $b64 = $json['result']['image'] ?? null;
            if (!is_string($b64)) {
                // Some models return an array of images.
                $b64 = $json['result'][0]['image'] ?? null;
            }
        }

        if (!is_string($b64) || trim($b64) === '') {
            throw new \RuntimeException('Cloudflare Workers AI: réponse image invalide.');
        }

        $bytes = base64_decode($b64, true);
        if (!is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('Cloudflare Workers AI: base64 invalide.');
        }

        return [
            'bytes' => $bytes,
            'mime' => 'image/png',
            'ext' => 'png',
        ];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseSize(string $size): array
    {
        $size = trim($size);
        if (preg_match('/^(\d{2,4})x(\d{2,4})$/', $size, $m) === 1) {
            $w = (int) $m[1];
            $h = (int) $m[2];
            return [max(256, min(2048, $w)), max(256, min(2048, $h))];
        }

        // Fallback 2:3-ish.
        return [1024, 1536];
    }

    private function extFromMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    private function encodeModelForPath(string $model): string
    {
        $model = trim($model);
        if ($model === '') {
            return '';
        }

        // Encode each path segment separately so slashes remain slashes.
        $parts = array_values(array_filter(explode('/', $model), static fn ($p) => $p !== ''));
        $encoded = array_map('rawurlencode', $parts);

        // Preserve a leading slash if provided (not expected but safe).
        $leadingSlash = str_starts_with($model, '/') ? '/' : '';

        return $leadingSlash . implode('/', $encoded);
    }
}
