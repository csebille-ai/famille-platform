<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_resources_routes_are_protected_by_auth(): void
    {
        $this->get(route('resources.index'))
            ->assertRedirect(route('login'));

        $this->get(route('resources.create'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_create_update_and_delete_a_resource(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Create
        $createResponse = $this->post(route('resources.store'), [
            'title' => 'Test',
            'category' => 'Administratif',
            'content' => 'Première ressource',
        ]);

        $resource = Resource::query()->firstOrFail();

        $createResponse->assertRedirect(route('resources.show', $resource));

        $this->get(route('resources.index'))
            ->assertOk()
            ->assertSee('Test');

        // Update
        $this->put(route('resources.update', $resource), [
            'title' => 'Test modifié',
            'category' => 'Administratif',
            'content' => 'Contenu modifié',
        ])->assertRedirect(route('resources.show', $resource));

        $this->assertDatabaseHas('resources', [
            'id' => $resource->id,
            'title' => 'Test modifié',
            'content' => 'Contenu modifié',
        ]);

        // Delete
        $this->delete(route('resources.destroy', $resource))
            ->assertRedirect(route('resources.index'));

        $this->assertDatabaseMissing('resources', [
            'id' => $resource->id,
        ]);
    }

    public function test_validation_requires_title_and_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->from(route('resources.create'))
            ->post(route('resources.store'), [
                'title' => '',
                'category' => '',
                'content' => 'x',
            ])
            ->assertRedirect(route('resources.create'))
            ->assertSessionHasErrors(['title', 'category']);
    }
}
