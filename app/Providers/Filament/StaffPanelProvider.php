<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\Admin\Resources\MembershipPlanResource;
use App\Filament\Admin\Resources\TrainerResource;
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
            ->tenant(Gym::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                ApplyTenantScopes::class,
                TeamsPermission::class,
            ], isPersistent: true)
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Users')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make()
                    ->label('Gym Management')
                    ->icon('heroicon-o-building-storefront'),
                NavigationGroup::make()
                    ->label('Expense Management')
                    ->icon('phosphor-invoice'),
                NavigationGroup::make()
                    ->label('Settings')
                    ->icon('heroicon-o-wrench-screwdriver'),
            ])
            ->resources([
                MembershipPlanResource::class,
                GymMemberResource::class,
                TrainerResource::class,
            ])
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

                // TeamsPermission::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
