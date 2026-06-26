<?php

namespace App\Livewire\Frontend;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

class ProfileController extends Component
{
    public ?int $selectedPlan = null;

    public bool $renewingPlan = false;

    public bool $selectingPlan = false;

    public bool $upgradingPlan = false;

    public bool $loggingOut = false;

    #[Layout('frontend.layouts.profile')]
    public function render(): View
    {
        $user = auth()
            ->user()
            ->load(['roles', 'gyms']);

        // Get only active subscription
        $subscription = $user->subscription();
        $currentPlan = $subscription?->plan;

        // dd($subscription->wasTrial(), $user->hasUsedTrial());

        $allPlans = Cache::flexible(Plan::PROFILE_ALL_PLANS_CACHE_KEY, [now()->addDay(), now()->addWeek()], function () {
            return Plan::with(['features'])
                ->where('is_active', true)
                ->where('is_enterprise', false)
                ->orderBy('price_monthly')
                ->get();
        });

        $latestPayments = Cache::flexible(Payment::PROFILE_LATEST_PAYMENTS_OF_AUTH_USER_CACHE_KEY . auth()->id(), [now()->addDay(), now()->addWeek()], function () {
            return auth()->user()->payments()->latest()->take(5)->get();
        });

        return view('livewire.frontend.profile-controller', [
            'user' => $user,
            'subscription' => $subscription,
            'currentPlan' => $currentPlan,
            'allPlans' => $allPlans,
            'latestPayments' => $latestPayments,
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
                $subscriptionService = resolve(
                    SubscriptionService::class,
                );
                $subscriptionService->createSubscription($user, $plan, [
                    'billing_cycle' => 'yearly',
                    'trial_days' => $trialDays,
                ]);

                session()->flash(
                    'success',
                    "Your {$trialDays}-day free trial has started! You can start using {$plan->name} plan immediately.",
                );

                return to_route('profile');
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
        return to_route('subscriptions.checkout');
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
        return to_route('subscriptions.checkout');
    }

    public function renewPlan(): mixed
    {
        $this->renewingPlan = true;

        /**
         * @var User $user
         */
        $user = auth()->user();
        // Get active subscription or expired trial subscription that can be renewed
        $subscription = $user
            ->subscriptions()
            ->where(function (QueryBuilder $query) {
                $query->whereIn('status', [SubscriptionStatus::TRIAL, SubscriptionStatus::ACTIVE])
                    ->orWhere(function (QueryBuilder $q) {
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

        /** @var Subscription $subscription */
        $this->clearSessionData();

        // Store renewal information in session
        session([
            'renew_plan_id' => $subscription->plan_id,
            'renew_subscription_id' => $subscription->id,
        ]);

        // Redirect to subscription checkout for renewal
        return to_route('subscriptions.checkout');
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

    public function logout(): RedirectResponse|Redirector
    {
        $this->loggingOut = true;

        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        return to_route('home');
    }

    public function downloadInvoice(int $paymentId)
    {
        $payment = Payment::query()->where('user_id', auth()->id())
            ->findOrFail($paymentId);

        return resolve(InvoiceService::class)->downloadInvoice($payment);
    }
}
