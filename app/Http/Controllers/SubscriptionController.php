<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\GymOwnerRegistrationService;
use App\Services\InvoiceService;
use App\Services\RazorpayService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(
        protected RazorpayService $razorpayService,
        protected SubscriptionService $subscriptionService,
        protected GymOwnerRegistrationService $gymOwnerRegistrationService,
        protected InvoiceService $invoiceService
    ) {}

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

        // For upgrades, charge the full amount of the new plan
        $upgradeAmount = $newPlan->price_yearly;

        $orderData = $this->razorpayService->createOrder([
            'amount' => $upgradeAmount,
            'currency' => $subscription->currency,
            'receipt' => 'SUB-UPG-' . $subscription->id . '-' . time(),
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $newPlan->id,
            'payment_type' => 'subscription_upgrade',
            'description' => "Upgrade to {$newPlan->name} plan",
            'notes' => [
                'subscription_id' => $subscription->id,
                'from_plan' => $subscription?->plan?->name,
                'to_plan' => $newPlan?->name,
                'plan_name' => $newPlan?->name,
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

        // Check if user has already used a trial
        $hasUsedTrial = $user->hasUsedTrial();
        $trialDays =
            ! $hasUsedTrial && $plan->trial_days ? $plan->trial_days : 0;
        $amount = $trialDays > 0 ? 0 : $plan->price_yearly;

        $orderData = $this->razorpayService->createOrder([
            'amount' => $amount,
            'currency' => $plan->currency,
            'receipt' => 'SUB-NEW-' . $user->id . '-' . time(),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'payment_type' => 'subscription_new',
            'description' => "New {$plan->name} subscription" .
                ($trialDays > 0 ? ' (with trial)' : ''),
            'notes' => [
                'user_id' => $user->id,
                'plan_name' => $plan->name,
                'trial_days' => $trialDays,
                'has_used_trial_before' => $hasUsedTrial,
            ],
        ]);

        return view('subscriptions.checkout', [
            'orderId' => $orderData['order_id'],
            'amount' => $orderData['amount'],
            'key' => config('services.razorpay.key_id'),
            'plan' => $plan,
            'checkoutType' => 'new',
            'trialDays' => $trialDays,
            'hasUsedTrial' => $hasUsedTrial,
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
                $request->razorpay_signature,
            );

            if (! $isVerified) {
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
                default => 'Payment processed successfully!',
            };

            // Generate invoice
            $payment = Payment::where('razorpay_order_id', $request->razorpay_order_id)->first();
            if ($payment?->isCompleted()) {
                try {
                    $this->invoiceService->generateInvoice($payment);
                } catch (\Exception $e) {
                    Log::error('Failed to generate invoice in verifyPayment', ['error' => $e->getMessage()]);
                }
            }

            return redirect()
                ->route('profile')
                ->with('success', $successMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription payment verification failed', [
                'error' => $e->getMessage(),
                'order_id' => $request->razorpay_order_id,
                'checkout_type' => $this->determineCheckoutType(),
            ]);

            return redirect()
                ->route('profile')
                ->with('error', 'Payment failed. Please try again.');
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
        $user = $subscription?->user;

        // Cancel the current subscription immediately
        $this->subscriptionService->cancelSubscription($subscription, true);

        // Create new subscription with the upgraded plan (no trial since user already used trial)
        $this->subscriptionService->createSubscription($user, $newPlan, [
            'billing_cycle' => $subscription->billing_cycle,
            'trial_days' => 0, // Explicitly prevent trial usage for upgrades
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

    /**
     * Complete a pending renewal payment
     */
    public function completePendingPayment(
        string $orderId,
    ): View|\Illuminate\Http\RedirectResponse {
        // Find the payment record
        $payment = Payment::where('razorpay_order_id', $orderId)->first();

        if (! $payment || $payment->user_id !== auth()->id()) {
            abort(404, 'Payment not found');
        }

        // Find the subscription
        $subscription = Subscription::find(
            $payment->metadata['subscription_id'] ?? null,
        );

        if (! $subscription || $subscription->user_id !== auth()->id()) {
            abort(404, 'Subscription not found');
        }

        // Check if payment is still pending
        if ($payment->status !== 'pending') {
            return redirect()
                ->route('profile')
                ->with('info', 'This payment has already been processed.');
        }

        return view('subscriptions.checkout', [
            'orderId' => $orderId,
            'amount' => $payment->amount,
            'key' => config('services.razorpay.key_id'),
            'subscription' => $subscription,
            'plan' => $subscription->plan,
            'checkoutType' => 'renewal',
            'title' => 'Complete Subscription Renewal',
            'isPendingRenewal' => true,
        ]);
    }

    /**
     * Handle gym owner registration checkout
     */
    public function handleGymOwnerCheckout(): View|\Illuminate\Http\RedirectResponse
    {
        $registrationData = session('gym_owner_registration');

        if (! $registrationData || ! isset($registrationData['selected_plan_id'])) {
            return redirect()
                ->route('gym.register')
                ->with('error', 'Registration data not found. Please start again.');
        }

        $plan = Plan::findOrFail($registrationData['selected_plan_id']);

        // Create Razorpay order with complete registration data in metadata
        $orderData = $this->razorpayService->createOrder([
            'amount' => $plan->price_yearly,
            'currency' => $plan->currency,
            'receipt' => 'GYM-REG-' . uniqid(),
            'payment_type' => 'gym_owner_registration',
            'description' => "Gym Owner Registration - {$plan->name}",
            'notes' => [
                'plan_name' => $plan->name,
                'user_name' => $registrationData['name'],
                'owner_email' => $registrationData['email'],
            ],
            // Store complete registration data for webhook to access
            'metadata' => [
                'registration_data' => $registrationData,
            ],
        ]);

        return view('subscriptions.checkout', [
            'orderId' => $orderData['order_id'],
            'amount' => $orderData['amount'],
            'key' => config('services.razorpay.key_id'),
            'plan' => $plan,
            'checkoutType' => 'gym_owner_registration',
            'title' => 'Complete Your Gym Registration',
            'gymName' => $registrationData['gym_name'],
            'user_name' => $registrationData['name'],
        ]);
    }

    /**
     * Verify gym owner registration payment and complete setup
     */
    public function verifyGymOwnerPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        try {
            // Get registration data from session
            $registrationData = session('gym_owner_registration');

            if (! $registrationData) {
                throw new \Exception('Registration data not found');
            }

            // Get payment record
            $payment = Payment::where('razorpay_order_id', $request->razorpay_order_id)->first();

            // Use the reusable method to create gym owner
            $result = $this->gymOwnerRegistrationService->createFromRegistrationData($registrationData, $payment);

            // Clear session data
            session()->forget('gym_owner_registration');

            // Success message based on subscription status
            if ($result['already_exists']) {
                $successMessage = 'Your account already exists. Please log in.';
            } else {
                $subscriptionStatus = $result['subscription_status'] ?? 'pending';
                $successMessage = $subscriptionStatus === 'active'
                    ? 'Registration completed successfully! Your subscription is now active.'
                    : 'Registration completed! Please complete the payment to activate your subscription.';
            }

            return redirect()
                ->route('thank-you')
                ->with('success', $successMessage);
        } catch (\Exception $e) {
            Log::error('Gym owner payment verification failed', [
                'error' => $e->getMessage(),
                'order_id' => $request->razorpay_order_id ?? null,
            ]);

            return redirect()
                ->route('gym.register')
                ->with('error', 'Payment verification failed. Please try again or contact support.');
        }
    }

    /**
     * Create gym owner, gym, and subscription from registration data
     * Delegates to GymOwnerRegistrationService for business logic
     *
     * @deprecated This method delegates to GymOwnerRegistrationService. Use the service directly instead.
     */
    public function createGymOwnerFromRegistrationData(array $registrationData, ?Payment $payment = null): array
    {
        return $this->gymOwnerRegistrationService->createFromRegistrationData($registrationData, $payment);
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
