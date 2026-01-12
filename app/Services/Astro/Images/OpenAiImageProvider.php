<?php

namespace App\Services\Astro\Images;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAiImageProvider implements ImageProvider
{
    public function generateImage(string $prompt, array $opts = []): array
    {
        $apiKey = (string) config('services.openai.key');
        if (trim($apiKey) === '') {
            throw new \RuntimeException('OPENAI_API_KEY manquante');
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.image_model', 'gpt-image-1');

        $size = (string) ($opts['size'] ?? '1024x1536');

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            // OpenAI image generation supports these common sizes.
            'size' => $size,
        ];

        try {
            $resp = Http::timeout(90)
                ->retry(1, 200)
                ->withToken($apiKey)
                ->post("{$baseUrl}/images/generations", $payload)
                ->throw();
        } catch (RequestException $e) {
            $msg = $e->response?->json('error.message') ?? $e->getMessage();
            throw new \RuntimeException('Erreur OpenAI Image: ' . (string) $msg, previous: $e);
        }

        $b64 = $resp->json('data.0.b64_json');
        $url = $resp->json('data.0.url');

        $bytes = null;
        if (is_string($b64) && trim($b64) !== '') {
            $bytes = base64_decode($b64, true);
            if (!is_string($bytes) || $bytes === '') {
                throw new \RuntimeException('OpenAI Image: base64 invalide.');
            }
        } elseif (is_string($url) && trim($url) !== '') {
            // Some gateways/models only return a URL.
            $img = Http::timeout(60)->get($url);
            $bytes = $img->body();
            if (!is_string($bytes) || $bytes === '') {
                throw new \RuntimeException('OpenAI Image: téléchargement vide.');
            }
        } else {
            throw new \RuntimeException('OpenAI Image: image vide.');
        }

        return [
            'bytes' => $bytes,
            'mime' => 'image/png',
            'ext' => 'png',
        ];
    }
}
