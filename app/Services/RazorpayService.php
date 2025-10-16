<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class RazorpayService
{
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret')
        );
    }

    /**
     * Create a Razorpay order
     */
    public function createOrder(array $data): array
    {
        try {
            // Amount should be in paisa (multiply by 100)
            $amount = (int) ($data['amount'] * 100);

            $orderData = [
                'receipt' => $data['receipt'] ?? uniqid(),
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'INR',
                'payment_capture' => 1, // Auto capture
            ];

            if (isset($data['notes'])) {
                $orderData['notes'] = $data['notes'];
            }

            $razorpayOrder = $this->api->order->create($orderData);

            // Create payment record
            $paymentData = [
                'razorpay_order_id' => $razorpayOrder->id,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'INR',
                'status' => 'pending',
                'payment_type' => $data['payment_type'] ?? 'whatsapp_package',
                'metadata' => $data['notes'] ?? [],
            ];

            // Handle different payment types
            if ($data['payment_type'] === 'subscription_renewal' || $data['payment_type'] === 'subscription_upgrade' || $data['payment_type'] === 'subscription_new') {
                $paymentData['user_id'] = $data['user_id'];
                $paymentData['metadata'] = array_merge($paymentData['metadata'], [
                    'subscription_id' => $data['subscription_id'] ?? null,
                    'plan_id' => $data['plan_id'] ?? null,
                    'description' => $data['description'] ?? null,
                ]);
            } else {
                // WhatsApp package payments
                $paymentData['gym_id'] = $data['gym_id'];
                $paymentData['metadata'] = array_merge($paymentData['metadata'], [
                    'package_id' => $data['package_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'package_name' => $data['notes']['package_name'] ?? null,
                    'message_count' => $data['notes']['message_count'] ?? null,
                ]);
            }

            $payment = Payment::create($paymentData);

            Log::info('Razorpay order created', [
                'order_id' => $razorpayOrder->id,
                'payment_id' => $payment->id,
                'amount' => $data['amount'],
            ]);

            return [
                'order_id' => $razorpayOrder->id,
                'amount' => $razorpayOrder->amount,
                'currency' => $razorpayOrder->currency,
                'key' => config('services.razorpay.key_id'),
                'payment_id' => $payment->id,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Razorpay order', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Verify payment signature
     */
    public function verifyPayment(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool
    {
        try {
            $attributes = [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $razorpaySignature,
            ];

            $this->api->utility->verifyPaymentSignature($attributes);

            // Update payment record
            $payment = Payment::where('razorpay_order_id', $razorpayOrderId)->first();
            if ($payment) {
                $payment->update([
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);

                Log::info('Payment verified successfully', [
                    'payment_id' => $payment->id,
                    'razorpay_payment_id' => $razorpayPaymentId,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'error' => $e->getMessage(),
                'order_id' => $razorpayOrderId,
                'payment_id' => $razorpayPaymentId,
            ]);

            // Update payment status to failed
            Payment::where('razorpay_order_id', $razorpayOrderId)->update([
                'status' => 'failed',
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);

            return false;
        }
    }

    /**
     * Handle Razorpay webhook
     */
    public function handleWebhook(array $payload): void
    {
        $event = $payload['event'] ?? null;
        $paymentEntity = $payload['payment']['entity'] ?? null;

        if (!$event || !$paymentEntity) {
            Log::warning('Invalid webhook payload', ['payload' => $payload]);
            return;
        }

        $razorpayPaymentId = $paymentEntity['id'];
        $razorpayOrderId = $paymentEntity['order_id'];
        $status = $paymentEntity['status'];

        Log::info('Razorpay webhook received', [
            'event' => $event,
            'payment_id' => $razorpayPaymentId,
            'order_id' => $razorpayOrderId,
            'status' => $status,
        ]);

        $payment = Payment::where('razorpay_order_id', $razorpayOrderId)->first();

        if (!$payment) {
            Log::warning('Payment not found for webhook', [
                'order_id' => $razorpayOrderId,
                'payment_id' => $razorpayPaymentId,
            ]);
            return;
        }

        switch ($event) {
            case 'payment.captured':
                $payment->update([
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);
                break;

            case 'payment.failed':
                $payment->update([
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'status' => 'failed',
                ]);
                break;

            default:
                Log::info('Unhandled webhook event', ['event' => $event]);
                break;
        }
    }

    /**
     * Get payment details
     */
    public function getPayment(string $paymentId): ?array
    {
        try {
            $razorpayPayment = $this->api->payment->fetch($paymentId);

            return [
                'id' => $razorpayPayment->id,
                'amount' => $razorpayPayment->amount / 100, // Convert from paisa
                'currency' => $razorpayPayment->currency,
                'status' => $razorpayPayment->status,
                'method' => $razorpayPayment->method,
                'order_id' => $razorpayPayment->order_id,
                'captured' => $razorpayPayment->captured,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment details', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $paymentId, ?float $amount = null): ?array
    {
        try {
            $refundData = [];
            if ($amount) {
                $refundData['amount'] = (int) ($amount * 100);
            }

            $refund = $this->api->payment->fetch($paymentId)->refund($refundData);

            return [
                'id' => $refund->id,
                'payment_id' => $refund->payment_id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process refund', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
