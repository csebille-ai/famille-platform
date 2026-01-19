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
        $bundle = $this->interpretBundle(question: $question, spread: $spread, cards: $cards);
        return $bundle['interpretation'];
    }

    /**
     * @param array<int, array{name:string, keywords:string, reversed?:bool, orientation?:string}> $cards
     * @return array{interpretation:string, spoken_text:string}
     */
    public function interpretBundle(string $question, string $spread, array $cards): array
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

        $system = <<<SYS
    Tu es "Le Tarologue de Famille" : un interprète de tarot en français, très drôle, surprenant, et bienveillant.
    But: faire rire ET donner une interprétation utile (même légère). Ambiance: repas de famille, taquinerie gentille.

    RÈGLES DE SÉCURITÉ & TON
    - Jamais méchant: pas d’humiliation, pas d’attaque sur le physique, pas de harcèlement.
    - Pas de vulgarité crue, pas de sexe explicite, pas de politique.
    - Pas de fatalisme: pas de prédictions absolues. Parle en tendances ("ça sent…", "il se peut…").
    - Si la question touche santé/justice/finance: humour OK mais conseille prudence et "à confirmer IRL".

    STYLE
    - Humour: 3 à 6 touches max (running gags, métaphores absurdes, mini punchlines).
    - Surprenant: images inattendues, comparaisons modernes (WhatsApp, micro-ondes, GPS, facture EDF, etc.).
    - Rythme: phrases courtes, dynamique, pas de blabla ésotérique lourd. Évite "vibrations cosmiques".
    - Utilise des apartés entre parenthèses parfois.
    - Tu peux te permettre un mini "plot twist" à la fin.

    INTERPRÉTATION DES CARTES
    - Chaque carte: 1 idée principale + 1 conséquence concrète.
    - Carte renversée: blocage, excès, retard, angle mort ou "mode bug". Explique en 1 phrase claire.

    FORMAT EXACT (Markdown)
    - Utilise des TITRES (##) et de VRAIS paragraphes (lignes séparées par une ligne vide).
    - Aucun bloc compact tout collé : laisse une ligne vide entre les sections.

    ## Passé
    2 phrases.

    ## Présent
    2 phrases.

    ## Futur
    2 phrases.

    ## Le conseil qui pique mais qui aide
    2 actions concrètes au format liste:
    - ✅ ...
    - ✅ ...

    ## Le twist final
    1 punchline surprise.
    - Ne termine jamais par "..." ou "…".

    EN PLUS: SPOKEN_TEXT (pour lecture audio)
    - Génère aussi un champ spoken_text adapté à l’oral: 25–45 secondes.
    - Phrases courtes. Respiration. Rythme.
    - Zéro markdown. Pas de listes. Pas d’emojis.
    - Tu peux faire "Ok." / "Passé:" / "Présent:" etc, mais en phrases simples.

    LONGUEUR
    - Réponse courte (max ~{$maxChars} caractères, idéalement ≤ 260 mots).

    NE JAMAIS
    - Mentionner le modèle, "OpenAI", "prompt", ou les règles internes.

    IMPORTANT
    - Réponds UNIQUEMENT en JSON valide, sans texte autour.
    - Clés attendues: interpretation, spoken_text.
    SYS;

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
            'response_format' => [
                // More compatible with chat/completions than json_schema.
                'type' => 'json_object',
            ],
            'temperature' => 0.7,
            'max_tokens' => 650,
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

        $raw = (string) ($resp->json('choices.0.message.content') ?? '');
        $raw = trim($raw);

        $decoded = $this->decodeJsonObjectFromContent($raw);

        $interpretation = '';
        $spokenText = '';
        if (is_array($decoded)) {
            $interpretation = trim((string) ($decoded['interpretation'] ?? ''));
            $spokenText = trim((string) ($decoded['spoken_text'] ?? ''));
        }

        // Fallback: don't block the draw if the model didn't respect JSON.
        if ($interpretation === '') {
            $interpretation = $raw;
        }
        if ($spokenText === '') {
            $spokenText = $this->deriveSpokenText($interpretation);
        }

        $interpretation = $this->normalizeInterpretationMarkdown($interpretation);

        if ($maxChars > 0 && mb_strlen($interpretation) > $maxChars) {
            $cut = mb_substr($interpretation, 0, $maxChars);
            $last = max(mb_strrpos($cut, '.') ?: 0, mb_strrpos($cut, '!') ?: 0, mb_strrpos($cut, '?') ?: 0);
            if ($last > (int) max(200, $maxChars * 0.6)) {
                $interpretation = trim(mb_substr($cut, 0, $last + 1));
            } else {
                $interpretation = rtrim(mb_substr($interpretation, 0, $maxChars - 1)) . '…';
            }
        }

        return [
            'interpretation' => $interpretation,
            'spoken_text' => $spokenText,
        ];
    }

    private function normalizeInterpretationMarkdown(string $interpretation): string
    {
        $t = trim($interpretation);
        if ($t === '') {
            return '';
        }

        // Normalize line endings.
        $t = str_replace("\r\n", "\n", $t);

        // Normalize common list markers.
        $t = preg_replace('/^\s*✅\s*/mu', '- ✅ ', $t) ?? $t;
        $t = preg_replace('/^\s*•\s+/mu', '- ', $t) ?? $t;

        // Upgrade common section labels to markdown headings.
        $t = preg_replace('/^\s*\*\*(Annonce du tirage|Passé|Présent|Futur|Le conseil qui pique mais qui aide|Le twist final)\s*:?\s*\*\*\s*$/mu', '## $1', $t) ?? $t;
        $t = preg_replace('/^\s*(Annonce du tirage|Passé|Présent|Futur|Le conseil qui pique mais qui aide|Le twist final)\s*:\s*$/mu', '## $1', $t) ?? $t;
        $t = preg_replace('/^\s*(Annonce du tirage|Passé|Présent|Futur|Le conseil qui pique mais qui aide|Le twist final)\s*:\s*(.+)$/mu', "## $1\n\n$2", $t) ?? $t;

        // Drop the intro section entirely (users don't want it in UI).
        $t = preg_replace('/^##\s*Annonce du tirage\s*\n+.*?(?=^##\s|\z)/ms', '', $t) ?? $t;

        // Ensure a blank line after headings.
        $t = preg_replace('/^(##\s+[^\n]+)\n(?!\n)/m', "$1\n\n", $t) ?? $t;

        // Ensure a blank line before lists.
        $t = preg_replace('/\n(\s*[-*]\s+)/m', "\n\n$1", $t) ?? $t;

        // Collapse excessive blank lines.
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;

        return trim($t);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObjectFromContent(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        // Common: fenced JSON
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // If the model included text around the JSON, extract the first {...} block.
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $m) === 1) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Fallback for truncated/invalid JSON: extract string fields by regex.
        $out = [];
        if (preg_match('/"interpretation"\s*:\s*"(?<val>.*?)(?="\s*,\s*"spoken_text"|"\s*\})/s', $content, $mm) === 1) {
            $out['interpretation'] = $this->decodeJsonStringLoose((string) $mm['val']);
        }
        if (preg_match('/"spoken_text"\s*:\s*"(?<val>.*?)(?="\s*,\s*"interpretation"|"\s*\})/s', $content, $mm) === 1) {
            $out['spoken_text'] = $this->decodeJsonStringLoose((string) $mm['val']);
        }
        if ($out !== []) {
            return $out;
        }

        return null;
    }

    private function decodeJsonStringLoose(string $raw): string
    {
        $raw = (string) $raw;
        // Handle content that contains literal newlines inside a JSON string (invalid JSON).
        $normalized = str_replace(["\r\n", "\r", "\n"], "\\n", $raw);
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $normalized);
        $val = json_decode('"' . $escaped . '"');
        if (is_string($val)) {
            return $val;
        }
        return stripcslashes($raw);
    }

    private function deriveSpokenText(string $interpretation): string
    {
        $t = trim($interpretation);
        if ($t === '') {
            return '';
        }

        // Remove common markdown & list formatting.
        $t = preg_replace('/\*\*(.*?)\*\*/s', '$1', $t) ?? $t;
        $t = preg_replace('/^\s*#{1,6}\s+/m', '', $t) ?? $t;
        $t = preg_replace('/^\s*[-*•]\s+/m', '', $t) ?? $t;
        $t = str_replace(["✅", "`"], ['', ''], $t);

        // Remove emojis/pictographs (best effort).
        $t = preg_replace('/\p{Extended_Pictographic}+/u', '', $t) ?? $t;

        // Collapse whitespace.
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        $t = trim($t);

        // Keep it reasonably short for ~25–45s (heuristic).
        $max = 850;
        if (mb_strlen($t) > $max) {
            $cut = mb_substr($t, 0, $max);
            $last = max(mb_strrpos($cut, '.') ?: 0, mb_strrpos($cut, '!') ?: 0, mb_strrpos($cut, '?') ?: 0);
            if ($last > 200) {
                $t = trim(mb_substr($cut, 0, $last + 1));
            } else {
                $t = trim($cut) . '…';
            }
        }

        return $t;
    }
}
