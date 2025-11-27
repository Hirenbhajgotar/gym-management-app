<?php

namespace App\Providers;

use App\Listeners\UpdateSpatiePermissionCacheOnTenantSwitch;
use App\Models\Gym;
use App\Models\Subscription;
use App\Notifications\Channels\FilamentChannel;
use App\Notifications\WhatsAppChannel;
use App\Observers\GymObserver;
use App\Observers\SubscriptionObserver;
use Filament\Events\TenantSet;
use Illuminate\Database\Eloquent\Model;
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
        Model::automaticallyEagerLoadRelationships();

        Gym::observe(GymObserver::class);
        Subscription::observe(SubscriptionObserver::class);

        FacadesEvent::listen(
            TenantSet::class,
            UpdateSpatiePermissionCacheOnTenantSwitch::class
        );

        Notification::extend('whatsapp', function ($app) {
            return new WhatsAppChannel;
        });

        Notification::extend('filament', function ($app) {
            return new FilamentChannel;
        });
    }
}
