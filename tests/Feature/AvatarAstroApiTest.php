<?php

namespace Tests\Feature;

use App\Jobs\GenerateAvatarAstroJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AvatarAstroApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_returns_400_when_required_fields_missing(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'astro_signature_json' => [
                'sun_sign' => 'Taureau',
                'ascendant' => 'Bélier',
                'chinese' => ['polarity' => 'Yang', 'element' => 'Métal', 'animal' => 'Chien'],
                'life_path' => 8,
            ],
            'date_of_birth' => null,
        ]);

        $this->actingAs($user)
            ->postJson('/api/avatar-astro/generate')
            ->assertStatus(400)
            ->assertJsonPath('status', 'error');

        Bus::assertNotDispatched(GenerateAvatarAstroJob::class);
    }

    public function test_generate_sets_pending_and_dispatches_job(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'astro_signature_json' => [
                'sun_sign' => 'Taureau',
                'moon_sign' => 'Gémeaux',
                'ascendant' => 'Bélier',
                'chinese' => ['polarity' => 'Yang', 'element' => 'Métal', 'animal' => 'Chien'],
                'life_path' => 8,
            ],
            'date_of_birth' => '1990-01-01',
            'birth_time' => '12:00',
        ]);

        $this->actingAs($user)
            ->postJson('/api/avatar-astro/generate')
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $user->refresh();
        $this->assertSame('pending', $user->avatar_astro_status);

        Bus::assertDispatched(GenerateAvatarAstroJob::class);

        // Idempotent when already pending.
        $this->actingAs($user)
            ->postJson('/api/avatar-astro/generate')
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        Bus::assertDispatchedTimes(GenerateAvatarAstroJob::class, 1);
    }

    public function test_status_returns_expected_payload_and_overlay(): void
    {
        $user = User::factory()->create([
            'avatar_astro_status' => 'ready',
            'avatar_image_url' => 'https://example.test/avatar.png',
            'avatar_spec_json' => [
                'sun_element' => 'Terre',
                'chinese_animal' => 'Chien',
                'life_path' => 8,
            ],
            'avatar_archetype_title' => 'Le Pilier',
            'avatar_traits_surannes' => ['flegmatique', 'tenace'],
            'avatar_updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/avatar-astro/status')
            ->assertOk()
            ->assertJsonStructure(['status', 'image_url', 'image_display_url', 'updated_at', 'error', 'overlay'])
            ->assertJsonPath('overlay.sun_element', 'Terre')
            ->assertJsonPath('overlay.chinese_animal', 'Chien')
            ->assertJsonPath('overlay.life_path', 8);
    }
}
