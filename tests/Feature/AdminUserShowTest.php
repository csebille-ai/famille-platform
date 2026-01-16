<?php

namespace Tests\Feature;

use App\Models\AstroProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_user_detail_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create([
            'role' => 'member',
            'birth_place' => 'Paris',
        ]);

        AstroProfile::query()->updateOrCreate(
            ['user_id' => $target->id],
            [
                'signature' => 'Test signature',
                'archetype' => 'Test archetype',
                'talents' => ['A', 'B'],
                'weakness' => 'Test weakness',
                'computed_at' => now(),
            ]
        );

        // Canonical display now comes from structured JSON keys.
        $target->forceFill([
            'astro_signature_json' => [
                'sun_sign' => 'Taureau',
                'ascendant' => 'Bélier',
                'chinese' => ['polarity' => 'Yang', 'element' => 'Métal', 'animal' => 'Chien'],
                'life_path' => 8,
                'archetype' => 'Test archetype',
                'talents' => ['A', 'B'],
                'vigilance' => 'Test weakness',
            ],
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee($target->email)
            ->assertSee('Statut')
            ->assertSee('Naissance (astro)')
            ->assertSee('Actions rapides')
            ->assertSee('Copier lien invitation')
            ->assertSee('Voir la fiche astro');
    }
}
