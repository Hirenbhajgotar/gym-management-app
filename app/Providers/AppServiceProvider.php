<?php

namespace App\Providers;

use App\Listeners\UpdateSpatiePermissionCacheOnTenantSwitch;
use Filament\Events\TenantSet;
use Illuminate\Support\Facades\Event as FacadesEvent;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FacadesEvent::listen(
            TenantSet::class,
            UpdateSpatiePermissionCacheOnTenantSwitch::class
        );
    }
}
