<?php

namespace App\Livewire\Frontend;

use App\Models\Plan;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ProfileController extends Component
{
    public $selectedPlan = null;

    public $renewingPlan = false;

    public $selectingPlan = false;

    public $upgradingPlan = false;

    public $loggingOut = false;

    #[Layout('frontend.layouts.profile')]
    public function render()
    {
        $user = auth()->user()->load(['roles', 'gyms']);
        // Get only active subscription
        $subscription = $user->subscription()->active()->with('plan')->first();
        $currentPlan = $subscription?->plan;
        $allPlans = Plan::with(['features'])->active()->orderBy('price_monthly')->get();

        return view('livewire.frontend.profile-controller', [
            'user' => $user,
            'subscription' => $subscription,
            'currentPlan' => $currentPlan,
            'allPlans' => $allPlans,
        ]);
    }

    public function selectPlan($planId)
    {
        $this->selectingPlan = true;

        $plan = Plan::with(['features'])->findOrFail($planId);

        $this->clearSessionData();

        // Store selected plan in session
        session(['selected_plan_id' => $plan->id]);

        // Redirect to checkout
        return redirect()->route('subscriptions.checkout');
    }

    public function upgradePlan($planId)
    {
        $this->upgradingPlan = true;

        $user = auth()->user();
        $currentSubscription = $user->subscription()->active()->first();

        // Check if user can upgrade (has active subscription)
        if (! $currentSubscription) {
            session()->flash('error', 'You need an active subscription to upgrade.');
            $this->upgradingPlan = false;

            return;
        }

        // $newPlan = Plan::findOrFail($planId);

        // Check if the new plan is actually an upgrade (higher price)
        // if ($newPlan->price_yearly <= $currentSubscription->plan->price_yearly) {
        //     session()->flash('error', 'Please select a higher-tier plan to upgrade.');
        //     $this->upgradingPlan = false;
        //     return;
        // }

        $this->clearSessionData();

        // Store upgrade information in session
        session([
            'upgrade_plan_id' => $planId,
            'current_subscription_id' => $currentSubscription->id,
        ]);

        // Redirect to checkout for upgrade
        return redirect()->route('subscriptions.checkout');
    }

    public function renewPlan()
    {
        $this->renewingPlan = true;

        $user = auth()->user();
        // Get active subscription or expired trial subscription that can be renewed
        $subscription = $user->subscription()->where(function ($query) {
            $query->active()->orWhere(function ($q) {
                $q->where('status', 'expired')
                    ->whereNotNull('trial_ends_at'); // Expired trials can be renewed
            });
        })->first();

        if (! $subscription) {
            session()->flash('error', 'No subscription found to renew.');
            $this->renewingPlan = false;

            return;
        }

        $this->clearSessionData();

        // Store renewal information in session
        session([
            'renew_plan_id' => $subscription->plan_id,
            'renew_subscription_id' => $subscription->id,
        ]);

        // Redirect to subscription checkout for renewal
        return redirect()->route('subscriptions.checkout');
    }

    private function clearSessionData(): void
    {
        session()->forget([
            'renew_plan_id',
            'renew_subscription_id',
            'upgrade_plan_id',
            'current_subscription_id',
            'selected_plan_id',
        ]);
    }

    public function logout()
    {
        $this->loggingOut = true;

        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('home');
    }
}
