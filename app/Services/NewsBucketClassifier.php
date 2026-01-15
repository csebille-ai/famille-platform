<?php

namespace App\Services;

use Illuminate\Support\Str;

class NewsBucketClassifier
{
    /**
     * @return array{bucket:'infos'|'sorties'|'sport', sub_category:?string}
     */
    public function classify(string $sourceName, ?string $sourceTag, string $title, string $excerpt): array
    {
        $sourceNameNorm = $this->norm($sourceName);
        $sourceTagNorm = $this->norm((string) ($sourceTag ?? ''));
        $textNorm = $this->norm(trim($title . ' ' . $excerpt));

        $bucket = null;
        $sub = null;

        // 1) Mapping par tag (prioritaire)
        if ($sourceTagNorm === 'sport') {
            $bucket = 'sport';
        } elseif ($sourceTagNorm === 'habitat') {
            $bucket = 'infos';
            $sub = 'habitat';
        } elseif ($sourceTagNorm === 'charente-maritime') {
            $bucket = 'infos';
            $sub = 'institutions';
        } elseif ($sourceTagNorm === 'ile-de-re') {
            if (str_contains($sourceNameNorm, 'agenda')) {
                $bucket = 'sorties';
                $sub = 'agenda';
            } else {
                $bucket = 'infos';
            }
        } elseif ($sourceTagNorm === 'la-rochelle') {
            // Cas mixte: ne pas décider ici.
        }

        // 2) Overrides par source.name (uniquement si bucket toujours null)
        if ($bucket === null) {
            // Hélène FM: mix, ne pas forcer.
            if (!str_contains($sourceNameNorm, 'helene fm')) {
                if ($this->containsAny($sourceNameNorm, ['agglo', 'prefecture', 'cdc'])) {
                    $bucket = 'infos';
                    $sub = 'institutions';
                } elseif (str_contains($sourceNameNorm, 'adil')) {
                    $bucket = 'infos';
                    $sub = 'habitat';
                } elseif (str_contains($sourceNameNorm, 'la sirene')) {
                    $bucket = 'sorties';
                    $sub = 'culture';
                } elseif (str_contains($sourceNameNorm, 'stade rochelais')) {
                    $bucket = 'sport';
                    $sub = 'rugby';
                } elseif (str_contains($sourceNameNorm, 're beach club')) {
                    $bucket = 'sport';
                    $sub = 'beach_volley';
                } elseif (str_contains($sourceNameNorm, 'ici la rochelle')) {
                    $bucket = 'infos';
                    $sub = 'media';
                }
            }
        }

        // 3) Fallback mots-clés (uniquement si bucket toujours null)
        if ($bucket === null) {
            $sortiesKeywords = [
                'concert', 'festival', 'expo', 'exposition', 'spectacle', 'agenda',
                'atelier', 'cinema', 'sortie',
            ];
            $sportKeywords = [
                'match', 'resultat', 'score', 'classement', 'tournoi', 'championnat',
            ];

            if ($this->containsAny($textNorm, $sortiesKeywords)) {
                $bucket = 'sorties';
            } elseif ($this->containsAny($textNorm, $sportKeywords)) {
                $bucket = 'sport';
            } else {
                $bucket = 'infos';
            }
        }

        return [
            'bucket' => $bucket,
            'sub_category' => $sub,
        ];
    }

    private function norm(string $s): string
    {
        return Str::of($s)->lower()->ascii()->toString();
    }

    /**
     * @param array<int,string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needleNorm = $this->norm($needle);
            if ($needleNorm !== '' && str_contains($haystack, $needleNorm)) {
                return true;
            }
        }
        return false;
    }
}
