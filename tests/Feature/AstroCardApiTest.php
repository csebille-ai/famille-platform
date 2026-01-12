<?php

namespace Tests\Feature;

use App\Jobs\GenerateAstroCardJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AstroCardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_returns_400_when_signature_missing(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/astro-card/generate')
            ->assertStatus(400)
            ->assertJsonPath('status', 'error');

        Bus::assertNotDispatched(GenerateAstroCardJob::class);
    }

    public function test_generate_sets_pending_and_dispatches_job(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'astro_signature_json' => [
                'sun_sign' => 'Taureau',
                'ascendant' => 'Bélier',
                'chinese' => ['polarity' => 'Yang', 'element' => 'Métal', 'animal' => 'Chien'],
                'life_path' => 8,
                'archetype' => 'Gardien',
                'talents' => ['Protège et sécurise', 'Structure le quotidien', 'Rassure naturellement'],
                'vigilance' => 'Peut trop vouloir contrôler',
            ],
        ]);

        $this->actingAs($user)
            ->postJson('/api/astro-card/generate')
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $user->refresh();
        $this->assertSame('pending', $user->astro_card_status);

        Bus::assertDispatched(GenerateAstroCardJob::class);

        // Idempotent when already pending.
        $this->actingAs($user)
            ->postJson('/api/astro-card/generate')
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        Bus::assertDispatchedTimes(GenerateAstroCardJob::class, 1);
    }

    public function test_status_returns_expected_payload(): void
    {
        $user = User::factory()->create([
            'astro_card_status' => 'ready',
            'astro_card_image_url' => 'https://example.test/card.png',
            'astro_signature_json' => [
                'sun_sign' => 'Taureau',
                'ascendant' => 'Bélier',
                'chinese' => ['polarity' => 'Yang', 'element' => 'Métal', 'animal' => 'Chien'],
                'life_path' => 8,
                'archetype' => 'Gardien',
                'talents' => ['Protège et sécurise', 'Structure le quotidien', 'Rassure naturellement'],
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/astro-card/status')
            ->assertOk()
            ->assertJsonStructure(['status', 'image_url', 'image_display_url', 'generated_at', 'error', 'overlay']);
    }
}
