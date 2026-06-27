<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\User;
use App\Models\WhatsAppCreditTransaction;
use App\Services\GymOwnerRegistrationService;
use App\Services\RazorpayService;
use App\Services\WhatsAppBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController
{
    public function __construct(
        protected RazorpayService $razorpayService,
        protected GymOwnerRegistrationService $gymOwnerRegistrationService,
        protected WhatsAppBillingService $whatsAppBillingService
    ) {}

    /**
     * Handle Razorpay payment webhook
     */
    public function handlePaymentWebhook(Request $request): JsonResponse
    {
        try {
            // Get raw payload and signature
            $payload = $request->getContent();
            $signature = $request->header('X-Razorpay-Signature');

            // Verify webhook signature
            if (! $signature || ! $this->razorpayService->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Invalid Razorpay webhook signature', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $data = json_decode($payload, true);
            $event = $data['event'] ?? null;
            $paymentEntity = $data['payload']['payment']['entity'] ?? null;

            if (! $event || ! $paymentEntity) {
                Log::warning('Invalid webhook payload structure', [
                    'payload' => $data,
                ]);

                return response()->json(['error' => 'Invalid payload'], 400);
            }

            Log::info('Razorpay webhook received', [
                'event' => $event,
                'payment_id' => $paymentEntity['id'] ?? null,
                'order_id' => $paymentEntity['order_id'] ?? null,
            ]);

            // Handle different events
            if ($event === 'payment.captured') {
                $this->handlePaymentCaptured($paymentEntity);
            } elseif ($event === 'payment.failed') {
                $this->handlePaymentFailed($paymentEntity);
            }

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent Razorpay from retrying
            // (we've logged the error for manual review)
            return response()->json(['status' => 'error'], 200);
        }
    }

    /**
     * Handle payment.captured event
     */
    protected function handlePaymentCaptured(array $paymentEntity): void
    {
        $orderId = $paymentEntity['order_id'] ?? null;
        $paymentId = $paymentEntity['id'] ?? null;

        if (! $orderId || ! $paymentId) {
            Log::warning('Missing order_id or payment_id in payment.captured event');

            return;
        }

        $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();

        if (! $payment) {
            Log::warning('Payment not found for webhook', [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
            ]);

            return;
        }

        // Update payment status if not already completed
        if ($payment->status !== 'completed') {
            $payment->update([
                'razorpay_payment_id' => $paymentId,
                'status' => 'completed',
                'paid_at' => now(),
            ]);
        }

        // Handle gym owner registration
        if ($payment->payment_type === 'gym_owner_registration') {
            $this->processGymOwnerRegistration($payment);
        }

        // Handle WhatsApp package purchases
        if ($payment->payment_type === 'whatsapp_package') {
            $this->processWhatsAppPackagePayment($payment);
        }
    }

    /**
     * Handle payment.failed event
     */
    protected function handlePaymentFailed(array $paymentEntity): void
    {
        $orderId = $paymentEntity['order_id'] ?? null;
        $paymentId = $paymentEntity['id'] ?? null;

        if (! $orderId) {
            return;
        }

        $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();

        if ($payment && $payment->status !== 'failed') {
            $payment->update([
                'razorpay_payment_id' => $paymentId,
                'status' => 'failed',
            ]);
        }
    }

    /**
     * Process gym owner registration from webhook
     */
    protected function processGymOwnerRegistration(Payment $payment): void
    {
        try {
            $metadata = $payment->metadata;

            // Get registration data from payment metadata (stored when order was created)
            $registrationData = $metadata['registration_data'] ?? null;

            if (! $registrationData) {
                Log::error('Missing registration_data in payment metadata', [
                    'payment_id' => $payment->id,
                    'metadata' => $metadata,
                ]);

                return;
            }

            // Get email from registration data
            $email = $registrationData['email'] ?? null;

            if (! $email) {
                Log::error('Missing email in registration data', [
                    'payment_id' => $payment->id,
                ]);

                return;
            }

            // Check if user already exists (idempotency)
            $existingUser = User::query()->where('email', $email)->first();

            if ($existingUser) {
                Log::info('User already exists, skipping registration', [
                    'email' => $email,
                    'payment_id' => $payment->id,
                ]);

                return;
            }

            // Use the service to create gym owner
            $this->gymOwnerRegistrationService->createFromRegistrationData($registrationData, $payment);

            Log::info('Gym owner created successfully via webhook', [
                'email' => $email,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process gym owner registration via webhook', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process WhatsApp package purchase from webhook
     */
    protected function processWhatsAppPackagePayment(Payment $payment): void
    {
        try {
            $metadata = $payment->metadata ?? [];

            $packageId = $metadata['package_id'] ?? null;
            $gymId = $payment->gym_id;

            if (! $packageId || ! $gymId) {
                Log::error('Missing package_id or gym_id in WhatsApp package payment', [
                    'payment_id' => $payment->id,
                    'package_id' => $packageId,
                    'gym_id' => $gymId,
                    'metadata' => $metadata,
                ]);

                return;
            }

            // Check if already processed (idempotency)
            $existingTransaction = WhatsAppCreditTransaction::query()->where('payment_reference', $payment->razorpay_payment_id)
                ->where('type', 'purchase')
                ->first();

            if ($existingTransaction) {
                Log::info('WhatsApp package already processed, skipping', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $existingTransaction->id,
                ]);

                return;
            }

            // Process the package purchase via WhatsAppBillingService
            $result = $this->whatsAppBillingService->purchasePackage(
                $gymId,
                $packageId,
                $payment->razorpay_payment_id,
                $payment->user_id
            );

            if ($result['success']) {
                Log::info('WhatsApp package purchased via webhook', [
                    'payment_id' => $payment->id,
                    'gym_id' => $gymId,
                    'package_id' => $packageId,
                    'package_name' => $result['package_name'] ?? null,
                    'message_count' => $result['message_count'] ?? null,
                    'total_credits' => $result['total_credits'] ?? null,
                    'remaining_credits' => $result['remaining_credits'] ?? null,
                ]);
            } else {
                Log::error('Failed to process WhatsApp package purchase', [
                    'payment_id' => $payment->id,
                    'gym_id' => $gymId,
                    'package_id' => $packageId,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process WhatsApp package payment via webhook', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
