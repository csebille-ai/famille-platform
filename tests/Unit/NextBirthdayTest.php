<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Models\User;
use App\Services\NextBirthday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

class NextBirthdayTest extends TestCase
{
    public function test_picks_next_birthday_including_year_rollover(): void
    {
        $svc = new NextBirthday();

        $today = CarbonImmutable::create(2026, 1, 14, 12, 0, 0, 'Europe/Paris');

        $a = new User(['name' => 'Alice']);
        $a->date_of_birth = CarbonImmutable::create(1990, 1, 10, 0, 0, 0, 'Europe/Paris'); // passed => next year

        $b = new User(['name' => 'Bob']);
        $b->date_of_birth = CarbonImmutable::create(1985, 1, 24, 0, 0, 0, 'Europe/Paris');

        $c = new User(['name' => 'Charly']);
        $c->date_of_birth = CarbonImmutable::create(1975, 2, 1, 0, 0, 0, 'Europe/Paris');

        $next = $svc->forUsers(new Collection([$a, $b, $c]), $today);

        $this->assertNotNull($next);
        $this->assertSame('Bob', $next['name']);
        $this->assertSame(10, $next['days_remaining']);
        $this->assertSame('2026-01-24', $next['next_date']->toDateString());
        $this->assertSame(41, $next['turning_age']);
    }

    public function test_feb_29_policy_is_feb_28_on_non_leap_years(): void
    {
        $svc = new NextBirthday();

        $today = CarbonImmutable::create(2026, 2, 1, 0, 0, 0, 'Europe/Paris'); // 2026 is not leap

        $u = new User(['name' => 'Leap']);
        $u->date_of_birth = CarbonImmutable::create(2000, 2, 29, 0, 0, 0, 'Europe/Paris');

        $next = $svc->forUsers(new Collection([$u]), $today);

        $this->assertNotNull($next);
        $this->assertSame('2026-02-28', $next['next_date']->toDateString());
        $this->assertSame(27, $next['days_remaining']);
        $this->assertSame(26, $next['turning_age']);
    }

    public function test_people_birthdays_include_children(): void
    {
        $svc = new NextBirthday();

        $today = CarbonImmutable::create(2026, 1, 14, 12, 0, 0, 'Europe/Paris');

        $child = new Person(['first_name' => 'Léo', 'last_name' => '']);
        $child->birth_date = CarbonImmutable::create(2016, 1, 20, 0, 0, 0, 'Europe/Paris');
        $child->is_child = true;

        $adult = new Person(['first_name' => 'Alice', 'last_name' => '']);
        $adult->birth_date = CarbonImmutable::create(1990, 2, 1, 0, 0, 0, 'Europe/Paris');

        $next = $svc->forPeople(new Collection([$child, $adult]), $today);

        $this->assertNotNull($next);
        $this->assertSame('Léo', $next['name']);
        $this->assertSame('2026-01-20', $next['next_date']->toDateString());
        $this->assertSame(6, $next['days_remaining']);
        $this->assertSame(10, $next['turning_age']);
    }

    public function test_dashboard_for_users_does_not_return_avatar_url_when_missing(): void
    {
        $svc = new NextBirthday();

        $today = CarbonImmutable::create(2026, 1, 14, 12, 0, 0, 'Europe/Paris');

        $u = new User(['name' => 'Alice']);
        $u->id = 123;
        $u->date_of_birth = CarbonImmutable::create(1990, 1, 24, 0, 0, 0, 'Europe/Paris');
        $u->avatar_path = null;

        $lists = $svc->dashboardForUsers(new Collection([$u]), $today, 10);
        $upcoming = $lists['upcomingBirthdays'] ?? [];

        $this->assertCount(1, $upcoming);
        $this->assertArrayHasKey('avatar_url', $upcoming[0]);
        $this->assertNull($upcoming[0]['avatar_url']);
    }

    public function test_dashboard_for_people_uses_linked_user_avatar_when_available(): void
    {
        $svc = new NextBirthday();

        $today = CarbonImmutable::create(2026, 1, 14, 12, 0, 0, 'Europe/Paris');

        $p = new Person(['first_name' => 'Christophe', 'last_name' => '']);
        $p->id = 1;
        $p->user_id = 123;
        $p->birth_date = CarbonImmutable::create(1990, 5, 4, 0, 0, 0, 'Europe/Paris');
        $p->is_child = false;
        $p->avatar_path = null;

        $u = new User(['name' => 'Christophe']);
        $u->id = 123;
        $u->avatar_path = 'avatars/123/avatar.webp';
        $u->avatar_updated_at = CarbonImmutable::create(2026, 1, 1, 0, 0, 0, 'Europe/Paris');

        $usersById = (new Collection([$u]))->keyBy('id');

        $lists = $svc->dashboardForPeople(new Collection([$p]), $today, 10, $usersById);
        $upcoming = $lists['upcomingBirthdays'] ?? [];

        $this->assertCount(1, $upcoming);
        $this->assertIsString($upcoming[0]['avatar_url']);
        $this->assertStringContainsString('avatars/123/avatar.webp', (string) $upcoming[0]['avatar_url']);
        $this->assertStringContainsString('v=', (string) $upcoming[0]['avatar_url']);
    }
}
