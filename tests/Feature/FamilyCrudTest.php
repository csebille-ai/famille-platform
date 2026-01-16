<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/famille');

        $response->assertStatus(200);
        $response->assertSee('Famille');
    }

    public function test_user_can_create_child_and_becomes_guardian(): void
    {
        $mom = User::factory()->create(['name' => 'Mom']);
        $dad = User::factory()->create(['name' => 'Dad']);

        $response = $this->actingAs($mom)->post('/famille/enfants', [
            'first_name' => 'Léo',
            'last_name' => 'Martin',
            'birth_date' => '2016-01-20',
            'guardians' => [
                (string) $mom->id => ['enabled' => 1, 'can_edit' => 1, 'notify' => 1],
                (string) $dad->id => ['enabled' => 0, 'can_edit' => 1, 'notify' => 1],
            ],
        ]);

        $response->assertRedirect(route('family.index'));

        $child = Person::query()->where('is_child', true)->first();
        $this->assertNotNull($child);
        $this->assertSame('Léo', $child->first_name);

        $this->assertDatabaseHas('guardianships', [
            'guardian_user_id' => $mom->id,
            'child_person_id' => $child->id,
            'can_edit' => 1,
        ]);

        // Dad has no access by default.
        $this->actingAs($dad)->get('/famille/enfants/' . $child->id . '/modifier')->assertStatus(403);

        // Mom can edit.
        $this->actingAs($mom)->get('/famille/enfants/' . $child->id . '/modifier')->assertStatus(200);

        // Add dad as guardian but without edit.
        $this->actingAs($mom)->patch('/famille/enfants/' . $child->id, [
            'first_name' => 'Léo',
            'last_name' => 'Martin',
            'birth_date' => '2016-01-20',
            'guardians' => [
                (string) $mom->id => ['enabled' => 1, 'can_edit' => 1, 'notify' => 1],
                (string) $dad->id => ['enabled' => 1, 'can_edit' => 0, 'notify' => 1],
            ],
        ])->assertRedirect(route('family.index'));

        $this->actingAs($dad)->get('/famille/enfants/' . $child->id . '/modifier')->assertStatus(403);

        // Grant edit.
        $this->actingAs($mom)->patch('/famille/enfants/' . $child->id, [
            'first_name' => 'Léo',
            'last_name' => 'Martin',
            'birth_date' => '2016-01-20',
            'guardians' => [
                (string) $mom->id => ['enabled' => 1, 'can_edit' => 1, 'notify' => 1],
                (string) $dad->id => ['enabled' => 1, 'can_edit' => 1, 'notify' => 1],
            ],
        ])->assertRedirect(route('family.index'));

        $this->actingAs($dad)->get('/famille/enfants/' . $child->id . '/modifier')->assertStatus(200);
    }
}
