<?php

namespace App\Services\Astro\Images;

interface ImageProvider
{
    /**
     * @param array{aspect_ratio?:string,size?:string,seed?:string} $opts
     * @return array{bytes:string,mime:string,ext:string}
     */
    public function generateImage(string $prompt, array $opts = []): array;
}
