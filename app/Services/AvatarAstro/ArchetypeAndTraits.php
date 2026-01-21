<?php

namespace App\Services\AvatarAstro;

use App\Models\User;

class ArchetypeAndTraits
{
    public function build(User $user, array $spec): array
    {
        throw new \RuntimeException('This feature has been removed.');
    }
}
