<?php

namespace App\Services\AvatarAstro;

use App\Models\User;

class AvatarSpecBuilder
{
    public const VERSION = 'removed';

    public function build(User $user): array
    {
        throw new \RuntimeException('This feature has been removed.');
    }
}
