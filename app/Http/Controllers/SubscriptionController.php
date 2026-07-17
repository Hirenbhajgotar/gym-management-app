<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\GymOwnerRegistrationService;
use App\Services\PriceResolver;
use App\Services\RazorpayService;
use App\Services\SubscriptionProvisioningService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(
        protected RazorpayService $razorpayService,
        protected GymOwnerRegistrationService $gymOwnerRegistrationService
    ) {}

    /**
     * Show subscription checkout page
     */
    public function checkout(Request $request): View
    {
        $token = $request->query('token');

        $orderData = DB::transaction(function () use ($token) {
            $session = CheckoutSession::query()
                ->where('token', $token)
                ->where('user_id', Auth::id())
                ->valid()
                ->with(['plan', 'subscription.plan'])
                ->lockForUpdate()
                ->first();

            abort_unless($session !== null, 404, 'Invalid or expired checkout session');

            // Idempotency: an order was already created for this session
            // (e.g. on first page load) — reuse it instead of minting a new
            // one on every refresh.
            if (filled($session->razorpay_order_id)) {
                return [
                    'order_id' => $session->razorpay_order_id,
                    'amount' => $session->razorpay_order_amount,
                    'session' => $session,
                ];
            }

            /** @var Plan $plan */
            $plan = $session->plan;

            $paymentType = match ($session->type) {
                CheckoutSession::TYPE_UPGRADE => 'subscription_upgrade',
                CheckoutSession::TYPE_RENEWAL => 'subscription_renewal',
                default => 'subscription_new',
            };

            $razorpayPayment = $this->razorpayService->createOrder([
                'amount' => $session->amount,
                'currency' => $session->currency->value,
                'receipt' => 'CS-' . $session->id . '-' . uniqid(),
                'user_id' => $session->user_id,
                'subscription_id' => $session->subscription_id,
                'plan_id' => $session->plan_id,
                'payment_type' => $paymentType,
                'description' => match ($session->type) {
                    CheckoutSession::TYPE_UPGRADE => "Upgrade to {$plan->name} plan",
                    CheckoutSession::TYPE_RENEWAL => "Renewal of {$plan->name} subscription",
                    default => "New {$plan->name} subscription",
                },
                'notes' => [
                    'checkout_session_token' => $session->token,
                    'user_id' => $session->user_id,
                    'plan_name' => $plan->name,
                    'billing_cycle' => $session->billing_cycle,
                ],
            ]);

            // Persist so a reload reuses this exact order instead of creating another.
            $session->update([
                'razorpay_order_id' => $razorpayPayment['order_id'],
                'razorpay_order_amount' => $razorpayPayment['amount'],
            ]);

            return [
                'order_id' => $razorpayPayment['order_id'],
                'amount' => $razorpayPayment['amount'],
                'session' => $session,
            ];
        });

        $session = $orderData['session'];
        /** @var Plan $plan */
        $plan = $session->plan;
        /** @var Subscription|null $subscription */
        $subscription = $session->subscription;
        $trialDaysRaw = $session->metadata['trial_days'] ?? 0;

        return view('subscriptions.checkout', [
            'orderId' => $orderData['order_id'],
            'amount' => $orderData['amount'],
            'key' => config('services.razorpay.key_id'),
            'subscription' => $subscription,
            'plan' => $plan,
            'checkoutType' => $session->type,
            'title' => match ($session->type) {
                CheckoutSession::TYPE_UPGRADE => 'Upgrade Plan',
                CheckoutSession::TYPE_RENEWAL => 'Renew Subscription',
                default => 'New Subscription',
            },
            'trialDays' => is_numeric($trialDaysRaw) ? (int) $trialDaysRaw : 0,
            'hasUsedTrial' => (bool) ($session->metadata['has_used_trial_before'] ?? false),
        ]);
    }

    /**
     * Verify payment and complete subscription action
     */
    public function verifyPayment(Request $request): RedirectResponse
    {
        $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        try {
            // Verify payment
            $verifiedPayment = $this->razorpayService->verifyPayment(
                $request->razorpay_order_id,
                $request->razorpay_payment_id,
                $request->razorpay_signature,
            );

            throw_unless($verifiedPayment, Exception::class, 'Payment verification failed');

            throw_if((int) $verifiedPayment->user_id !== Auth::id(), Exception::class, 'Unauthorized payment');

            throw_unless($verifiedPayment->isCompleted(), Exception::class, 'Payment verification failed');

            // Provision the subscription synchronously for the interactive
            // flow so the success message below is only shown once a real,
            // active subscription exists. The webhook path still uses the
            // queued event (idempotent, with the reconcile command as safety
            // net); the service here and there share the same idempotent path.
            // Only subscription payments are provisioned here — gym owner
            // registration is handled separately by the webhook.
            if (str_starts_with((string) $verifiedPayment->payment_type, 'subscription_')) {
                resolve(SubscriptionProvisioningService::class)->provision($verifiedPayment->id);
            }

            $successMessage = match ($verifiedPayment->payment_type) {
                'subscription_renewal' => 'Subscription renewed successfully!',
                'subscription_upgrade' => 'Subscription upgraded successfully!',
                'subscription_new' => 'Subscription created successfully!',
                default => 'Payment processed successfully!',
            };

            return to_route('profile')
                ->with('success', $successMessage);
        } catch (Exception $e) {
            Log::error('Subscription payment verification failed', [
                'error' => $e->getMessage(),
                'order_id' => $request->razorpay_order_id,
            ]);

            return to_route('profile')
                ->with('error', 'Payment failed. Please try again.');
        }
    }

    /**
     * Complete a pending renewal payment
     */
    public function completePendingPayment(string $orderId): View|RedirectResponse
    {
        // Find the payment record
        $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();

        abort_if(! $payment || $payment->user_id !== auth()->id(), 404, 'Payment not found');

        // Find the subscription
        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()->find($payment->metadata['subscription_id'] ?? null);

        abort_if(! $subscription || $subscription->user_id !== auth()->id(), 404, 'Subscription not found');

        // Check if payment is still pending
        if ($payment->status !== PaymentStatus::PENDING) {
            return to_route('profile')
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
    public function handleGymOwnerCheckout(): View|RedirectResponse
    {
        $registrationData = session('gym_owner_registration');

        if (! $registrationData || ! isset($registrationData['selected_plan_id'])) {
            return to_route('gym.register')
                ->with('error', 'Registration data not found. Please start again.');
        }

        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($registrationData['selected_plan_id']);

        $priceResolver = resolve(PriceResolver::class)->resolve($plan, 'yearly');
        try {
            // Create Razorpay order with complete registration data in metadata
            $orderData = $this->razorpayService->createOrder([
                'amount' => $priceResolver['amount'],
                'currency' => $priceResolver['currency']->value,
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
        } catch (Exception $e) {
            Log::error('Razorpay order creation failed on gym owner checkout', [
                'error' => $e->getMessage(),
            ]);

            return to_route('gym.register')
                ->with('error', 'The payment service is temporarily unavailable. Please try again.');
        }

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
    public function verifyGymOwnerPayment(Request $request): RedirectResponse
    {
        $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        try {
            // Get registration data from session
            $registrationData = session('gym_owner_registration');

            throw_unless($registrationData, Exception::class, 'Registration data not found');

            // Get payment record
            $payment = Payment::query()->where('razorpay_order_id', $request->razorpay_order_id)->first();

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

            return to_route('thank-you')
                ->with('success', $successMessage)
                ->with('email', $result['user']->email);
        } catch (Exception $e) {
            Log::error('Gym owner payment verification failed', [
                'error' => $e->getMessage(),
                'order_id' => $request->razorpay_order_id ?? null,
            ]);

            return to_route('gym.register')
                ->with('error', 'Payment verification failed. Please try again or contact support.');
        }
    }
}
