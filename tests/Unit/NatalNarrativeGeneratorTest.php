<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Astro\Natal\NatalNarrativeGenerator;
use PHPUnit\Framework\TestCase;

final class NatalNarrativeGeneratorTest extends TestCase
{
    public function test_it_keeps_outer_planets_when_trimming(): void
    {
        $natal = [
            'angles' => [
                'asc' => ['lon' => 10.0, 'sign' => 'Bélier', 'deg_in_sign' => 10.0],
                'mc' => ['lon' => 100.0, 'sign' => 'Cancer', 'deg_in_sign' => 10.0],
            ],
            'planets' => [
                ['key' => 'sun', 'name' => 'Soleil', 'lon' => 0.0, 'sign' => 'Bélier', 'deg_in_sign' => 0.0, 'house' => 1],
                ['key' => 'moon', 'name' => 'Lune', 'lon' => 60.0, 'sign' => 'Gémeaux', 'deg_in_sign' => 0.0, 'house' => 3],
                ['key' => 'mercury', 'name' => 'Mercure', 'lon' => 90.0, 'sign' => 'Cancer', 'deg_in_sign' => 0.0, 'house' => 4],
                ['key' => 'venus', 'name' => 'Vénus', 'lon' => 120.0, 'sign' => 'Lion', 'deg_in_sign' => 0.0, 'house' => 5],
                ['key' => 'mars', 'name' => 'Mars', 'lon' => 180.0, 'sign' => 'Balance', 'deg_in_sign' => 0.0, 'house' => 7],

                ['key' => 'jupiter', 'name' => 'Jupiter', 'lon' => 30.0, 'sign' => 'Taureau', 'deg_in_sign' => 0.0, 'house' => 2],
                ['key' => 'saturn', 'name' => 'Saturne', 'lon' => 150.0, 'sign' => 'Vierge', 'deg_in_sign' => 0.0, 'house' => 6],
                ['key' => 'uranus', 'name' => 'Uranus', 'lon' => 210.0, 'sign' => 'Scorpion', 'deg_in_sign' => 0.0, 'house' => 8],
                ['key' => 'neptune', 'name' => 'Neptune', 'lon' => 240.0, 'sign' => 'Sagittaire', 'deg_in_sign' => 0.0, 'house' => 9],
                ['key' => 'pluto', 'name' => 'Pluton', 'lon' => 300.0, 'sign' => 'Verseau', 'deg_in_sign' => 0.0, 'house' => 11],
            ],
        ];

        $gen = new NatalNarrativeGenerator();
        $txt = $gen->generate($natal, 'Christophe');

        $this->assertStringContainsString('Jupiter en', $txt);
        $this->assertStringContainsString('Saturne en', $txt);
        $this->assertStringContainsString('Uranus en', $txt);
        $this->assertStringContainsString('Neptune en', $txt);
        $this->assertStringContainsString('Pluton en', $txt);

        $this->assertTrue(
            mb_strpos($txt, 'Saturne en') < mb_strpos($txt, 'Uranus en'),
            'Text should not be cut before Uranus when Saturn is present.'
        );
    }
}
