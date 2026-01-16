<?php

namespace Tests\Feature;

use App\Jobs\ComputeAstroProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AstroAutoBirthPlaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_birth_place_is_auto_resolved_to_coords_and_profile_is_computed(): void
    {
        config()->set('astro.engine_url', 'https://astro.test');
        Http::fake([
            // Nominatim
            'https://nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '50.62925', 'lon' => '3.057256'],
            ], 200),

            // astro-engine
            'https://astro.test/moon' => Http::response([
                'utc' => '1990-01-01T11:34:00Z',
                'moon_lon' => 75.0,
                'moon_sign' => 'Gémeaux',
                'moon_deg_in_sign' => 15.0,
            ], 200),
            'https://astro.test/sun' => Http::response([
                'utc' => '1990-01-01T11:34:00Z',
                'sun_lon' => 42.0,
                'sun_sign' => 'Taureau',
                'sun_deg_in_sign' => 12.0,
            ], 200),
            'https://astro.test/chart' => Http::response([
                'utc' => '1990-01-01T11:34:00Z',
                'angles' => [
                    'asc' => ['lon' => 120.0, 'sign' => 'Lion', 'deg_in_sign' => 0.0],
                    'mc' => ['lon' => 30.0, 'sign' => 'Taureau', 'deg_in_sign' => 0.0],
                ],
                'houses' => [],
                'planets' => [
                    ['key' => 'sun', 'name' => 'Soleil', 'lon' => 42.0, 'sign' => 'Taureau', 'deg_in_sign' => 12.0, 'house' => 1],
                    ['key' => 'moon', 'name' => 'Lune', 'lon' => 75.0, 'sign' => 'Gémeaux', 'deg_in_sign' => 15.0, 'house' => 2],
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'date_of_birth' => '1990-01-01',
            'birth_time' => '12:34:00',
            'birth_place' => 'Lille, France',
            'birth_latitude' => null,
            'birth_longitude' => null,
        ]);

        // Re-run explicitly (created observer also runs, but explicit makes test deterministic).
        ComputeAstroProfile::dispatchSync($user->id);

        $user->refresh();

        $this->assertNotNull($user->birth_latitude);
        $this->assertNotNull($user->birth_longitude);

        $this->assertNotNull($user->astroProfile);
        $this->assertNotEmpty((string) ($user->astroProfile->signature ?? ''));
        $this->assertNotNull($user->astroProfile->computed_at);
    }
}
