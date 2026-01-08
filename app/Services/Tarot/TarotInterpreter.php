<?php

namespace App\Services\Tarot;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TarotInterpreter
{
    /**
     * @param array<int, array{name:string, keywords:string}> $cards
     */
    public function interpret(string $question, string $spread, array $cards): string
    {
        $apiKey = (string) config('services.openai.key');
        if (trim($apiKey) === '') {
            throw new \RuntimeException('OPENAI_API_KEY manquante');
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.model', 'gpt-4o-mini');
        $maxChars = (int) config('tarot.max_chars', 1200);

        $cardsText = collect($cards)
            ->map(fn ($c) => '- ' . ($c['name'] ?? '') . ' (' . ($c['keywords'] ?? '') . ')')
            ->filter(fn ($line) => trim($line) !== '-')
            ->implode("\n");

        $system = <<<TXT
Tu es un assistant de tirage tarot orienté "fun/famille" mais crédible et bienveillant.
Objectif: aider à la réflexion (pas de certitudes), en français, sans ésotérisme lourd.

Contraintes:
- Réponse courte (max ~{$maxChars} caractères).
- Sections obligatoires, chacune très courte:
  1) Résumé
  2) Interprétation
  3) Conseil concret
  4) Disclaimer (1 ligne: divertissement/aide à la réflexion)
- Pas de jugement, pas d'injonctions fortes.
- Si la question est trop vague, propose une reformulation en une phrase (dans Résumé).
TXT;

        $user = <<<TXT
Question: {$question}
Type de tirage: {$spread}
Cartes:
{$cardsText}
TXT;

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.7,
            'max_tokens' => 450,
        ];

        try {
            $resp = Http::timeout(25)
                ->retry(2, 200)
                ->withToken($apiKey)
                ->acceptJson()
                ->post("{$baseUrl}/chat/completions", $payload)
                ->throw();
        } catch (RequestException $e) {
            $msg = $e->response?->json('error.message') ?? $e->getMessage();
            throw new \RuntimeException('Erreur OpenAI: ' . (string) $msg, previous: $e);
        }

        $text = (string) ($resp->json('choices.0.message.content') ?? '');
        $text = trim($text);

        if ($maxChars > 0 && mb_strlen($text) > $maxChars) {
            $text = rtrim(mb_substr($text, 0, $maxChars - 1)) . '…';
        }

        return $text;
    }
}
