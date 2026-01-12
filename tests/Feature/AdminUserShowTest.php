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
            'birth_timezone' => 'Europe/Paris',
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

        $this->actingAs($admin)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee($target->email)
            ->assertSee('Fiche astrale')
            ->assertSee('Test signature');
    }
}
