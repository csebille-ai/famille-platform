<?php

namespace App\Providers;

use App\Observers\UserObserver;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        RateLimiter::for('tarot-draw', function ($request) {
            $userId = (string) optional($request->user())->id;
            $key = $userId !== '' ? 'u:' . $userId : (string) $request->ip();

            return Limit::perMinute(10)->by($key);
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
