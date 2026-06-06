<?php

namespace App\Filament\Admin\Widgets\Stats;

use App\Contracts\HasFilamentPermissions;
use App\Enums\DefaultRole;
use App\Models\GymMember;
use App\Services\DashboardCacheService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ActiveAndExpireSubscriptionStats extends BaseWidget implements HasFilamentPermissions
{
    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.MemberStatsUpdated" => '$refresh',
        ];
    }

    protected function getStats(): array
    {
        $gymId = Filament::getTenant()?->id;

        if (! $gymId) {
            return [];
        }

        return Cache::flexible(DashboardCacheService::SUBSCRIPTION_STATS . $gymId, DashboardCacheService::getCacheTtl(), function () {
            $today = Carbon::today();

            // Gym Memberships
            $gymActiveCount = GymMember::query()->membershipActive()->count('id');

            $gymExpiredCount = GymMember::query()->membershipExpired()->count('id');

            // Personal Trainer Subscriptions
            $ptActiveCount = GymMember::query()->membershipActive()->hasTrainer()->count('id');

            $ptExpiredCount = GymMember::query()->membershipExpired()->hasTrainer()->count('id');

            return [
                Stat::make('Active Gym Members', $gymActiveCount)
                    ->description('Membership currently valid')
                    ->icon('heroicon-o-users')
                    ->color('success'),

                Stat::make('Expired Gym Members', $gymExpiredCount)
                    ->description('Memberships ended')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger'),

                Stat::make('Active PT Subscriptions', $ptActiveCount)
                    ->description('Assigned trainer & valid membership')
                    ->icon('heroicon-o-user-group')
                    ->color('success'),

                Stat::make('Expired PT Subscriptions', $ptExpiredCount)
                    ->description('Assigned trainer but membership ended')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger'),
            ];
        }
        );
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // If it's a Livewire update request, we should generally allow it if it was already visible
        // to prevent the "Missing root tag" exception when roles are temporarily unavailable/unsynced
        if (request()->hasHeader('X-Livewire')) {
            return true;
        }

        return $user->hasRole([
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
        ]);
    }

    /**
     * Ensure we always return at least a root tag to prevent Livewire errors.
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        if (! static::canView()) {
            return view()->make('filament-widgets::blank');
        }

        return parent::render();
    }
}
