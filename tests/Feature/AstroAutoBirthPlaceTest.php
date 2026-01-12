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

    public function test_birth_place_is_auto_resolved_to_coords_and_timezone_and_profile_is_computed(): void
    {
        Http::fake([
            // Nominatim
            'https://nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '50.62925', 'lon' => '3.057256'],
            ], 200),
            // timeapi
            'https://timeapi.io/api/TimeZone/coordinate*' => Http::response([
                'timeZone' => 'Europe/Paris',
            ], 200),
        ]);

        $user = User::factory()->create([
            'date_of_birth' => '1990-01-01',
            'birth_time' => '12:34:00',
            'birth_place' => 'Lille, France',
            'birth_timezone' => null,
            'birth_latitude' => null,
            'birth_longitude' => null,
        ]);

        // Re-run explicitly (created observer also runs, but explicit makes test deterministic).
        ComputeAstroProfile::dispatchSync($user->id);

        $user->refresh();

        $this->assertNotNull($user->birth_latitude);
        $this->assertNotNull($user->birth_longitude);
        $this->assertSame('Europe/Paris', $user->birth_timezone);

        $this->assertNotNull($user->astroProfile);
        $this->assertNotEmpty((string) ($user->astroProfile->signature ?? ''));
        $this->assertNotNull($user->astroProfile->computed_at);
    }
}
