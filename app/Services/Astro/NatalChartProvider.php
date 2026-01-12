<?php

namespace App\Services\Astro;

use App\Models\User;

interface NatalChartProvider
{
    /**
     * Returns an array that can be stored in AstroProfile->natal, or null if unavailable.
     *
     * @return array<string,mixed>|null
     */
    public function compute(User $user): ?array;
}
