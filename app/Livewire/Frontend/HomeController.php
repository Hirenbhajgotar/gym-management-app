<?php

namespace App\Livewire\Frontend;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

class HomeController extends Component
{
    public $selectedPlan = null;

    #[Layout('frontend.layouts.app_new')]
    public function render()
    {
        $allPlans = Cache::flexible('all_plans_for_home', [now()->addDay(), now()->addWeek()], function () {
            return Plan::with(['features'])
                ->active()
                ->orderBy('price_monthly')
                ->get();
        });

        $plans = $allPlans->where('is_enterprise', false);
        $enterprisePlans = $allPlans->where('is_enterprise', true);

        return view('livewire.frontend.home-controller', [
            'plans' => $plans,
            'enterprisePlans' => $enterprisePlans,
        ]);
    }

    public function selectPlan($planId)
    {
        $plan = Plan::with(['features'])->findOrFail($planId);

        // Store selected plan in session
        session(['selected_plan_id' => $plan->id]);

        // Redirect to gym owner registration
        return redirect()->route('gym.register');
    }

    public function startFreeTrial()
    {
        // Get the free plan (price_monthly = 0)
        $freePlan = Plan::with(['features'])
            ->where('price_monthly', 0)
            ->first();

        if ($freePlan) {
            session(['selected_plan_id' => $freePlan->id]);

            return redirect()->route('gym.register');
        }

        // If no free plan, redirect to registration anyway
        return redirect()->route('gym.register');
    }

    public function requestDemo()
    {
        return redirect()->route('request-demo');
    }
}
