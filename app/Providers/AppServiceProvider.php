<?php

namespace App\Providers;

use App\Listeners\UpdateSpatiePermissionCacheOnTenantSwitch;
use App\Models\Gym;
use App\Observers\GymObserver;
use App\Notifications\WhatsAppChannel;
use Filament\Events\TenantSet;
use Illuminate\Support\Facades\Event as FacadesEvent;
use Illuminate\Support\Facades\Notification;
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
        Gym::observe(GymObserver::class);

        FacadesEvent::listen(
            TenantSet::class,
            UpdateSpatiePermissionCacheOnTenantSwitch::class
        );

        Notification::extend('whatsapp', function ($app) {
            return new WhatsAppChannel();
        });
    }
}
