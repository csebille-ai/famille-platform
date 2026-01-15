<?php

namespace App\Services\Astro\Images;

class NullImageProvider implements ImageProvider
{
    public function generateImage(string $prompt, array $opts = []): array
    {
        throw new \RuntimeException('Aucun provider d\'image configuré pour Avatar Astro (AVATAR_ASTRO_IMAGE_PROVIDER).');
    }
}
