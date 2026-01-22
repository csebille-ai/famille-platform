<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Person;
use App\Observers\EventObserver;
use App\Observers\UserObserver;
use App\Policies\EventPolicy;
use App\Policies\PersonPolicy;
use App\Services\Astro\NatalChartProvider;
use App\Services\Astro\NullNatalChartProvider;
use App\Models\User;
use App\Models\ActivityEvent;
use App\Events\ChatMessageSent;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event as EventFacade;
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
        $helpers = app_path('Support/helpers.php');
        if (is_file($helpers)) {
            require_once $helpers;
        }

        $this->app->bind(NatalChartProvider::class, NullNatalChartProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Product decision: the UI is French-only.
        // Force the runtime locale to avoid lingering APP_LOCALE=en in cached config/env.
        $locale = 'fr';
        app()->setLocale($locale);
        Carbon::setLocale($locale);
        CarbonImmutable::setLocale($locale);

        User::observe(UserObserver::class);
        Event::observe(EventObserver::class);

        EventFacade::listen(Login::class, function (Login $event): void {
            try {
                $user = $event->user;
                if (!$user instanceof User) {
                    return;
                }
                $now = CarbonImmutable::now();
                $user->forceFill(['last_login_at' => $now])->save();
                ActivityEvent::create([
                    'created_at' => $now,
                    'user_id' => $user->id,
                    'type' => 'login',
                    'route' => '/login',
                    'metadata' => ['guard' => $event->guard],
                ]);
            } catch (\Throwable) {
                // ignore
            }
        });

        EventFacade::listen(Logout::class, function (Logout $event): void {
            try {
                $user = $event->user;
                if (!$user instanceof User) {
                    return;
                }
                $now = CarbonImmutable::now();
                ActivityEvent::create([
                    'created_at' => $now,
                    'user_id' => $user->id,
                    'type' => 'logout',
                    'route' => '/logout',
                    'metadata' => ['guard' => $event->guard],
                ]);
            } catch (\Throwable) {
                // ignore
            }
        });

        EventFacade::listen(ChatMessageSent::class, function (ChatMessageSent $event): void {
            try {
                $now = CarbonImmutable::now();
                $userId = $event->message?->user_id;
                ActivityEvent::create([
                    'created_at' => $now,
                    'user_id' => $userId,
                    'type' => 'chat_message',
                    'route' => '/chat',
                    'metadata' => ['chat_message_id' => $event->message?->id],
                ]);
            } catch (\Throwable) {
                // ignore
            }
        });

        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);

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
