<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Person;
use App\Observers\UserObserver;
use App\Policies\EventPolicy;
use App\Policies\PersonPolicy;
use App\Services\Astro\Images\CloudflareWorkersAiImageProvider;
use App\Services\Astro\Images\ImageProvider;
use App\Services\Astro\Images\NullImageProvider;
use App\Services\Astro\Images\OpenAiImageProvider;
use App\Services\Astro\NatalChartProvider;
use App\Services\Astro\NullNatalChartProvider;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NatalChartProvider::class, NullNatalChartProvider::class);

        $this->app->bind(ImageProvider::class, function () {
            $provider = strtolower(trim((string) env('AVATAR_ASTRO_IMAGE_PROVIDER', 'auto')));

            if ($provider === 'auto') {
                $hasCloudflare = trim((string) config('services.cloudflare.account_id')) !== ''
                    && trim((string) config('services.cloudflare.api_token')) !== '';

                $provider = $hasCloudflare ? 'cloudflare' : 'openai';
            }

            return match ($provider) {
                'openai' => app(OpenAiImageProvider::class),
                'cloudflare', 'workersai', 'workers-ai' => app(CloudflareWorkersAiImageProvider::class),
                'none', 'null', '' => app(NullImageProvider::class),
                default => app(NullImageProvider::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);

        RateLimiter::for('tarot-draw', function ($request) {
            $userId = (string) optional($request->user())->id;
            $key = $userId !== '' ? 'u:' . $userId : (string) $request->ip();

            return Limit::perMinute(10)->by($key);
        });

        RateLimiter::for('avatar-astro-generate', function ($request) {
            $userId = (string) optional($request->user())->id;
            $key = $userId !== '' ? 'u:' . $userId : (string) $request->ip();

            return Limit::perMinute(3)->by($key);
        });

        Gate::define('manage-users', function (User $user): bool {
            return ($user->role ?? 'member') === 'admin';
        });

        Gate::define('images-upload', function (User $user): bool {
            return in_array($user->role ?? 'member', ['member', 'editor', 'admin'], true);
        });

        Gate::define('images-delete', function (User $user): bool {
            return ($user->role ?? 'member') === 'admin';
        });

        Gate::define('videos-delete', function (User $user): bool {
            return ($user->role ?? 'member') === 'admin';
        });

        Gate::define('cloud-write', function (User $user): bool {
            return in_array($user->role ?? 'member', ['editor', 'admin'], true);
        });

        Gate::define('manage-cloud', function (User $user): bool {
            return in_array($user->role ?? 'member', ['editor', 'admin'], true);
        });

        Gate::define('playlists-delete-items', function (User $user): bool {
            return ($user->role ?? 'member') === 'admin';
        });
    }
}
