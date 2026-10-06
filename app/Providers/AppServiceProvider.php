<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configurações da empresa: dono e administradores.
        Gate::define('manage-tenant', fn (User $user) => app(CurrentTenant::class)->role()?->canManageTenant() ?? false);
    }
}
