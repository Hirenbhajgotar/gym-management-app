<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Tenancy\EditGymProfile;
use App\Filament\Admin\Pages\Tenancy\RegisterGym;
use App\Http\Middleware\ApplyTenantScopes;
use App\Models\Gym;
use Filafly\FilamentPhosphorIcons\PhosphorIcons;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // ->registration()
            ->passwordReset()
            ->emailVerification()
            ->profile(isSimple: false)
            ->colors([
                'primary' => Color::Amber,
                'danger' => Color::Red,
            ])
            ->spa()
            ->tenant(Gym::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                // SyncShieldTenant::class,
                ApplyTenantScopes::class,
            ], isPersistent: true)
            ->tenantRegistration(RegisterGym::class)
            ->tenantProfile(EditGymProfile::class)
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Users')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make()
                    ->label('Gym Management')
                    ->icon('heroicon-o-building-storefront'),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling(null)
            // ->unsavedChangesAlerts()
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\\Filament\\Admin\\Clusters')
            ->pages([
                // Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
                // GymStatsOverview::class,
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
                // \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
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

                // FilamentGeneralSettingsPlugin::make()
                //     ->canAccess(fn() => auth()->user()->hasRole('super_admin'))
                //     ->setSort(3)
                //     ->setIcon('heroicon-o-cog')
                //     ->setNavigationGroup('Settings')
                //     ->setTitle('General Settings')
                //     ->setNavigationLabel('General Settings'),
                PhosphorIcons::make(),

                // ActivitylogPlugin::make(),
            ]);
    }
}
