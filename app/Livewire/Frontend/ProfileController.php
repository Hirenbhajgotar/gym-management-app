<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Enums\RecurringFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\CheckoutSession;
use App\Models\Gym;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property-read User $user
 * @property-read Subscription|null $subscription
 * @property-read Plan|null $currentPlan
 * @property-read int $totalGymsCount
 * @property-read int $totalMembersCount
 * @property-read float $totalSpentAmount
 * @property-read bool $hasUsedTrial
 * @property-read Collection<int, Plan> $plans
 * @property-read Collection<int, Payment> $latestPayments
 */
#[Title('My Account & Subscriptions | FluxFitness')]
#[Layout('frontend.layouts.app_new', [
    'seo' => [
        'description' => 'Manage your FluxFitness gym owner account, active subscriptions, gym locations, payment history, and billing plans.',
        'keywords' => 'gym management account, fluxfitness profile, gym subscription, billing invoices',
    ],
])]
class ProfileController extends Component
{
    public bool $renewingPlan = false;

    public bool $selectingPlan = false;

    public bool $upgradingPlan = false;

    public bool $loggingOut = false;

    public string $name = '';

    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $updatingProfile = false;

    public bool $updatingPassword = false;

    protected PriceResolver $priceResolver;

    protected SubscriptionService $subscriptionService;

    protected InvoiceService $invoiceService;

    public function __construct(
        ?PriceResolver $priceResolver = null,
        ?SubscriptionService $subscriptionService = null,
        ?InvoiceService $invoiceService = null,
    ) {
        $this->priceResolver = $priceResolver ?? resolve(PriceResolver::class);
        $this->subscriptionService = $subscriptionService ?? resolve(SubscriptionService::class);
        $this->invoiceService = $invoiceService ?? resolve(InvoiceService::class);
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->name = $user->name;
        $this->phone = $user->phone ?? '';
    }

    public function updateProfile(): void
    {
        $this->resetErrorBag();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $this->updatingProfile = true;

        try {
            /** @var User $user */
            $user = Auth::user();
            $user->update([
                'name' => $this->name,
                'phone' => $this->phone !== '' ? $this->phone : null,
            ]);

            unset($this->user);

            session()->flash('success', 'Profile details updated successfully.');
            $this->dispatch('profile-saved');
        } finally {
            $this->updatingProfile = false;
        }
    }

    public function updatePassword(): void
    {
        $this->resetErrorBag();

        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->updatingPassword = true;

        try {
            /** @var User $user */
            $user = Auth::user();
            $user->update([
                'password' => Hash::make($this->password),
            ]);

            Auth::logoutOtherDevices($this->password);

            $this->reset(['current_password', 'password', 'password_confirmation']);
            session()->flash('success', 'Account password changed successfully.');
            $this->dispatch('password-saved');
        } finally {
            $this->updatingPassword = false;
        }
    }

    #[Computed(persist: true, seconds: 86400)]
    public function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->loadMissing([
            'roles',
            'activeSubscription.plan.features',
            'gyms' => function ($query): void {
                /** @var BelongsToMany<Gym, User> $query */
                $query->withCount(['members', 'users', 'biometricDevices'])->with('address.city');
            },
        ]);
    }

    #[Computed(persist: true, seconds: 86400)]
    public function subscription(): ?Subscription
    {
        return $this->user()->subscription();
    }

    #[Computed(persist: true, seconds: 86400)]
    public function currentPlan(): ?Plan
    {
        return $this->subscription()?->plan?->loadMissing('features');
    }

    #[Computed(persist: true, seconds: 86400)]
    public function totalGymsCount(): int
    {
        return $this->user()->gyms->count();
    }

    #[Computed(persist: true, seconds: 86400)]
    public function totalMembersCount(): int
    {
        return $this->user()->gyms->sum(fn (Gym $gym): int => $gym->members_count ?? 0);
    }

    #[Computed(persist: true, seconds: 86400)]
    public function hasUsedTrial(): bool
    {
        return $this->user()->hasUsedTrial();
    }

    public function render(): View
    {
        return view('livewire.frontend.profile-controller');
    }

    /**
     * @return Collection<int, Plan>
     */
    #[Computed(persist: true, seconds: 86400, key: Plan::PROFILE_ALL_PLANS_CACHE_KEY)]
    public function plans(): Collection
    {
        return Plan::with(['features'])
            ->where('is_active', true)
            ->where('is_enterprise', false)
            ->orderBy('price_monthly')
            ->get();
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Computed(persist: true, seconds: 86400)]
    public function latestPayments(): Collection
    {
        return Payment::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->take(5)
            ->get();
    }

    public function selectPlan(int $planId, string $billingCycle = 'yearly'): mixed
    {
        $this->selectingPlan = true;

        try {
            $billingCycle = in_array($billingCycle, [RecurringFrequency::MONTHLY->value, RecurringFrequency::YEARLY->value], true)
                ? $billingCycle
                : RecurringFrequency::YEARLY->value;

            $plan = Plan::with(['features'])
                ->where('is_active', true)
                ->where('is_enterprise', false)
                ->find($planId);

            if (! $plan) {
                session()->flash('error', 'The selected plan is not available for self-service subscription.');

                return null;
            }

            /** @var User $user */
            $user = Auth::user();

            // Check if user can get a free trial
            $hasUsedTrial = $user->hasUsedTrial();
            $trialDays = ! $hasUsedTrial && $plan->trial_days ? $plan->trial_days : 0;

            // If it's a free trial, create subscription directly without payment
            if ($trialDays > 0) {
                try {
                    $this->subscriptionService->createSubscription($user, $plan, [
                        'billing_cycle' => $billingCycle,
                        'trial_days' => $trialDays,
                    ]);

                    session()->flash(
                        'success',
                        "Your {$trialDays}-day free trial has started! You can start using {$plan->name} plan immediately.",
                    );

                    return to_route('profile');
                } catch (Exception $e) {
                    Log::error('Failed to start trial', [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'error' => $e->getMessage(),
                    ]);

                    session()->flash(
                        'error',
                        'Unable to start your trial at this time. Please try again or contact support.',
                    );

                    return null;
                }
            }

            // For paid plans, create a checkout session and redirect with its token.
            $price = $this->priceResolver->resolve($plan, $billingCycle);

            $session = CheckoutSession::query()->create([
                'token' => CheckoutSession::generateToken(),
                'user_id' => $user->id,
                'type' => CheckoutSession::TYPE_NEW,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'amount' => $price['amount'],
                'currency' => $price['currency'],
                'status' => CheckoutSession::STATUS_PENDING,
                'expires_at' => now()->addMinutes(30),
                'metadata' => ['trial_days' => 0, 'has_used_trial_before' => $hasUsedTrial],
            ]);

            return to_route('subscriptions.checkout', ['token' => $session->token]);
        } catch (Exception $e) {
            Log::error('Failed to select plan', [
                'user_id' => Auth::id(),
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Unable to process plan selection. Please try again or contact support.');

            return null;
        } finally {
            $this->selectingPlan = false;
        }
    }

    public function upgradePlan(int $planId): mixed
    {
        $this->upgradingPlan = true;

        try {
            /** @var User $user */
            $user = Auth::user();
            $currentSubscription = $user->subscription();

            // Check if user can upgrade (has active subscription)
            if (! $currentSubscription) {
                session()->flash(
                    'error',
                    'You need an active subscription to upgrade.',
                );

                return null;
            }

            $newPlan = Plan::query()
                ->where('is_active', true)
                ->where('is_enterprise', false)
                ->find($planId);

            if (! $newPlan) {
                session()->flash(
                    'error',
                    'The selected plan is not available for upgrades.',
                );

                return null;
            }

            $cycle = $currentSubscription->billing_cycle;
            $newPrice = $this->priceResolver->resolve($newPlan, $cycle)['amount'];

            // Charge only the prorated difference when proration is enabled (G10).
            if (config()->boolean('subscriptions.proration_enabled')) {
                $totalDays = $cycle === RecurringFrequency::YEARLY->value ? 365 : 30;
                $remaining = max(0, now()->diffInDays($currentSubscription->current_period_end, false));
                $currentPlanPrice = $this->priceResolver->resolve($currentSubscription->plan, $cycle)['amount'];
                $credit = $currentPlanPrice * $remaining / $totalDays;
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
        } catch (Exception $e) {
            Log::error('Failed to upgrade plan', [
                'user_id' => Auth::id(),
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Unable to process plan upgrade. Please try again or contact support.');

            return null;
        } finally {
            $this->upgradingPlan = false;
        }
    }

    public function renewPlan(): mixed
    {
        $this->renewingPlan = true;

        try {
            /** @var User $user */
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

                return null;
            }

            // Charge the CURRENT effective plan price, not the stale price stored on
            // the subscription at signup (fixes G1: renewal pays old price).
            $price = $this->priceResolver->resolve($subscription->plan, $subscription->billing_cycle);

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
        } catch (Exception $e) {
            Log::error('Failed to renew plan', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Unable to process plan renewal. Please try again or contact support.');

            return null;
        } finally {
            $this->renewingPlan = false;
        }
    }

    public function logout(): RedirectResponse|Redirector
    {
        $this->loggingOut = true;

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return to_route('home');
    }

    public function downloadInvoice(int $paymentId): StreamedResponse
    {
        $payment = Payment::query()->where('user_id', Auth::id())
            ->findOrFail($paymentId);

        return $this->invoiceService->downloadInvoice($payment);
    }
}
