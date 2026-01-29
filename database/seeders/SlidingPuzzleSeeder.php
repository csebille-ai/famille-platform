<?php

namespace Database\Seeders;

use App\Models\SlidingPuzzle;
use Illuminate\Database\Seeder;

class SlidingPuzzleSeeder extends Seeder
{
    public function run(): void
    {
        SlidingPuzzle::query()->firstOrCreate(
            ['title' => 'Taquin — Logo Famille'],
            [
                'description' => '3×3 — meilleur temps + coups',
                'image_source_type' => 'external',
                'image_source_id' => '/images/logo1.png',
                'grid_size' => 3,
                'is_active' => true,
            ]
        );
    }
}
