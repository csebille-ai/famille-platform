<?php

namespace Tests\Unit;

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
}
