<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class NextBirthday
{
    /**
     * @param  Collection<int,User>  $users
     * @return array{name:string, initials:string, next_date:CarbonImmutable, days_remaining:int, turning_age:int|null}|null
     */
    public function forUsers(Collection $users, ?CarbonInterface $today = null): ?array
    {
        $today = $today ? CarbonImmutable::instance($today) : CarbonImmutable::now(config('app.timezone'));
        $today = $today->startOfDay();

        $best = null;

        foreach ($users as $user) {
            $dob = $user->date_of_birth;
            if (!$dob instanceof CarbonInterface) {
                continue;
            }

            $nextDate = $this->nextOccurrence($dob, $today);
            $days = (int) $today->diffInDays($nextDate, false);
            if ($days < 0) {
                continue;
            }

            $age = null;
            try {
                $age = $nextDate->year - (int) $dob->year;
                if ($age < 0) {
                    $age = null;
                }
            } catch (\Throwable $e) {
                $age = null;
            }

            $candidate = [
                'name' => $this->firstName((string) $user->name),
                'initials' => $user->initials(),
                'next_date' => $nextDate,
                'days_remaining' => $days,
                'turning_age' => $age,
            ];

            if ($best === null || $candidate['days_remaining'] < $best['days_remaining']) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int,Person>  $people
     * @return array{name:string, initials:string, next_date:CarbonImmutable, days_remaining:int, turning_age:int|null}|null
     */
    public function forPeople(Collection $people, ?CarbonInterface $today = null): ?array
    {
        $today = $today ? CarbonImmutable::instance($today) : CarbonImmutable::now(config('app.timezone'));
        $today = $today->startOfDay();

        $best = null;

        foreach ($people as $person) {
            $dob = $person->birth_date;
            if (!$dob instanceof CarbonInterface) {
                continue;
            }

            $nextDate = $this->nextOccurrence($dob, $today);
            $days = (int) $today->diffInDays($nextDate, false);
            if ($days < 0) {
                continue;
            }

            $age = null;
            try {
                $age = $nextDate->year - (int) $dob->year;
                if ($age < 0) {
                    $age = null;
                }
            } catch (\Throwable $e) {
                $age = null;
            }

            $name = trim((string) $person->first_name);
            if ($name === '') {
                $name = $this->firstName($person->displayName());
            }

            $candidate = [
                'name' => $name,
                'initials' => $person->initials(),
                'next_date' => $nextDate,
                'days_remaining' => $days,
                'turning_age' => $age,
            ];

            if ($best === null || $candidate['days_remaining'] < $best['days_remaining']) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int,User>  $users
     * @return array<int,array{name:string, initials:string, next_date:CarbonImmutable, days_remaining:int, turning_age:int|null}>
     */
    public function upcomingForUsers(Collection $users, ?CarbonInterface $today = null): array
    {
        $today = $today ? CarbonImmutable::instance($today) : CarbonImmutable::now(config('app.timezone'));
        $today = $today->startOfDay();

        $items = [];

        foreach ($users as $user) {
            $dob = $user->date_of_birth;
            if (!$dob instanceof CarbonInterface) {
                continue;
            }

            $nextDate = $this->nextOccurrence($dob, $today);
            $days = (int) $today->diffInDays($nextDate, false);
            if ($days < 0) {
                continue;
            }

            $age = null;
            try {
                $age = $nextDate->year - (int) $dob->year;
                if ($age < 0) {
                    $age = null;
                }
            } catch (\Throwable $e) {
                $age = null;
            }

            $items[] = [
                'name' => $this->firstName((string) $user->name),
                'initials' => $user->initials(),
                'next_date' => $nextDate,
                'days_remaining' => $days,
                'turning_age' => $age,
            ];
        }

        usort($items, fn ($a, $b) => $a['days_remaining'] <=> $b['days_remaining']);
        return $items;
    }

    /**
     * @param  Collection<int,Person>  $people
     * @return array<int,array{name:string, initials:string, next_date:CarbonImmutable, days_remaining:int, turning_age:int|null}>
     */
    public function upcomingForPeople(Collection $people, ?CarbonInterface $today = null): array
    {
        $today = $today ? CarbonImmutable::instance($today) : CarbonImmutable::now(config('app.timezone'));
        $today = $today->startOfDay();

        $items = [];

        foreach ($people as $person) {
            $dob = $person->birth_date;
            if (!$dob instanceof CarbonInterface) {
                continue;
            }

            $nextDate = $this->nextOccurrence($dob, $today);
            $days = (int) $today->diffInDays($nextDate, false);
            if ($days < 0) {
                continue;
            }

            $age = null;
            try {
                $age = $nextDate->year - (int) $dob->year;
                if ($age < 0) {
                    $age = null;
                }
            } catch (\Throwable $e) {
                $age = null;
            }

            $name = trim((string) $person->first_name);
            if ($name === '') {
                $name = $this->firstName($person->displayName());
            }

            $items[] = [
                'name' => $name,
                'initials' => $person->initials(),
                'next_date' => $nextDate,
                'days_remaining' => $days,
                'turning_age' => $age,
            ];
        }

        usort($items, fn ($a, $b) => $a['days_remaining'] <=> $b['days_remaining']);
        return $items;
    }

    private function nextOccurrence(CarbonInterface $dob, CarbonImmutable $todayStart): CarbonImmutable
    {
        $tz = (string) ($todayStart->getTimezone()?->getName() ?: config('app.timezone'));

        $month = (int) $dob->month;
        $day = (int) $dob->day;

        $candidate = $this->safeDate($todayStart->year, $month, $day, $tz);
        if ($candidate->isBefore($todayStart)) {
            $candidate = $this->safeDate($todayStart->year + 1, $month, $day, $tz);
        }

        return $candidate;
    }

    private function safeDate(int $year, int $month, int $day, string $tz): CarbonImmutable
    {
        // Edge-case policy: Feb 29 birthdays are celebrated on Feb 28 on non-leap years.
        if ($month === 2 && $day === 29) {
            $isLeap = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $tz)->isLeapYear();
            if (!$isLeap) {
                $day = 28;
            }
        }

        return CarbonImmutable::createFromDate($year, $month, $day, $tz)->startOfDay();
    }

    private function firstName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Quelqu’un';
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return (string) ($parts[0] ?? $name);
    }
}
