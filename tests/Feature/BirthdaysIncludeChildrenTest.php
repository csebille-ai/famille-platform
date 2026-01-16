<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BirthdaysIncludeChildrenTest extends TestCase
{
    use RefreshDatabase;

    public function test_birthdays_page_includes_children(): void
    {
        $user = User::factory()->create();

        // Adult profile is auto-created by UserObserver.
        $adult = Person::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($adult);
        $adult->update([
            'first_name' => 'Alice',
            'last_name' => null,
            'birth_date' => '1990-02-01',
            'is_child' => false,
        ]);

        Person::query()->create([
            'user_id' => null,
            'first_name' => 'Léo',
            'last_name' => 'Martin',
            'birth_date' => '2016-01-20',
            'is_child' => true,
        ]);

        $resp = $this->actingAs($user)->get('/anniversaires');

        $resp->assertStatus(200);
        $resp->assertSee('Anniversaires');
        $resp->assertSee('Léo');
    }
}
