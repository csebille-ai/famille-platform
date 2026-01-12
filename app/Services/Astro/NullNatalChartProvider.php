<?php

namespace App\Services\Astro;

use App\Models\User;

class NullNatalChartProvider implements NatalChartProvider
{
    public function compute(User $user): ?array
    {
        return null;
    }
}
