<?php

namespace App\Providers;

use App\Services\SettingsService;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Super admin passes every permission check. Agents never hold admin
        // permissions, so they fall through to the normal (denying) checks.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Staff routes must never load a super admin or an agent.
        Route::bind('staff', fn (string $id) => User::ofType(UserType::Staff)->findOrFail($id));
        Route::bind('agent', fn (string $id) => User::ofType(UserType::Agent)->findOrFail($id));
    }
}
