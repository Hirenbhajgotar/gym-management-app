<?php

namespace App\Http\Controllers;

use App\Models\Gym;
use App\Models\GymWhatsAppBalance;
use App\Models\Payment;
use App\Models\WhatsAppMessagePackage;
use App\Services\RazorpayService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    protected RazorpayService $razorpayService;

    public function __construct(RazorpayService $razorpayService)
    {
        $this->razorpayService = $razorpayService;
    }

    /**
     * Create Razorpay order for WhatsApp package purchase
     */
    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => 'required|exists:whatsapp_message_packages,id',
            'gym_id' => 'required|exists:gyms,id',
        ]);

        try {
            $package = WhatsAppMessagePackage::findOrFail($request->package_id);
            $gym = Gym::findOrFail($request->gym_id);

            // Check if user has permission to purchase for this gym
            if (Filament::getTenant()?->id !== $gym->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to gym.',
                ], 403);
            }

            $orderData = $this->razorpayService->createOrder([
                'amount' => $package->price,
                'currency' => 'INR',
                'receipt' => 'WAP-' . $package->id . '-' . time(),
                'gym_id' => $gym->id,
                'package_id' => $package->id,
                'payment_type' => 'whatsapp_package',
                'description' => "Purchase of {$package->name} - {$package->message_count} messages",
                'notes' => [
                    'package_name' => $package->name,
                    'message_count' => $package->message_count,
                    'gym_name' => $gym->name,
                ],
            ]);

            return response()->json([
                'success' => true,
                'data' => $orderData,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create payment order', [
                'error' => $e->getMessage(),
                'package_id' => $request->package_id,
                'gym_id' => $request->gym_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment order. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify payment and complete purchase
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
            'tenant_id' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $isVerified = $this->razorpayService->verifyPayment(
                $request->razorpay_order_id,
                $request->razorpay_payment_id,
                $request->razorpay_signature
            );

            if (!$isVerified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed.',
                ], 400);
            }

            // Get payment record
            $payment = Payment::where('razorpay_order_id', $request->razorpay_order_id)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment record not found.',
                ], 404);
            }

            // $tenant = Gym::where('id', $request->tenant_id)->first();

            // Process the purchase (add messages to balance)
            $this->processWhatsAppPackagePurchase($payment);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified and package purchased successfully.',
                'payment_id' => $payment->razorpay_payment_id,
                // 'redirect_url' => route('filament.admin.pages.settings', ['tenant' => $tenant->slug, 'tab' => 'whatsapp']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment verification failed', [
                'error' => $e->getMessage(),
                'order_id' => $request->razorpay_order_id,
                'payment_id' => $request->razorpay_payment_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed. Please contact support.',
            ], 500);
        }
    }

    /**
     * Handle Razorpay webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            $expectedSignature = $request->header('X-Razorpay-Signature');

            // Verify webhook signature
            $secret = config('services.razorpay.webhook_secret');
            if ($secret) {
                // $generatedSignature = hash_hmac('sha256', json_encode($payload), $secret);
                $generatedSignature = hash_hmac('sha256', $request->getContent(), $secret);

                if (!hash_equals($generatedSignature, $expectedSignature)) {
                    Log::warning('Invalid webhook signature', ['payload' => $payload]);
                    return response()->json(['status' => 'error'], 400);
                }
            }

            $this->razorpayService->handleWebhook($payload);

            // Process completed payments
            if (($payload['event'] ?? null) === 'payment.captured') {
                $paymentEntity = $payload['payment']['entity'] ?? null;
                if ($paymentEntity) {
                    $payment = Payment::where('razorpay_order_id', $paymentEntity['order_id'])->first();
                    if ($payment && $payment->payment_type === 'whatsapp_package') {
                        $this->processWhatsAppPackagePurchase($payment);
                    }
                }
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Show payment success page
     */
    public function success(Request $request): View
    {
        $paymentId = $request->query('payment_id');
        $tenantId = $request->query('tenant');

        $tenant = Gym::where('id', $tenantId)->firstOrFail();

        $payment = null;
        if ($paymentId) {
            $payment = Payment::where('razorpay_payment_id', $paymentId)->first();
        }

        return view('payments.success', compact('payment', 'tenant'));
    }

    /**
     * Show payment checkout page
     */
    public function checkout(Request $request): View
    {
        $orderId = $request->query('order_id');
        $amount = $request->query('amount');
        $key = $request->query('key');
        $packageName = $request->query('package_name');
        $messageCount = $request->query('message_count');
        $tenantId = $request->query('tenantId');

        $tenant = Gym::where('id', $tenantId)->firstOrFail();

        if (!$orderId || !$amount || !$key) {
            abort(400, 'Invalid payment parameters');
        }

        return view('payments.checkout', compact(
            'orderId',
            'amount',
            'key',
            'packageName',
            'messageCount',
            'tenantId',
            'tenant'
        ));
    }

    /**
     * Show payment failure page
     */
    public function failure(Request $request): View
    {
        $orderId = $request->query('order_id');
        $tenantId = $request->query('tenant');

        $tenant = Gym::where('id', $tenantId)->firstOrFail();

        $payment = null;
        if ($orderId) {
            $payment = Payment::where('razorpay_order_id', $orderId)->first();
        }

        return view('payments.failure', compact('payment', 'tenant'));
    }

    /**
     * Process WhatsApp package purchase
     */
    private function processWhatsAppPackagePurchase(Payment $payment): void
    {
        // DB::transaction(function () use ($payment) {
        // });

        $packageId = $payment->metadata['package_id'] ?? null;

        if (!$packageId) {
            Log::error('Package ID not found in payment metadata', ['payment_id' => $payment->id]);
            return;
        }

        $package = WhatsAppMessagePackage::find($packageId);
        if (!$package) {
            Log::error('WhatsApp package not found', ['package_id' => $packageId, 'payment_id' => $payment->id]);
            return;
        }

        // Update or create WhatsApp balance
        $balance = GymWhatsAppBalance::firstOrCreate(
            ['gym_id' => $payment->gym_id],
            [
                'balance_messages' => 0,
                'total_messages_purchased' => 0,
                'messages_used' => 0,
            ]
        );

        $balance->increment('balance_messages', $package->message_count);
        $balance->increment('total_messages_purchased', $package->message_count);

        Log::info('WhatsApp package purchase processed', [
            'payment_id' => $payment->id,
            'package_id' => $packageId,
            'messages_added' => $package->message_count,
            'gym_id' => $payment->gym_id,
        ]);
    }
}
