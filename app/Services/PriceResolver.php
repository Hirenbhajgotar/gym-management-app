<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Currency;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

class PriceResolver
{
    /**
     * Resolve the effective price for a plan + billing cycle at a point in time.
     * Reads the versioned plan_prices history, falling back to the plan columns
     * for any plan that has no versioned row yet.
     *
     * @return array{amount: float, currency: Currency}
     */
    public function resolve(Plan $plan, string $cycle, ?Carbon $at = null): array
    {
        $at ??= Date::now();

        $priceRow = PlanPrice::query()
            ->where('plan_id', $plan->id)
            ->effective($at)
            ->latest('effective_from')
            ->first();

        if ($priceRow) {
            $amount = $cycle === 'yearly' ? $priceRow->price_yearly : $priceRow->price_monthly;
            $currency = $priceRow->currency;
        } else {
            $amount = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
            $currency = $plan->currency;
        }

        return [
            'amount' => (float) ($amount ?? 0),
            'currency' => $currency instanceof Currency ? $currency : Currency::from($currency),
        ];
    }
}
