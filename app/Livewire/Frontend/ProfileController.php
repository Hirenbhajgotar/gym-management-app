<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Enums\SubscriptionStatus;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PriceResolver;
use App\Services\SubscriptionService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

class ProfileController extends Component
{
    #[Locked]
    public ?int $selectedPlan = null;

    public bool $renewingPlan = false;

    public bool $selectingPlan = false;

    public bool $upgradingPlan = false;

    public bool $loggingOut = false;

    #[Layout('frontend.layouts.profile')]
    public function render(): View
    {
        $user = Auth::user()->load(['roles', 'gyms.address']);

        // Get only active subscription
        $subscription = $user->subscription();
        $currentPlan = $subscription?->plan;

        $allPlans = Cache::flexible(Plan::PROFILE_ALL_PLANS_CACHE_KEY, [now()->addDay(), now()->addWeek()], function () {
            return Plan::with(['features'])
                ->where('is_active', true)
                ->where('is_enterprise', false)
                ->orderBy('price_monthly')
                ->get();
        });

        $latestPayments = Cache::flexible(Payment::PROFILE_LATEST_PAYMENTS_OF_AUTH_USER_CACHE_KEY . Auth::id(), [now()->addDay(), now()->addWeek()], function () {
            return Auth::user()->payments()->latest()->take(5)->get();
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

        /** @var User $user */
        $user = Auth::user();

        // Check if user already has an active subscription
        $activeSubscription = Subscription::query()->where('user_id', $user->id)
            ->active()
            ->first();

        throw_if($activeSubscription, Exception::class, 'User already has an active subscription');

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
            } catch (Exception $e) {
                session()->flash(
                    'error',
                    'Failed to start trial. Please try again.',
                );
                $this->selectingPlan = false;

                return null;
            }
        }

        // For paid plans, create a checkout session and redirect with its token.
        $price = resolve(PriceResolver::class)->resolve($plan, 'yearly');

        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_NEW,
            'plan_id' => $plan->id,
            'billing_cycle' => 'yearly',
            'amount' => $price['amount'],
            'currency' => $price['currency'],
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => ['trial_days' => 0, 'has_used_trial_before' => $hasUsedTrial],
        ]);

        return to_route('subscriptions.checkout', ['token' => $session->token]);
    }

    public function upgradePlan(int $planId): mixed
    {
        $this->upgradingPlan = true;

        /** @var User $user */
        $user = Auth::user();
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

        $newPlan = Plan::query()->findOrFail($planId);

        $cycle = $currentSubscription->billing_cycle;
        $newPrice = resolve(PriceResolver::class)->resolve($newPlan, $cycle)['amount'];

        // Charge only the prorated difference when proration is enabled (G10).
        if (config()->boolean('subscriptions.proration_enabled')) {
            $totalDays = $cycle === 'yearly' ? 365 : 30;
            $remaining = max(0, now()->diffInDays($currentSubscription->current_period_end, false));
            $credit = $currentSubscription->price * $remaining / $totalDays;
            $amount = max(0, round($newPrice - $credit, 2));
        } else {
            $amount = $newPrice;
        }

        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_UPGRADE,
            'plan_id' => $newPlan->id,
            'subscription_id' => $currentSubscription->id,
            'billing_cycle' => $cycle,
            'amount' => $amount,
            'currency' => $currentSubscription->currency,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [
                'from_plan' => $currentSubscription->plan->name,
                'to_plan' => $newPlan->name,
            ],
        ]);

        return to_route('subscriptions.checkout', ['token' => $session->token]);
    }

    public function renewPlan(): mixed
    {
        $this->renewingPlan = true;

        /**
         * @var User $user
         */
        $user = Auth::user();
        // Get active subscription or expired trial subscription that can be renewed
        $subscription = $user
            ->subscriptions()
            ->where(function (Builder $query) {
                $query->whereIn('status', [SubscriptionStatus::TRIAL, SubscriptionStatus::ACTIVE])
                    ->orWhere(function (Builder $q) {
                        $q->where('status', SubscriptionStatus::EXPIRED)
                            ->whereNotNull('trial_ends_at'); // Expired trials can be renewed
                    });
            })
            ->first();

        if (! $subscription) {
            session()->flash('error', 'No subscription found to renew.');
            $this->renewingPlan = false;

            return null;
        }

        // Charge the CURRENT effective plan price, not the stale price stored on
        // the subscription at signup (fixes G1: renewal pays old price).
        $price = resolve(PriceResolver::class)->resolve($subscription->plan, $subscription->billing_cycle);

        $session = CheckoutSession::query()->create([
            'token' => CheckoutSession::generateToken(),
            'user_id' => $user->id,
            'type' => CheckoutSession::TYPE_RENEWAL,
            'plan_id' => $subscription->plan_id,
            'subscription_id' => $subscription->id,
            'billing_cycle' => $subscription->billing_cycle,
            'amount' => $price['amount'],
            'currency' => $subscription->currency,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);

        return to_route('subscriptions.checkout', ['token' => $session->token]);
    }

    public function logout(): RedirectResponse|Redirector
    {
        $this->loggingOut = true;

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return to_route('home');
    }

    public function downloadInvoice(int $paymentId)
    {
        $payment = Payment::query()->where('user_id', Auth::id())
            ->findOrFail($paymentId);

        return resolve(InvoiceService::class)->downloadInvoice($payment);
    }
}
