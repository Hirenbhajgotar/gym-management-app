<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController
{
    public function __construct(
        protected RazorpayService $razorpayService
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

        $payment = Payment::where('razorpay_order_id', $orderId)->first();

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

        $payment = Payment::where('razorpay_order_id', $orderId)->first();

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
            $existingUser = \App\Models\User::where('email', $email)->first();

            if ($existingUser) {
                Log::info('User already exists, skipping registration', [
                    'email' => $email,
                    'payment_id' => $payment->id,
                ]);

                return;
            }

            // Use the shared method from SubscriptionController
            app(\App\Http\Controllers\SubscriptionController::class)
                ->createGymOwnerFromRegistrationData($registrationData, $payment);

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
}
