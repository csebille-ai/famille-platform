<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_with_birth_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        $payload = [
            'email' => 'new.user@example.test',
            'role' => 'member',
            'date_of_birth' => '1990-10-23',
            'birth_time' => '13:45',
            'birth_place' => 'Paris',
            'birth_latitude' => 48.8566,
            'birth_longitude' => 2.3522,
        ];

        $this->post(route('admin.users.store'), $payload)
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'new.user@example.test')->firstOrFail();

        $this->assertSame('member', $user->role);
        $this->assertSame('1990-10-23', optional($user->date_of_birth)->format('Y-m-d'));
        $this->assertTrue(in_array($user->birth_time, ['13:45', '13:45:00'], true));
        $this->assertSame('Paris', $user->birth_place);
    }

    public function test_admin_can_create_user_even_if_astro_profiles_table_is_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Schema::dropIfExists('astro_profiles');

        $this->actingAs($admin);

        $this->post(route('admin.users.store'), [
            'email' => 'no.astro@example.test',
            'role' => 'member',
            'date_of_birth' => '1990-10-23',
            'birth_time' => '13:45',
            'birth_place' => 'Paris',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'no.astro@example.test',
            'role' => 'member',
        ]);
    }
}
