<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\WhatsAppCreditTransaction;
use App\Models\WhatsAppPackage;
use App\Services\InvoiceService;
use App\Services\RazorpayService;
use App\Services\WhatsAppBillingService;
use App\Traits\HasTenantAccess;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    use HasTenantAccess;

    protected RazorpayService $razorpayService;

    protected InvoiceService $invoiceService;

    protected WhatsAppBillingService $whatsAppBillingService;

    public function __construct(RazorpayService $razorpayService, InvoiceService $invoiceService)
    {
        $this->razorpayService = $razorpayService;
        $this->invoiceService = $invoiceService;
        $this->whatsAppBillingService = new WhatsAppBillingService($razorpayService);
    }

    /**
     * Create Razorpay order for WhatsApp package purchase
     */
    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => ['required', 'exists:whatsapp_packages,id'],
            'gym_id' => ['required', 'exists:gyms,id'],
        ]);

        try {
            $package = WhatsAppPackage::query()->findOrFail($request->package_id);
            $gym = Gym::query()->findOrFail($request->gym_id);

            // Check if user has permission to purchase for this gym
            if ($this->getGymId() !== $gym->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to gym.',
                ], 403);
            }

            $orderData = $this->razorpayService->createOrder([
                'amount' => is_numeric($package->price) ? (float) $package->price : 0.0,
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
        } catch (Exception $e) {
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
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
            'tenant_id' => ['required'],
        ]);

        try {
            DB::beginTransaction();

            $verifiedPayment = $this->razorpayService->verifyPayment(
                $request->razorpay_order_id,
                $request->razorpay_payment_id,
                $request->razorpay_signature
            );

            if (! $verifiedPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment record not found.',
                ], 404);
            }

            if (! $verifiedPayment->isCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed.',
                ], 400);
            }

            // Process the purchase (add messages to balance)
            $this->processWhatsAppPackagePurchase($verifiedPayment);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified and package purchased successfully.',
                'payment_id' => $verifiedPayment->razorpay_payment_id,
            ]);
        } catch (Exception $e) {
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
     * Show payment success page
     */
    public function success(Request $request): View
    {
        $paymentId = $request->query('payment_id');
        $tenantId = $request->query('tenant');

        $tenant = Gym::query()->where('id', $tenantId)->firstOrFail();

        $payment = null;
        if ($paymentId) {
            $payment = Payment::query()->where('razorpay_payment_id', $paymentId)->first();
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

        $tenant = Gym::query()->where('id', $tenantId)->firstOrFail();

        abort_if(! $orderId || ! $amount || ! $key, 400, 'Invalid payment parameters');

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

        $tenant = Gym::query()->where('id', $tenantId)->firstOrFail();

        $payment = null;
        if ($orderId) {
            $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();
        }

        return view('payments.failure', compact('payment', 'tenant'));
    }

    /**
     * Process WhatsApp package purchase
     */
    private function processWhatsAppPackagePurchase(Payment $payment): void
    {
        // Check if already processed to prevent double processing
        $existingTransaction = WhatsAppCreditTransaction::query()->where('payment_reference', $payment->razorpay_payment_id)
            ->where('gym_id', $payment->gym_id)
            ->first();

        if ($existingTransaction) {
            Log::info('WhatsApp package purchase already processed', [
                'payment_id' => $payment->id,
                'transaction_id' => $existingTransaction->id,
                'gym_id' => $payment->gym_id,
            ]);

            return;
        }

        $packageId = $payment->metadata['package_id'] ?? null;

        if (! $packageId) {
            Log::error('Package ID not found in payment metadata', ['payment_id' => $payment->id]);

            return;
        }

        $package = WhatsAppPackage::query()->find($packageId);
        if (! $package) {
            Log::error('WhatsApp package not found', ['package_id' => $packageId, 'payment_id' => $payment->id]);

            return;
        }

        if (! $package->is_active) {
            Log::warning('WhatsApp package is not active', ['package_id' => $packageId, 'payment_id' => $payment->id]);

            return;
        }

        try {
            $result = $this->whatsAppBillingService->purchasePackage(
                $payment->gym_id,
                $packageId,
                $payment->razorpay_payment_id
            );

            if ($result['success']) {
                Log::info('WhatsApp package purchase processed successfully', [
                    'payment_id' => $payment->id,
                    'package_id' => $packageId,
                    'messages_added' => $result['message_count'],
                    'gym_id' => $payment->gym_id,
                ]);

                dispatch(new GenerateInvoiceJob($payment));
            } else {
                Log::error('Failed to process WhatsApp package purchase', [
                    'payment_id' => $payment->id,
                    'package_id' => $packageId,
                    'gym_id' => $payment->gym_id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (Exception $e) {
            Log::error('Exception processing WhatsApp package purchase', [
                'payment_id' => $payment->id,
                'package_id' => $packageId,
                'gym_id' => $payment->gym_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
