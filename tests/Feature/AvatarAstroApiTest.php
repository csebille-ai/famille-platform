<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvatarAstroApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_returns_410_gone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/avatar-astro/generate')
            ->assertStatus(410)
            ->assertJsonPath('status', 'error');
    }

    public function test_status_returns_410_gone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/avatar-astro/status')
            ->assertStatus(410)
            ->assertJsonPath('status', 'error');
    }
}
