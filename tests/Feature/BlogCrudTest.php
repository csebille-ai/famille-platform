<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_routes_are_protected_by_auth(): void
    {
        $this->get(route('blog.index'))
            ->assertRedirect(route('login'));

        $this->get(route('blog.create'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_create_update_and_delete_a_blog_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Create
        $createResponse = $this->post(route('blog.store'), [
            'title' => 'Hello',
            'category' => 'News',
            'content' => 'First post',
        ]);

        $post = BlogPost::query()->firstOrFail();

        $createResponse->assertRedirect(route('blog.show', $post));

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Hello');

        // Update
        $this->put(route('blog.update', $post), [
            'title' => 'Hello updated',
            'category' => 'News',
            'content' => 'Updated content',
        ])->assertRedirect(route('blog.show', $post));

        $this->assertDatabaseHas('blog_posts', [
            'id' => $post->id,
            'title' => 'Hello updated',
            'content' => 'Updated content',
        ]);

        // Delete
        $this->delete(route('blog.destroy', $post))
            ->assertRedirect(route('blog.index'));

        $this->assertDatabaseMissing('blog_posts', [
            'id' => $post->id,
        ]);
    }

    public function test_validation_requires_title_and_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->from(route('blog.create'))
            ->post(route('blog.store'), [
                'title' => '',
                'category' => '',
                'content' => 'x',
            ])
            ->assertRedirect(route('blog.create'))
            ->assertSessionHasErrors(['title', 'category']);
    }
}
