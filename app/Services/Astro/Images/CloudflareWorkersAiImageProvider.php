<?php

namespace App\Services\Astro\Images;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $negative = $opts['negative_prompt'] ?? null;

        // Model-specific inputs: not all Workers AI image models accept width/height/seed/negative.
        $modelLower = strtolower(trim($model));
        $seed = $opts['seed'] ?? null;
        $seedInt = null;
        if (is_string($seed) && $seed !== '') {
            // Derive a stable int seed from string hash.
            $seedInt = hexdec(substr(sha1($seed), 0, 8));
        }

        if (str_contains($modelLower, 'flux-1-schnell')) {
            $steps = (int) ($opts['steps'] ?? 4);
            $steps = max(1, min(8, $steps));

            $jsonPayload = [
                'prompt' => $prompt,
                'steps' => $steps,
            ];

            // flux-1-schnell schema: prompt + steps only.
            $w = 0;
            $h = 0;
            $seedInt = null;
            $negative = null;
        } else {
            // Size handling: most Workers AI image models use width/height.
            $size = (string) ($opts['size'] ?? '1024x1536');
            [$w, $h] = $this->parseSize($size);

            $jsonPayload = [
                'prompt' => $prompt,
                'width' => $w,
                'height' => $h,
            ];

            if (is_string($negative) && trim($negative) !== '') {
                $jsonPayload['negative_prompt'] = trim($negative);
            }

            if ($seedInt !== null) {
                $jsonPayload['seed'] = $seedInt;
            }
        }

        $request = Http::timeout(120)
            ->retry(1, 250)
            ->asJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json, image/*',
            ]);

        try {
            if ($this->modelRequiresMultipartWrapper($model)) {
                Log::warning('Workers AI image generation: using multipart wrapper (model requires it).', [
                    'model' => $model,
                    'size' => "{$w}x{$h}",
                    'has_seed' => $seedInt !== null,
                    'has_negative' => is_string($negative) && trim($negative) !== '',
                ]);
                $resp = $this->postMultipartWrapper($request, $url, $prompt, $w, $h, $seedInt, $negative);
            } else {
                try {
                    $resp = $request->post($url, $jsonPayload)->throw();
                } catch (RequestException $e) {
                    // Some models (e.g. flux-2-dev) require a multipart wrapper; retry automatically.
                    if ($this->isMultipartRequiredError($e)) {
                        Log::warning('Workers AI image generation: retrying with multipart wrapper (API requires multipart).', [
                            'model' => $model,
                            'size' => "{$w}x{$h}",
                            'has_seed' => $seedInt !== null,
                            'has_negative' => is_string($negative) && trim($negative) !== '',
                        ]);
                        $resp = $this->postMultipartWrapper($request, $url, $prompt, $w, $h, $seedInt, $negative);
                    } else {
                        throw $e;
                    }
                }
            }
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

    private function modelRequiresMultipartWrapper(string $model): bool
    {
        $model = strtolower(trim($model));

        // Cloudflare docs: flux-2-dev uses a multipart wrapper input schema.
        // See: https://developers.cloudflare.com/workers-ai/models/flux-2-dev
        return str_contains($model, 'flux-2-dev');
    }

    /**
     * Cloudflare Workers AI: some models (e.g. flux-2-dev) require a `multipart` wrapper
     * rather than the usual JSON payload.
     */
    private function postMultipartWrapper($request, string $url, string $prompt, int $w, int $h, ?int $seedInt, $negative)
    {
        $body = [
            'prompt' => $prompt,
            'width' => $w,
            'height' => $h,
        ];

        if ($seedInt !== null) {
            $body['seed'] = $seedInt;
        }
        if (is_string($negative) && trim($negative) !== '') {
            $body['negative_prompt'] = trim($negative);
        }

        try {
            return $request->post($url, [
                'multipart' => [
                    'body' => $body,
                    'contentType' => 'application/json',
                ],
            ])->throw();
        } catch (RequestException $e) {
            // If the model rejects extra fields, retry with prompt-only.
            $code = $e->response?->status();
            $msg = $e->response?->json('errors.0.message')
                ?? $e->response?->json('error.message')
                ?? '';

            $canRetry = $code === 400 && (array_key_exists('width', $body) || array_key_exists('height', $body) || array_key_exists('seed', $body) || array_key_exists('negative_prompt', $body));
            if (!$canRetry) {
                throw $e;
            }

            Log::warning('Workers AI image generation: multipart wrapper rejected extra fields; retrying with prompt-only.', [
                'status' => $code,
                'message' => (string) $msg,
            ]);

            return $request->post($url, [
                'multipart' => [
                    'body' => ['prompt' => $prompt],
                    'contentType' => 'application/json',
                ],
            ])->throw();
        }
    }

    private function isMultipartRequiredError(RequestException $e): bool
    {
        $code = $e->response?->status();
        if ($code !== 400) {
            return false;
        }

        $msg = $e->response?->json('errors.0.message')
            ?? $e->response?->json('error.message')
            ?? '';
        $msg = strtolower((string) $msg);

        if ($msg !== '' && str_contains($msg, 'required properties') && str_contains($msg, 'multipart')) {
            return true;
        }

        $body = strtolower((string) $e->response?->body());
        return $body !== '' && str_contains($body, 'required properties') && str_contains($body, 'multipart');
    }
}
