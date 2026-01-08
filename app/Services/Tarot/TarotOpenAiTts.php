<?php

namespace App\Services\Tarot;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TarotOpenAiTts
{
    /**
     * Generates an audio file for the given text and returns a public URL.
     */
    public function synthesizeToPublicUrl(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Texte vide.');
        }

        $apiKey = (string) config('services.openai.key');
        if (trim($apiKey) === '') {
            throw new \RuntimeException('OPENAI_API_KEY manquante');
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.tts_model', 'tts-1');
        $voice = (string) config('services.openai.tts_voice', 'alloy');
        $format = (string) config('services.openai.tts_format', 'mp3');

        $hash = hash('sha256', $model . '|' . $voice . '|' . $format . '|' . $text);
        $ext = $format === '' ? 'mp3' : $format;
        $path = "tarot-tts/{$hash}.{$ext}";

        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            return $disk->url($path);
        }

        $payload = [
            'model' => $model,
            'voice' => $voice,
            'input' => $text,
            'format' => $format,
        ];

        try {
            $resp = Http::timeout(40)
                ->retry(1, 200)
                ->withToken($apiKey)
                ->withHeaders([
                    // Some clients return audio/mpeg or audio/*
                    'Accept' => 'audio/*',
                ])
                ->post("{$baseUrl}/audio/speech", $payload)
                ->throw();
        } catch (RequestException $e) {
            $msg = $e->response?->json('error.message') ?? $e->getMessage();
            throw new \RuntimeException('Erreur OpenAI TTS: ' . (string) $msg, previous: $e);
        }

        $bytes = $resp->body();
        if (!is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('OpenAI TTS: audio vide.');
        }

        $disk->put($path, $bytes);

        return $disk->url($path);
    }
}
