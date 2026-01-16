<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AstroProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_astro_page_requires_authentication(): void
    {
        $response = $this->get('/me/astro');

        $response->assertRedirect('/login');
    }

    public function test_astro_page_is_displayed_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/me/astro');

        $response->assertOk();
        $response->assertSee('Fiche astro');
    }
}
