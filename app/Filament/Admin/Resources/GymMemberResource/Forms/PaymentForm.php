<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\Forms;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\MembershipPlanResource;
use App\Models\MembershipPlan;
use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

class PaymentForm
{
    /**
     * @return array<int|string, Field>
     */
    public static function make(): array
    {
        return [
            Select::make('membership_plan_id')
                ->label('Membership Plan')
                ->placeholder('Select a membership plan')
                ->reactive()
                ->required()
                ->afterStateUpdated(fn ($state, callable $set, callable $get) => static::updateMembershipDates($state, $set, $get))
                ->options(fn () => static::getActiveMembershipPlans())
                ->dehydrated(true)
                ->createOptionForm(function (): array {
                    /** @var array<int, Component> $planFormSchema */
                    $planFormSchema = MembershipPlanResource::planFormSchema();

                    return [
                        Section::make('Plan Details')
                            ->icon('heroicon-o-ticket')
                            ->description('Create a membership plan for your gym')
                            ->schema($planFormSchema)
                            ->columns(2),
                    ];
                })
                ->createOptionUsing(function (array $data): int {
                    /** @var array<string, mixed> $data */
                    $state = MembershipPlan::query()->create($data);

                    return $state->id;
                }),

            DatePicker::make('membership_start')
                ->placeholder('Select start date')
                ->native(false)
                ->closeOnDateSelection(true)
                ->live()
                ->required()
                ->default(now())
                ->reactive()
                ->afterStateUpdated(self::updateEndDate()),

            DatePicker::make('membership_end')
                ->placeholder('Auto-calculated based on plan')
                ->native(false)
                ->closeOnDateSelection(true)
                ->required(),

            TextInput::make('amount')
                ->numeric()
                ->required()
                ->prefix(self::getCurrencySymbol())
                ->minValue(0)
                ->step(0.01)
                ->disabled()
                ->dehydrated()
                ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

            TextInput::make('discount_amount')
                ->label('Discount')
                ->numeric()
                ->prefix('-')
                ->minValue(0)
                ->maxValue(function (callable $get) {
                    $planId = $get('membership_plan_id');
                    if (! $planId) {
                        return null;
                    }

                    /** @var MembershipPlan|null $plan */
                    $plan = MembershipPlan::query()->find($planId);

                    return $get('amount') ?? $plan->price ?? 0;
                })
                ->step(0.01)
                ->default(0)
                ->reactive()
                ->live(debounce: 400)
                ->afterStateUpdated(self::getDiscountCallback())
                ->helperText('Optional: enter any discount to subtract from the plan price.'),

            TextInput::make('amount_paid')
                ->label('Amount Paid Now')
                ->numeric()
                ->required()
                ->prefix(self::getCurrencySymbol())
                ->minValue(0)
                ->maxValue(fn (callable $get) => $get('amount') ?? 0)
                ->step(0.01)
                ->helperText('Enter the amount the member is paying now. If less than the plan price, remaining balance will be tracked.')
                ->default(fn (callable $get) => $get('amount') ?? 0)
                ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

            Select::make('payment_method')
                ->options(PaymentMethod::toSelectOptions())
                ->required()
                ->default(PaymentMethod::CASH->value),

            DatePicker::make('payment_date')
                ->native(false)
                ->closeOnDateSelection(true)
                ->required()
                ->default(now()),

            Select::make('currency')
                ->options(Currency::toSelectOptions())
                ->required()
                ->default(Currency::INR->value)
                ->reactive(),

            ToggleButtons::make('status')
                ->inline()
                ->options(PaymentStatus::class)
                ->required(),

            Textarea::make('notes')
                ->maxLength(65535)
                ->placeholder('e.g., Paid via UPI ID: user@upi')
                ->extraAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'])
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<int|string, Field>
     */
    public static function only(array $fields): array
    {
        $all = self::make();

        return array_filter($all, fn (Field $field): bool => in_array($field->getName(), $fields, true));
    }

    /**
     * Update membership dates based on selected plan
     */
    protected static function updateMembershipDates(mixed $state, callable $set, callable $get): void
    {
        if (! $state) {
            return;
        }

        /** @var MembershipPlan|null $plan */
        $plan = MembershipPlan::query()->find($state);
        if (! $plan || ! $plan->duration) {
            return;
        }

        /** @var string|Carbon $start */
        $start = $get('membership_start') ?? now();
        $set('membership_end', Date::parse($start)->addMonths($plan->duration)->toDateString());
        $set('amount', $plan->price);
        $set('discount_amount', 0);
        $set('amount_paid', $plan->price);
    }

    /**
     * Get active membership plans for the member's gym
     *
     * @return array<int, string>
     */
    protected static function getActiveMembershipPlans(): array
    {
        /** @var array<int, string> $plans */
        $plans = MembershipPlan::query()
            ->where('is_active', true)
            ->pluck('name', 'id')
            ->all();

        return $plans;
    }

    /**
     * Update end date when start date changes
     */
    protected static function updateEndDate(): Closure
    {
        return function (mixed $state, callable $set, callable $get): void {
            $planId = $get('membership_plan_id');

            if (! $planId || ! $state) {
                return;
            }

            /** @var MembershipPlan|null $plan */
            $plan = MembershipPlan::query()->find($planId);
            if (! $plan || ! $plan->duration) {
                return;
            }

            /** @var string|Carbon $state */
            $set('membership_end', Date::parse($state)->addMonths($plan->duration)->toDateString());
        };
    }

    /**
     * Get currency symbol based on selected currency
     */
    protected static function getCurrencySymbol(): Closure
    {
        return function (callable $get): string {
            $currency = Currency::tryFrom(is_string($get('currency')) ? $get('currency') : Currency::INR->value);

            return $currency?->getSymbol() ?? Currency::INR->getSymbol();
        };
    }

    public static function getDiscountCallback(): Closure
    {
        return function ($state, callable $set, callable $get): void {
            $planId = $get('membership_plan_id');
            if (! $planId) {
                return;
            }

            /** @var MembershipPlan|null $plan */
            $plan = MembershipPlan::query()->find($planId);
            if (! $plan) {
                return;
            }

            $discount = (float) (is_numeric($state) ? $state : 0);
            $finalAmount = max(0, ((float) $plan->price) - $discount);

            $set('amount', $finalAmount);
            $set('amount_paid', $finalAmount);
        };
    }
}
