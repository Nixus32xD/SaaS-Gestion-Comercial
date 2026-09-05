<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentBusiness::class, fn (): CurrentBusiness => new CurrentBusiness);
        $this->app->scoped(CurrentBranch::class, fn (): CurrentBranch => new CurrentBranch);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('mercadopago-webhook', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip() ?: 'unknown');
        });

        Gate::define('super.admin', function (User $user): bool {
            return $user->isSuperAdmin();
        });

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasPermission($ability) ? true : null;
        });

        Gate::define('business.admin', function (User $user): bool {
            return $user->isBusinessAdmin() && $user->business_id !== null && $user->is_active;
        });
    }
}
