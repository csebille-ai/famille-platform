<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_edit_user_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'member']);

        $this->actingAs($admin);

        $this->get(route('admin.users.edit', $user))
            ->assertOk();
    }

    public function test_admin_can_update_user_profile_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'member',
            'name' => 'Old Name',
            'email' => 'old.user@example.test',
        ]);

        $this->actingAs($admin);

        $payload = [
            'name' => 'Manon Sebille',
            'email' => 'manon.sebille@example.test',
            'role' => 'editor',
            'gender' => 'female',
            'date_of_birth' => '1995-01-02',
            'birth_time' => '08:15',
            'birth_place' => 'Lille',
            'birth_latitude' => 50.6292,
            'birth_longitude' => 3.0573,
            'phone' => '0600000000',
            'address_line1' => '1 rue de la Paix',
            'address_line2' => 'Bâtiment A',
            'postal_code' => '59000',
            'city' => 'Lille',
        ];

        $this->patch(route('admin.users.update', $user), $payload)
            ->assertRedirect(route('admin.users.show', $user));

        $user->refresh();

        $this->assertSame('Manon Sebille', $user->name);
        $this->assertSame('manon.sebille@example.test', $user->email);
        $this->assertSame('editor', $user->role);
        $this->assertSame('female', $user->gender);
        $this->assertSame('1995-01-02', optional($user->date_of_birth)->format('Y-m-d'));
        $this->assertTrue(in_array($user->birth_time, ['08:15', '08:15:00'], true));
        $this->assertSame('Lille', $user->birth_place);
        $this->assertSame('Lille', $user->city);
    }
}
