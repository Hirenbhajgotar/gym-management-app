<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Gym Management Software for Indian Gyms | FluxFitness')]
class HomeController extends Component
{
    public $selectedPlan;

    #[Layout('frontend.layouts.app_new')]
    public function render()
    {
        $allPlans = Cache::flexible(Plan::ALL_PLANS_FOR_HOME_CACHE_KEY, [now()->addDay(), now()->addWeek()], function () {
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
        ])->layout('frontend.layouts.app_new', [
            'seo' => [
                'description' => 'Fluxfitness is an all-in-one gym management software with membership tracking, WhatsApp reminders, billing & attendance. Made for Indian gyms. Start free trial today',
                'keywords' => 'gym software, gym software india, gym management software for Indian gyms, fitness center management, gym membership software, gym scheduling software, gym billing software, gym attendance software, gym analytics software, gym CRM software, fitness center management system, branded gym member app, Automated WhatsApp Reminders for Gyms',
            ],
        ]);
    }

    public function selectPlan($planId)
    {
        $plan = Plan::with(['features'])->findOrFail($planId);

        // Store selected plan in session
        session(['selected_plan_id' => $plan->id]);

        // Redirect to gym owner registration
        return to_route('gym.register');
    }

    public function startFreeTrial()
    {
        // Get the free plan (price_monthly = 0)
        $freePlan = Plan::with(['features'])
            ->where('price_monthly', 0)
            ->first();

        if ($freePlan) {
            session(['selected_plan_id' => $freePlan->id]);

            return to_route('gym.register');
        }

        // If no free plan, redirect to registration anyway
        return to_route('gym.register');
    }

    public function requestDemo()
    {
        return to_route('request-demo');
    }
}
