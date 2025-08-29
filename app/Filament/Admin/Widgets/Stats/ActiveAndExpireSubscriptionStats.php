<?php

namespace App\Filament\Admin\Widgets\Stats;

use App\Enums\DefaultRole;
use App\Models\GymMember;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveAndExpireSubscriptionStats extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
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

    public static function canView(): bool
    {
        return auth()->user()->hasRole([
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
        ]);
    }
}
