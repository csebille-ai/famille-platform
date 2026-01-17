<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class NextBirthday
{
    /**
     * Build birthday lists for the dashboard.
     *
     * @param  Collection<int,Person>  $people
     * @param  Collection<int,User>|null  $usersById keyed by user id
     * @return array{todayBirthdays: array<int,array{name:string,initials:string,birthday_date:CarbonImmutable,days_until:int,date_label:string,age_label:string|null,profile_url:string,avatar_url:string|null}>, upcomingBirthdays: array<int,array{name:string,initials:string,birthday_date:CarbonImmutable,days_until:int,date_label:string,age_label:string|null,profile_url:string,avatar_url:string|null}>}
     */
    public function dashboardForPeople(Collection $people, ?CarbonInterface $today = null, int $upcomingLimit = 10, ?Collection $usersById = null): array
    {
        $today = $today ? CarbonImmutable::instance($today) : CarbonImmutable::now(config('app.timezone'));
        $today = $today->startOfDay();

        $raw = $this->upcomingForPeople($people, $today);

        $todayBirthdays = [];
        $upcomingBirthdays = [];

        foreach ($raw as $b) {
            $days = (int) ($b['days_remaining'] ?? -1);
            $nextDate = $b['next_date'] ?? null;
            if (!($nextDate instanceof CarbonImmutable)) {
                continue;
            }
            if ($days < 0) {
                continue;
            }

            $name = trim((string) ($b['name'] ?? ''));
            $initials = (string) ($b['initials'] ?? '?');

            $profileUrl = route('family.index');
            $personId = (int) ($b['id'] ?? 0);
            if ($personId > 0 && (bool) ($b['is_child'] ?? false)) {
                $profileUrl = route('family.children.edit', ['person' => $personId]);
            }

            $avatarUrl = null;
            $path = trim((string) ($b['avatar_path'] ?? ''));
            if ($path !== '') {
                try {
                    $avatarUrl = Storage::url($path);
                } catch (\Throwable $e) {
                    $avatarUrl = null;
                }
            }

            // If the person is linked to a user, prefer the user's Avatar Astro (same avatar as top-right menu).
            if ($avatarUrl === null && $usersById instanceof Collection) {
                $userId = (int) ($b['user_id'] ?? 0);
                if ($userId > 0 && $usersById->has($userId)) {
                    $u = $usersById->get($userId);
                    if ($u instanceof User && $u->hasAvatarAstroImage()) {
                        $avatarUrl = route('avatar.astro.imagePublic', ['user' => $userId, 'v' => $u->avatarAstroVersion()]);
                    }
                }
            }

            $ageLabel = $this->ageLabel($b['turning_age'] ?? null, (bool) ($b['is_child'] ?? false));

            $item = [
                'name' => $name !== '' ? $name : 'Quelqu’un',
                'initials' => $initials,
                'birthday_date' => $nextDate,
                'days_until' => $days,
                'date_label' => $this->shortFrDate($nextDate),
                'age_label' => $ageLabel,
                'profile_url' => $profileUrl,
                'avatar_url' => $avatarUrl,
            ];

            if ($days === 0) {
                $todayBirthdays[] = $item;
            } elseif ($days > 0) {
                $upcomingBirthdays[] = $item;
            }
        }

        if ($upcomingLimit > 0) {
            $upcomingBirthdays = array_slice($upcomingBirthdays, 0, $upcomingLimit);
        }

        return [
            'todayBirthdays' => $todayBirthdays,
            'upcomingBirthdays' => $upcomingBirthdays,
        ];
    }

    /**
     * @param  Collection<int,User>  $users
     * @return array{todayBirthdays: array<int,array{name:string,initials:string,birthday_date:CarbonImmutable,days_until:int,date_label:string,age_label:string|null,profile_url:string,avatar_url:string|null}>, upcomingBirthdays: array<int,array{name:string,initials:string,birthday_date:CarbonImmutable,days_until:int,date_label:string,age_label:string|null,profile_url:string,avatar_url:string|null}>}
     */
    public function dashboardForUsers(Collection $users, ?CarbonInterface $today = null, int $upcomingLimit = 10): array
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

            $id = (int) ($user->id ?? 0);

            $profileUrl = route('family.index');
            if ($id > 0) {
                if (\Illuminate\Support\Facades\Gate::allows('manage-users')) {
                    $profileUrl = route('admin.users.show', ['user' => $id]);
                } elseif (auth()->check() && auth()->id() === $id) {
                    $profileUrl = route('profile.edit');
                }
            }

            // Avatar priority:
            // 1) user.avatar_image_url (explicit portrait)
            // 2) local generated avatar route
            $avatarUrl = null;
            if ($user instanceof \App\Models\User && $user->hasAvatarAstroImage()) {
                $avatarUrl = trim((string) $user->avatar_image_url);
            }

            $items[] = [
                'name' => $this->firstName((string) ($user->name ?? '')),
                'initials' => $user->initials(),
                'birthday_date' => $nextDate,
                'days_until' => $days,
                'date_label' => $this->shortFrDate($nextDate),
                'age_label' => $this->ageLabel($age, true),
                'profile_url' => $profileUrl,
                'avatar_url' => $avatarUrl,
            ];
        }

        usort($items, fn ($a, $b) => (int) $a['days_until'] <=> (int) $b['days_until']);

        $todayBirthdays = array_values(array_filter($items, fn ($it) => (int) ($it['days_until'] ?? -1) === 0));
        $upcomingBirthdays = array_values(array_filter($items, fn ($it) => (int) ($it['days_until'] ?? -1) > 0));

        if ($upcomingLimit > 0) {
            $upcomingBirthdays = array_slice($upcomingBirthdays, 0, $upcomingLimit);
        }

        return [
            'todayBirthdays' => $todayBirthdays,
            'upcomingBirthdays' => $upcomingBirthdays,
        ];
    }

    /**
     * @param  Collection<int,User>  $users
    * @return array{kind:'user',id:int,name:string, initials:string, next_date:CarbonImmutable, days_remaining:int, turning_age:int|null}|null
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
                'kind' => 'user',
                'id' => (int) ($user->id ?? 0),
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
          * @return array{kind:'person',id:int,is_child:bool,user_id:int|null,avatar_path:string|null,name:string, initials:string, next_date:CarbonImmutable, days_remaining:int, turning_age:int|null}|null
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
                'kind' => 'person',
                'id' => (int) ($person->id ?? 0),
                'is_child' => (bool) ($person->is_child ?? false),
                'user_id' => isset($person->user_id) ? (int) $person->user_id : null,
                'avatar_path' => isset($person->avatar_path) ? (string) $person->avatar_path : null,
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
          * @return array<int,array{kind:'user',id:int,name:string, initials:string, next_date:CarbonImmutable, days_remaining:int, turning_age:int|null}>
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
                'kind' => 'user',
                'id' => (int) ($user->id ?? 0),
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
          * @return array<int,array{kind:'person',id:int,is_child:bool,user_id:int|null,avatar_path:string|null,name:string, initials:string, next_date:CarbonImmutable, days_remaining:int, turning_age:int|null}>
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
                'kind' => 'person',
                'id' => (int) ($person->id ?? 0),
                'is_child' => (bool) ($person->is_child ?? false),
                'user_id' => isset($person->user_id) ? (int) $person->user_id : null,
                'avatar_path' => isset($person->avatar_path) ? (string) $person->avatar_path : null,
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

    private function shortFrDate(CarbonImmutable $date): string
    {
        $day = str_pad((string) ((int) $date->day), 2, '0', STR_PAD_LEFT);
        $month = (int) $date->month;

        $months = [
            1 => 'jan',
            2 => 'fév',
            3 => 'mar',
            4 => 'avr',
            5 => 'mai',
            6 => 'juin',
            7 => 'juil',
            8 => 'août',
            9 => 'sept',
            10 => 'oct',
            11 => 'nov',
            12 => 'déc',
        ];

        $m = $months[$month] ?? '';
        return trim($day . ' ' . $m);
    }

    private function ageLabel($turningAge, bool $isChild): ?string
    {
        if (!is_int($turningAge)) {
            return null;
        }

        // “Premium” rule of thumb: show age only when it’s meaningful (kids).
        if (!$isChild && $turningAge >= 18) {
            return null;
        }

        if ($turningAge <= 0) {
            return null;
        }

        return $turningAge . ' ' . ($turningAge === 1 ? 'an' : 'ans');
    }
}
