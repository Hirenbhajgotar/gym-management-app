<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Order;
use Razorpay\Api\Utility;

class RazorpayService
{
    /**
     * @var Api&object{order: Order, payment: \Razorpay\Api\Payment, utility: Utility}
     */
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(
            config()->string('services.razorpay.key_id'),
            config()->string('services.razorpay.key_secret')
        );
    }

    /**
     * Create a Razorpay order
     *
     * @param array{
     *     amount: float|int|string|null,
     *     currency?: string,
     *     receipt?: string,
     *     user_id?: int|null,
     *     subscription_id?: int|null,
     *     plan_id?: int|null,
     *     payment_type: string|null,
     *     description?: string,
     *     notes?: array,
     *     gym_id?: int|null,
     *     package_id?: int|null
     * } $data
     * @return array{order_id: string, amount: int, currency: string, key: string, payment_id: int}
     */
    public function createOrder(array $data): array
    {
        try {
            // Amount should be in paisa (multiply by 100). Round to avoid
            // floating-point truncation (e.g. 999.99 * 100 = 99998.999...).
            $amount = $this->toPaisa((float) $data['amount']);

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

            // The Razorpay SDK is untyped, so its response is mixed. Pin the
            // scalar fields we return to their concrete types.
            /** @var string $orderId */
            $orderId = $razorpayOrder->id;
            /** @var int $orderAmount */
            $orderAmount = $razorpayOrder->amount;
            /** @var string $orderCurrency */
            $orderCurrency = $razorpayOrder->currency;

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
            if (in_array($data['payment_type'], ['subscription_renewal', 'subscription_upgrade', 'subscription_new', 'gym_owner_registration'], true)) {
                $paymentData['user_id'] = $data['user_id'] ?? null;
                $paymentData['metadata'] = array_merge($paymentData['metadata'], [
                    'subscription_id' => $data['subscription_id'] ?? null,
                    'plan_id' => $data['plan_id'] ?? null,
                    'description' => $data['description'] ?? null,
                ]);
            } else {
                // WhatsApp package payments
                $paymentData['gym_id'] = $data['gym_id'] ?? null;
                $paymentData['user_id'] = $data['user_id'] ?? null;
                $paymentData['metadata'] = array_merge($paymentData['metadata'], [
                    'package_id' => $data['package_id'] ?? $data['notes']['package_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'package_name' => $data['notes']['package_name'] ?? null,
                    'message_count' => $data['notes']['message_count'] ?? null,
                ]);
            }

            $payment = Payment::query()->create($paymentData);

            Log::info('Razorpay order created', [
                'order_id' => $razorpayOrder->id,
                'payment_id' => $payment->id,
                'amount' => $data['amount'],
            ]);

            return [
                'order_id' => $orderId,
                'amount' => $orderAmount,
                'currency' => $orderCurrency,
                'key' => config()->string('services.razorpay.key_id'),
                'payment_id' => $payment->id,
            ];
        } catch (Exception $e) {
            Log::error('Failed to create Razorpay order', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Convert a currency amount to paisa, rounding to the nearest integer to
     * avoid floating-point truncation (e.g. 999.99 * 100 = 99998.999...).
     */
    protected function toPaisa(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Verify payment signature
     */
    public function verifyPayment(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): ?Payment
    {
        try {
            $attributes = [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $razorpaySignature,
            ];

            $this->api->utility->verifyPaymentSignature($attributes);

            // Update payment record
            $payment = Payment::query()->where('razorpay_order_id', $razorpayOrderId)->first();
            if (! $payment) {
                return null;
            }

            $payment->update([
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $razorpaySignature,
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            Log::info('Payment verified successfully', [
                'payment_id' => $payment->id,
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);

            return $payment->fresh();
        } catch (Exception $e) {
            Log::error('Payment verification failed', [
                'error' => $e->getMessage(),
                'order_id' => $razorpayOrderId,
                'payment_id' => $razorpayPaymentId,
            ]);

            // Update payment status to failed
            $payment = Payment::query()->where('razorpay_order_id', $razorpayOrderId)->first();
            if (! $payment) {
                return null;
            }
            $payment->update([
                'status' => 'failed',
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $razorpaySignature,
            ]);

            return $payment->fresh();
        }
    }

    /**
     * Handle Razorpay webhook
     */
    public function handleWebhook(array $payload): void
    {
        $event = $payload['event'] ?? null;
        $paymentEntity = $payload['payment']['entity'] ?? null;

        if (! $event || ! $paymentEntity) {
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

        $payment = Payment::query()->where('razorpay_order_id', $razorpayOrderId)->first();

        if (! $payment) {
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            Log::error('Failed to process refund', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verify Razorpay webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        try {
            $secret = config('services.razorpay.webhook_secret');
            if (blank($secret)) {
                Log::error('Razorpay webhook secret not configured');

                return false;
            }

            $expectedSignature = hash_hmac('sha256', $payload, $secret);

            return hash_equals($expectedSignature, $signature);
        } catch (Exception $e) {
            Log::error('Webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify payment directly with Razorpay API using Order ID
     * This is useful when signature is not available or for double verification
     */
    public function verifyPaymentByOrderId(string $orderId): array
    {
        try {
            // Fetch order and its payments
            $order = $this->api->order->fetch($orderId);
            $payments = $order->payments(); // Helper method on the resource

            if ($payments->count() === 0) {
                return ['verified' => false, 'reason' => 'no_payments_found'];
            }

            // Check if any payment is captured/authorized
            $successfulPayment = null;
            foreach ($payments->items as $payment) {
                if ($payment->status === 'captured' || $payment->status === 'authorized') {
                    $successfulPayment = $payment;
                    break;
                }
            }

            if (! $successfulPayment) {
                return ['verified' => false, 'reason' => 'no_successful_payment'];
            }

            // Update local payment record if verified
            $localPayment = Payment::query()->where('razorpay_order_id', $orderId)->first();
            if ($localPayment) {
                // Only update if not already completed to avoid overwriting newer data
                if (! $localPayment->isCompleted()) {
                    $localPayment->update([
                        'razorpay_payment_id' => $successfulPayment->id,
                        'status' => 'completed',
                        'paid_at' => now(),
                        // We don't have signature here, but we verified via API
                    ]);
                }

                return [
                    'verified' => true,
                    'payment_id' => $successfulPayment->id,
                    'status' => $successfulPayment->status,
                    'amount' => $successfulPayment->amount / 100,
                ];
            }

            return ['verified' => false, 'reason' => 'local_record_not_found'];
        } catch (Exception $e) {
            Log::error('API-based payment verification failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return ['verified' => false, 'reason' => 'api_error', 'message' => $e->getMessage()];
        }
    }
}
