<?php

namespace App\Providers;

use App\Events\BiometricConsentGiven;
use App\Events\BiometricConsentRevoked;
use App\Listeners\HandleConsentRevoked;
use App\Listeners\LogUserLogin;
use App\Listeners\NotifyStaffOnConsentGiven;
use App\Listeners\UpdateSpatiePermissionCacheOnTenantSwitch;
use App\Models\Gym;
use App\Notifications\Channels\FilamentChannel;
use App\Notifications\WhatsAppChannel;
use App\Permissions\TeamPermissionRegistrar;
use Filament\Events\TenantSet;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event as FacadesEvent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

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
        FilamentColor::register([
            'primary' => Color::Amber,
            'success' => Color::Green,
            'danger' => Color::Red,
            'info' => Color::Sky,
            'warning' => Color::Yellow,
            'blue' => Color::Blue,
            'neutral' => Color::Neutral,
            'orange' => Color::Orange,
            'lime' => Color::Lime,
            'teal' => Color::Teal,
            'cyan' => Color::Cyan,
            'rose' => Color::Rose,
            'purple' => Color::Purple,
            'pink' => Color::Pink,
        ]);
        // Replace Spatie's registrar with per-gym scoped version
        // This loads only current gym's permissions (~500) instead of all (35k+)
        $this->app->singleton(PermissionRegistrar::class, function ($app) {
            return new TeamPermissionRegistrar($app['cache']);
        });

        FacadesEvent::listen(
            TenantSet::class,
            UpdateSpatiePermissionCacheOnTenantSwitch::class
        );

        // Register login event listener
        FacadesEvent::listen(
            Login::class,
            [LogUserLogin::class, 'handleLogin']
        );
        FacadesEvent::listen(
            Logout::class,
            [LogUserLogin::class, 'handleLogout']
        );
        FacadesEvent::listen(
            Failed::class,
            [LogUserLogin::class, 'handleFailed']
        );

        // Biometric consent event listeners
        FacadesEvent::listen(
            BiometricConsentGiven::class,
            NotifyStaffOnConsentGiven::class
        );

        FacadesEvent::listen(
            BiometricConsentRevoked::class,
            HandleConsentRevoked::class
        );

        Notification::extend('whatsapp', function ($app) {
            return new WhatsAppChannel;
        });

        Notification::extend('filament', function ($app) {
            return new FilamentChannel;
        });
    }
}
