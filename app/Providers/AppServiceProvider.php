<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\DefaultRole;
use App\Events\BiometricConsentGiven;
use App\Events\BiometricConsentRevoked;
use App\Events\PaymentCompleted;
use App\Listeners\HandleConsentRevoked;
use App\Listeners\LogUserLogin;
use App\Listeners\NotifyStaffOnConsentGiven;
use App\Listeners\SubscriptionProvisioner;
use App\Listeners\UpdateSpatiePermissionCacheOnTenantSwitch;
use App\Notifications\Channels\FilamentChannel;
use App\Notifications\WhatsAppChannel;
use App\Permissions\TeamPermissionRegistrar;
use Filament\Events\TenantSet;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as FacadesEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
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
        Model::preventLazyLoading(! app()->isProduction());

        $this->configureCommands();

        $this->registerFilamentColors();

        $this->registerPermissionRegistrar();

        $this->registerRateLimiter();

        $this->registerSpatiePermissionListener();

        $this->registerAuthEventListeners();

        $this->registerBiometricEventListeners();

        $this->registerPaymentEventListeners();

        $this->registerNotificationChannels();

        $this->defineGates();
    }

    private function registerFilamentColors(): void
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
    }

    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(app()->isProduction());
    }

    private function registerAuthEventListeners(): void
    {
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
    }

    private function registerBiometricEventListeners(): void
    {
        FacadesEvent::listen(
            BiometricConsentGiven::class,
            NotifyStaffOnConsentGiven::class
        );

        FacadesEvent::listen(
            BiometricConsentRevoked::class,
            HandleConsentRevoked::class
        );
    }

    private function registerPaymentEventListeners(): void
    {
        // Provision a subscription once its payment is completed. The listener
        // is idempotent, so a webhook redelivery never creates a duplicate.
        FacadesEvent::listen(
            PaymentCompleted::class,
            SubscriptionProvisioner::class
        );
    }

    private function registerSpatiePermissionListener(): void
    {
        FacadesEvent::listen(
            TenantSet::class,
            UpdateSpatiePermissionCacheOnTenantSwitch::class
        );
    }

    private function registerRateLimiter(): void
    {
        RateLimiter::for('whatsapp-outbound-limit', function (object $job) {
            return Limit::perSecond(30)->by('whatsapp-gateway');
        });
    }

    private function registerNotificationChannels(): void
    {
        Notification::extend('whatsapp', function ($app) {
            return new WhatsAppChannel();
        });

        Notification::extend('filament', function ($app) {
            return new FilamentChannel();
        });
    }

    private function registerPermissionRegistrar(): void
    {
        // Replace Spatie's registrar with per-gym scoped version
        // This loads only current gym's permissions (~500) instead of all (35k+)
        $this->app->singleton(function ($app): PermissionRegistrar {
            return new TeamPermissionRegistrar($app['cache']);
        });
    }

    private function defineGates(): void
    {
        // Gate for queue job
        Gate::define('viewVantage', function ($user = null) {
            // Only allow super-admins
            if (! $user) {
                return false;
            }
            if (! $user->hasRole([DefaultRole::SUPER_ADMIN->value])) {
                return false;
            }

            return true;
        });
    }
}
