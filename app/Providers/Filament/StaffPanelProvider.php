<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ApplyTenantScopes;
use App\Http\Middleware\TeamsPermission;
use App\Models\Gym;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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

class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('staff')
            ->path('staff')
            // ->authGuard('web')
            ->login()
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandLogo(asset('fluxfitness-logo.svg'))
            ->brandLogoHeight('3rem')
            ->spa()
            ->spaUrlExceptions(fn (): array => [
                // Allow all files in the import/templates directory
                // Allow the download of the sample file
                url('import/templates/*'),
                '/import/templates/*',
            ])
            ->tenant(Gym::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                ApplyTenantScopes::class,
                TeamsPermission::class,
            ], isPersistent: true)
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('CRM'),

                NavigationGroup::make()
                    ->label('Users'),

                NavigationGroup::make()
                    ->label('Gym Management'),

                NavigationGroup::make()
                    ->label('Expense Management'),

                NavigationGroup::make()
                    ->label('WhatsApp'),

                NavigationGroup::make()
                    ->label('Device Management'),

                NavigationGroup::make()
                    ->label('Settings')
                    ->collapsed(true),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling(null)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\\Filament\\Admin\\Clusters')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverResources(in: app_path('Filament/Staff/Resources'), for: 'App\\Filament\\Staff\\Resources')
            ->discoverPages(in: app_path('Filament/Staff/Pages'), for: 'App\\Filament\\Staff\\Pages')
            ->pages([
                \App\Filament\Staff\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Staff/Widgets'), for: 'App\\Filament\\Staff\\Widgets')
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

                TeamsPermission::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
