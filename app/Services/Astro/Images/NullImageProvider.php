<?php

namespace App\Services\Astro\Images;

class NullImageProvider implements ImageProvider
{
    public function generateImage(string $prompt, array $opts = []): array
    {
        throw new \RuntimeException('Aucun provider d\'image configuré pour Astro Card (ASTRO_CARD_IMAGE_PROVIDER).');
    }
}
