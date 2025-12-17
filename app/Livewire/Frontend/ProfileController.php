<?php

namespace App\Livewire\Frontend;

use App\Models\Plan;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ProfileController extends Component
{
    public ?int $selectedPlan = null;

    public bool $renewingPlan = false;

    public bool $selectingPlan = false;

    public bool $upgradingPlan = false;

    public bool $loggingOut = false;

    #[Layout('frontend.layouts.profile')]
    public function render(): \Illuminate\Contracts\View\View
    {
        $user = auth()
            ->user()
            ->load(['roles', 'gyms']);

        // Get only active subscription
        $subscription = $user->subscription();
        $currentPlan = $subscription?->plan;

        // dd($subscription->wasTrial(), $user->hasUsedTrial());

        $allPlans = Plan::with(['features'])
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->get();

        return view('livewire.frontend.profile-controller', [
            'user' => $user,
            'subscription' => $subscription,
            'currentPlan' => $currentPlan,
            'allPlans' => $allPlans,
        ]);
    }

    public function selectPlan(int $planId): mixed
    {
        $this->selectingPlan = true;

        $plan = Plan::with(['features'])->findOrFail($planId);
        $user = auth()->user();

        $this->clearSessionData();

        // Check if user can get a free trial
        $hasUsedTrial = $user->hasUsedTrial();
        $trialDays =
            ! $hasUsedTrial && $plan->trial_days ? $plan->trial_days : 0;

        // If it's a free trial, create subscription directly without payment
        if ($trialDays > 0) {
            try {
                $subscriptionService = app(
                    \App\Services\SubscriptionService::class,
                );
                $subscriptionService->createSubscription($user, $plan, [
                    'billing_cycle' => 'yearly',
                    'trial_days' => $trialDays,
                ]);

                session()->flash(
                    'success',
                    "Your {$trialDays}-day free trial has started! You can start using {$plan->name} plan immediately.",
                );

                return redirect()->route('profile');
            } catch (\Exception $e) {
                session()->flash(
                    'error',
                    'Failed to start trial. Please try again.',
                );
                $this->selectingPlan = false;

                return null;
            }
        }

        // For paid plans, proceed to checkout
        session(['selected_plan_id' => $plan->id]);

        // Redirect to checkout
        return redirect()->route('subscriptions.checkout');
    }

    public function upgradePlan(int $planId): mixed
    {
        $this->upgradingPlan = true;

        $user = auth()->user();
        $currentSubscription = $user->subscription();

        // Check if user can upgrade (has active subscription)
        if (! $currentSubscription) {
            session()->flash(
                'error',
                'You need an active subscription to upgrade.',
            );
            $this->upgradingPlan = false;

            return null;
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

    public function renewPlan(): mixed
    {
        $this->renewingPlan = true;

        /**
         * @var \App\Models\User $user
         */
        $user = auth()->user();
        // Get active subscription or expired trial subscription that can be renewed
        $subscription = $user
            ->subscriptions()
            ->where(function ($query) {
                $query->whereIn('status', [\App\Enums\SubscriptionStatus::TRIAL, \App\Enums\SubscriptionStatus::ACTIVE])
                    ->orWhere(function ($q) {
                        $q->where('status', 'expired')->whereNotNull(
                            'trial_ends_at',
                        ); // Expired trials can be renewed
                    });
            })
            ->first();

        if (! $subscription) {
            session()->flash('error', 'No subscription found to renew.');
            $this->renewingPlan = false;

            return null;
        }

        /** @var \App\Models\Subscription $subscription */
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

    public function logout(): \Illuminate\Http\RedirectResponse|\Livewire\Features\SupportRedirects\Redirector
    {
        $this->loggingOut = true;

        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('home');
    }
    public function downloadInvoice(int $paymentId)
    {
        $payment = \App\Models\Payment::where('user_id', auth()->id())
            ->findOrFail($paymentId);

        return app(\App\Services\InvoiceService::class)->downloadInvoice($payment);
    }
}
