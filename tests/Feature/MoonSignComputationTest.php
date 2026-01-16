<?php

namespace Tests\Feature;

use App\Jobs\ComputeAstroProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoonSignComputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_and_stores_moon_sign_when_date_and_time_present(): void
    {
        config()->set('astro.engine_url', 'https://astro.test');
        Http::fake([
            'https://astro.test/moon' => Http::response([
                'utc' => '2026-01-16T11:30:00Z',
                'moon_lon' => 75.0,
                'moon_sign' => 'Gémeaux',
                'moon_deg_in_sign' => 15.0,
            ], 200),
        ]);

        $user = User::factory()->create([
            'date_of_birth' => '1990-05-10',
            'birth_time' => '13:30',
            'birth_place' => null,
        ]);

        ComputeAstroProfile::dispatchSync((int) $user->id);

        $user->refresh();
        $user->loadMissing('astroProfile');

        $this->assertNotNull($user->astroProfile);
        $this->assertSame('Gémeaux', $user->astroProfile->moon_sign);
        $this->assertSame('Gémeaux', (string) ($user->astro_signature_json['moon_sign'] ?? ''));

        Http::assertSentCount(1);
    }

    public function test_it_uses_cached_moon_sign_when_inputs_unchanged(): void
    {
        config()->set('astro.engine_url', 'https://astro.test');

        Http::fake([
            'https://astro.test/moon' => Http::response([
                'utc' => '2026-01-16T11:30:00Z',
                'moon_lon' => 75.0,
                'moon_sign' => 'Gémeaux',
                'moon_deg_in_sign' => 15.0,
            ], 200),
        ]);

        $user = User::factory()->create([
            'date_of_birth' => '1990-05-10',
            'birth_time' => '13:30',
            'birth_place' => null,
        ]);

        ComputeAstroProfile::dispatchSync((int) $user->id);
        ComputeAstroProfile::dispatchSync((int) $user->id);

        Http::assertSentCount(1);
    }

    public function test_it_clears_cached_moon_when_time_missing(): void
    {
        config()->set('astro.engine_url', 'https://astro.test');
        Http::fake();

        $user = User::factory()->create([
            'date_of_birth' => '1990-05-10',
            'birth_time' => null,
            'birth_place' => null,
        ]);

        ComputeAstroProfile::dispatchSync((int) $user->id);

        $user->refresh();
        $user->loadMissing('astroProfile');

        $this->assertNotNull($user->astroProfile);
        $this->assertNull($user->astroProfile->moon_sign);
        $this->assertNull($user->astro_signature_json['moon_sign'] ?? null);

        Http::assertSentCount(0);
    }
}
