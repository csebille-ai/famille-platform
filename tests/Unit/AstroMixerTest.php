<?php

namespace Tests\Unit;

use App\Services\Astro\AstroMixer;
use Tests\TestCase;

class AstroMixerTest extends TestCase
{
    public function test_air_signs_can_have_different_archetypes(): void
    {
        $balance = AstroMixer::mix([
            'western_sign' => 'Balance',
            'western_element' => 'Air',
        ]);

        $verseau = AstroMixer::mix([
            'western_sign' => 'Verseau',
            'western_element' => 'Air',
        ]);

        $this->assertNotSame($balance['archetype'], $verseau['archetype']);
    }

    public function test_talents_are_deterministic_but_can_vary_by_signature_inputs(): void
    {
        $base = [
            'western_sign' => 'Gémeaux',
            'western_element' => 'Air',
            'chinese_animal' => 'Dragon',
            'chinese_element' => 'Métal',
            'chinese_yin_yang' => 'Yang',
        ];

        $a1 = AstroMixer::mix(array_merge($base, ['ascendant_sign' => 'Lion']));
        $a2 = AstroMixer::mix(array_merge($base, ['ascendant_sign' => 'Lion']));
        $b = AstroMixer::mix(array_merge($base, ['ascendant_sign' => 'Vierge']));

        $this->assertSame($a1['archetype'], $b['archetype']);
        $this->assertSame($a1['talents'], $a2['talents']);
        $this->assertNotSame($a1['talents'], $b['talents']);
    }
}
