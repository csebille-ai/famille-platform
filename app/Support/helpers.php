<?php

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

if (!function_exists('avatarUrl')) {
    /**
     * Returns a public URL for the avatar photo, or null when missing.
     *
     * - Users: stored on the `public` disk as `avatar_path`.
     * - People: uses `avatar_path` as stored (legacy).
     */
    function avatarUrl(User|Person|null $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        if ($subject instanceof User) {
            $path = trim((string) ($subject->avatar_path ?? ''));
            if ($path === '') {
                return null;
            }

            $url = Storage::disk('public')->url($path);

            $ts = optional($subject->avatar_updated_at)->getTimestamp();
            if (is_int($ts) && $ts > 0) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $ts;
            }

            return $url;
        }

        // Person: legacy avatar path (disk depends on how it was stored).
        $path = trim((string) ($subject->avatar_path ?? ''));
        if ($path === '') {
            return null;
        }

        try {
            return Storage::url($path);
        } catch (Throwable) {
            return null;
        }
    }
}
