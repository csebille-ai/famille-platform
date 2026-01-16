<?php

namespace Tests\Feature;

use App\Models\AstroProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AstroRecomputeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_recomputes_for_single_user_sync(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test',
            'date_of_birth' => '2000-10-15',
            'birth_place' => null,
            'birth_latitude' => null,
            'birth_longitude' => null,
            'birth_time' => null,
        ]);

        // Remove any auto-computed profile from observer so the command is meaningful.
        AstroProfile::query()->where('user_id', $user->id)->delete();
        $user->forceFill(['astro_signature_json' => null])->save();

        $this->artisan('astro:recompute --user-id=' . $user->id . ' --sync --yes')
            ->assertExitCode(0);

        $user->refresh();

        $this->assertIsArray($user->astro_signature_json);
        $this->assertNotEmpty((string) ($user->astro_signature_json['archetype'] ?? ''));
        $this->assertIsArray($user->astro_signature_json['talents'] ?? null);
    }
}
