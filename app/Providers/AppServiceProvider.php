<?php

namespace App\Providers;

use App\Listeners\LogUserLogin;
use App\Listeners\UpdateSpatiePermissionCacheOnTenantSwitch;
use App\Models\Attendances;
use App\Models\Expense;
use App\Models\Gym;
use App\Models\MemberPayment;
use App\Models\ReportGoal;
use App\Models\Subscription;
use App\Notifications\Channels\FilamentChannel;
use App\Notifications\WhatsAppChannel;
use App\Observers\AttendanceObserver;
use App\Observers\ExpenseObserver;
use App\Observers\GymObserver;
use App\Observers\MemberPaymentObserver;
use App\Observers\ReportGoalObserver;
use App\Observers\SubscriptionObserver;
use App\Permissions\TeamPermissionRegistrar;
use Filament\Events\TenantSet;
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
        // Replace Spatie's registrar with per-gym scoped version
        // This loads only current gym's permissions (~500) instead of all (35k+)
        $this->app->singleton(PermissionRegistrar::class, function ($app) {
            return new TeamPermissionRegistrar($app['cache']);
        });

        Gym::observe(GymObserver::class);
        Subscription::observe(SubscriptionObserver::class);
        Expense::observe(ExpenseObserver::class);
        ReportGoal::observe(ReportGoalObserver::class);
        Attendances::observe(AttendanceObserver::class);
        MemberPayment::observe(MemberPaymentObserver::class);

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

        Notification::extend('whatsapp', function ($app) {
            return new WhatsAppChannel;
        });

        Notification::extend('filament', function ($app) {
            return new FilamentChannel;
        });
    }
}
