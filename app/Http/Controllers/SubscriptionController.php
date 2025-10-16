<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\RazorpayService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    protected RazorpayService $razorpayService;
    protected SubscriptionService $subscriptionService;

    public function __construct(RazorpayService $razorpayService, SubscriptionService $subscriptionService)
    {
        $this->razorpayService = $razorpayService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Show subscription checkout page
     */
    public function checkout(Request $request): View
    {
        // Handle different checkout types
        $checkoutType = $this->determineCheckoutType();

        if ($checkoutType === 'renewal') {
            return $this->handleRenewalCheckout();
        } elseif ($checkoutType === 'upgrade') {
            return $this->handleUpgradeCheckout();
        } elseif ($checkoutType === 'new') {
            return $this->handleNewSubscriptionCheckout();
        }

        abort(400, 'Invalid checkout type');
    }

    private function determineCheckoutType(): string
    {
        if (session()->has('renew_subscription_id')) {
            return 'renewal';
        } elseif (session()->has('upgrade_plan_id')) {
            return 'upgrade';
        } elseif (session()->has('selected_plan_id')) {
            return 'new';
        }

        return 'unknown';
    }

    private function handleRenewalCheckout(): View
    {
        $subscriptionId = session('renew_subscription_id');
        $planId = session('renew_plan_id');

        $subscription = Subscription::with('plan')->findOrFail($subscriptionId);
        $plan = Plan::findOrFail($planId);

        // Create Razorpay order for renewal
        $orderData = $this->razorpayService->createOrder([
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'receipt' => 'SUB-REN-' . $subscription->id . '-' . uniqid(),
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'payment_type' => 'subscription_renewal',
            'description' => "Renewal of {$plan->name} subscription",
            'notes' => [
                'subscription_id' => $subscription->id,
                'plan_name' => $plan->name,
                'billing_cycle' => $subscription->billing_cycle,
            ],
        ]);

        return view('subscriptions.checkout', [
            'orderId' => $orderData['order_id'],
            'amount' => $orderData['amount'],
            'key' => config('services.razorpay.key_id'),
            'subscription' => $subscription,
            'plan' => $plan,
            'checkoutType' => 'renewal',
            'title' => 'Renew Subscription',
        ]);
    }

    private function handleUpgradeCheckout(): View
    {
        $planId = session('upgrade_plan_id');
        $subscriptionId = session('current_subscription_id');

        $subscription = Subscription::with('plan')->findOrFail($subscriptionId);
        $newPlan = Plan::findOrFail($planId);

        // Calculate prorated amount if needed
        $proratedAmount = $this->calculateProratedAmount($subscription, $newPlan);

        $orderData = $this->razorpayService->createOrder([
            'amount' => $proratedAmount,
            'currency' => $subscription->currency,
            'receipt' => 'SUB-UPG-' . $subscription->id . '-' . time(),
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $newPlan->id,
            'payment_type' => 'subscription_upgrade',
            'description' => "Upgrade to {$newPlan->name} plan",
            'notes' => [
                'subscription_id' => $subscription->id,
                'from_plan' => $subscription->plan->name,
                'to_plan' => $newPlan->name,
            ],
        ]);

        return view('subscriptions.checkout', [
            'orderId' => $orderData['order_id'],
            'amount' => $orderData['amount'],
            'key' => config('services.razorpay.key_id'),
            'subscription' => $subscription,
            'plan' => $newPlan,
            'checkoutType' => 'upgrade',
            'title' => 'Upgrade Plan',
        ]);
    }

    private function handleNewSubscriptionCheckout(): View
    {
        $planId = session('selected_plan_id');
        $plan = Plan::findOrFail($planId);
        $user = auth()->user();

        $trialDays = $plan->trial_days ?? 0;
        $amount = $trialDays > 0 ? 0 : $plan->price_yearly;

        $orderData = $this->razorpayService->createOrder([
            'amount' => $amount,
            'currency' => $plan->currency,
            'receipt' => 'SUB-NEW-' . $user->id . '-' . time(),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'payment_type' => 'subscription_new',
            'description' => "New {$plan->name} subscription" . ($trialDays > 0 ? ' (with trial)' : ''),
            'notes' => [
                'user_id' => $user->id,
                'plan_name' => $plan->name,
                'trial_days' => $trialDays,
            ],
        ]);

        return view('subscriptions.checkout', [
            'orderId' => $orderData['order_id'],
            'amount' => $orderData['amount'],
            'key' => config('services.razorpay.key_id'),
            'plan' => $plan,
            'checkoutType' => 'new',
            'trialDays' => $trialDays,
            'title' => 'New Subscription',
        ]);
    }

    /**
     * Verify payment and complete subscription action
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // Verify payment
            $isVerified = $this->razorpayService->verifyPayment(
                $request->razorpay_order_id,
                $request->razorpay_payment_id,
                $request->razorpay_signature
            );

            if (!$isVerified) {
                throw new \Exception('Payment verification failed');
            }

            $checkoutType = $this->determineCheckoutType();

            if ($checkoutType === 'renewal') {
                $this->processRenewal();
            } elseif ($checkoutType === 'upgrade') {
                $this->processUpgrade();
            } elseif ($checkoutType === 'new') {
                $this->processNewSubscription();
            }

            DB::commit();

            // Clear session data
            $this->clearSessionData();

            $successMessage = match ($checkoutType) {
                'renewal' => 'Subscription renewed successfully!',
                'upgrade' => 'Subscription upgraded successfully!',
                'new' => 'Subscription created successfully!',
                default => 'Payment processed successfully!'
            };

            return redirect()->route('profile')->with('success', $successMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription payment verification failed', [
                'error' => $e->getMessage(),
                'order_id' => $request->razorpay_order_id,
                'checkout_type' => $this->determineCheckoutType(),
            ]);

            return redirect()->route('profile')->with('error', 'Payment failed. Please try again.');
        }
    }

    private function processRenewal(): void
    {
        $subscriptionId = session('renew_subscription_id');
        $subscription = Subscription::findOrFail($subscriptionId);

        $this->subscriptionService->renewSubscription($subscription);
    }

    private function processUpgrade(): void
    {
        $planId = session('upgrade_plan_id');
        $subscriptionId = session('current_subscription_id');

        $subscription = Subscription::findOrFail($subscriptionId);
        $newPlan = Plan::findOrFail($planId);

        $this->subscriptionService->changePlan($subscription, $newPlan, [
            'billing_cycle' => $subscription->billing_cycle,
            'prorate' => true,
        ]);
    }

    private function processNewSubscription(): void
    {
        $planId = session('selected_plan_id');
        $plan = Plan::findOrFail($planId);
        $user = auth()->user();

        $this->subscriptionService->createSubscription($user, $plan, [
            'billing_cycle' => 'yearly',
        ]);
    }

    private function calculateProratedAmount(Subscription $subscription, Plan $newPlan): float
    {
        // Simple prorated calculation - you might want to make this more sophisticated
        $daysLeft = $subscription->daysUntilExpiry();
        $totalDays = $subscription->billing_cycle === 'yearly' ? 365 : 30;

        if ($daysLeft <= 0) {
            return $newPlan->price_yearly;
        }

        $currentPlanDailyRate = $subscription->price / $totalDays;
        $newPlanDailyRate = $newPlan->price_yearly / 365; // Assuming yearly billing

        $credit = $currentPlanDailyRate * $daysLeft;
        $newAmount = $newPlanDailyRate * $totalDays;

        return max(0, $newAmount - $credit);
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
}
