<?php

namespace App\Providers\Filament;

use App\Filament\SuperAdmin\Pages\Pulse;
use App\Filament\SuperAdmin\Pages\PulseDashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('super-admin')
            ->path('super-admin')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->login()
            ->authGuard('web')
            ->authPasswordBroker('users')
            ->spa()
            ->databaseNotifications()
            ->databaseNotificationsPolling(null)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Settings')
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->resources([
                // GymResource::class,

            ])
            ->discoverResources(in: app_path('Filament/SuperAdmin/Resources'), for: 'App\\Filament\\SuperAdmin\\Resources')
            ->discoverPages(in: app_path('Filament/SuperAdmin/Pages'), for: 'App\\Filament\\SuperAdmin\\Pages')
            ->discoverClusters(in: app_path('Filament/SuperAdmin/Clusters'), for: 'App\\Filament\\SuperAdmin\\Clusters')
            ->pages([
                Pages\Dashboard::class,
                // PulseDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/SuperAdmin/Widgets'), for: 'App\\Filament\\SuperAdmin\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                // FilamentShieldPlugin::make()
                //     ->gridColumns([
                //         'default' => 1,
                //         'sm' => 2,
                //         'lg' => 3
                //     ])
                //     ->sectionColumnSpan(1)
                //     ->checkboxListColumns([
                //         'default' => 1,
                //         'sm' => 2,
                //         'lg' => 4,
                //     ])
                //     ->resourceCheckboxListColumns([
                //         'default' => 1,
                //         'sm' => 2,
                //     ]),
            ]);
    }
}
