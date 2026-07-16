<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GenerateInvoiceJob;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Provisions a subscription from a completed payment. Shared by the
 * interactive checkout flow (SubscriptionController), the queued webhook
 * listener (SubscriptionProvisioner), and the scheduled reconciliation
 * command (ReconcileSubscriptionsCommand).
 *
 * The {@see self::provision()} method is idempotent: a second call on an
 * already-provisioned payment is a no-op, and a payment held for manual
 * review (amount-mismatch) is never provisioned.
 */
final class SubscriptionProvisioningService
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Provision the subscription for a completed payment, if not already done.
     *
     * Safe to call multiple times (idempotent). Amount-mismatch payments are
     * held for manual review and never provisioned here.
     *
     * @throws Exception
     */
    public function provision(int $paymentId): void
    {
        $payment = Payment::query()->find($paymentId);

        if (! $payment) {
            return;
        }

        $session = $this->resolveSession($payment);

        // Amount reconciliation: if the captured amount differs from the
        // amount we created the order for beyond the configured tolerance, hold
        // the payment for manual review instead of provisioning.
        $toleranceRaw = config()->integer('subscriptions.amount_mismatch_tolerance', 0);
        $tolerance = (float) $toleranceRaw / 100;
        if ($session && abs((float) $payment->amount - (float) $session->amount) > $tolerance) {
            $payment->update([
                'metadata' => array_merge(
                    $payment->metadata ?? [],
                    [
                        'amount_mismatch' => true,
                        'captured_amount' => $payment->amount,
                        'expected_amount' => $session->amount,
                    ]
                ),
            ]);

            Log::warning('Payment amount mismatch; holding for review', [
                'payment_id' => $payment->id,
                'expected' => $session->amount,
                'captured' => $payment->amount,
            ]);

            NotificationService::notifyAdmins(
                title: 'Payment amount mismatch',
                message: 'Payment amount mismatch; holding for review',
                type: 'danger',
            );

            return;
        }

        try {
            DB::transaction(function () use ($payment, $session) {
                $locked = Payment::query()->where('id', $payment->id)->lockForUpdate()->first();

                if (! $locked || $locked->provisioned_at) {
                    return;
                }

                $this->provisionSubscription($locked, $session);

                $locked->update(['provisioned_at' => now()]);
            });

            if ($session) {
                $session->update(['status' => CheckoutSession::STATUS_COMPLETED]);
            }

            dispatch(new GenerateInvoiceJob($payment));
        } catch (Exception $e) {
            Log::error('Subscription provisioning failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function resolveSession(Payment $payment): ?CheckoutSession
    {
        $token = $payment->metadata['checkout_session_token'] ?? null;

        if ($token) {
            return CheckoutSession::query()->with('plan')->where('token', $token)->first();
        }

        return null;
    }

    private function provisionSubscription(Payment $payment, ?CheckoutSession $session): void
    {
        throw_unless($payment->user, Exception::class, 'User not found for payment');

        $type = $payment->payment_type;

        if ($type === 'subscription_new') {
            if ($session !== null) {
                $plan = $session->plan;
                $billingCycle = $session->billing_cycle;
                $metadata = $session->metadata ?? [];
                $trialDaysRaw = $metadata['trial_days'] ?? 0;
                $trialDays = is_numeric($trialDaysRaw) ? (int) $trialDaysRaw : 0;
            } else {
                $planId = $payment->metadata['plan_id'] ?? null;
                $plan = is_numeric($planId) ? Plan::query()->find((int) $planId) : null;
                $billingCycle = config()->string('subscriptions.default_billing_cycle');
                $trialDays = 0;
            }

            throw_unless($plan, Exception::class, 'Plan not found for new subscription');

            $this->subscriptionService->createSubscription($payment->user, $plan, [
                'billing_cycle' => $billingCycle,
                'trial_days' => $trialDays,
            ]);

            return;
        }

        if ($type === 'subscription_upgrade') {
            if ($session !== null) {
                $old = $session->subscription;
                $plan = $session->plan;
                $billingCycle = $session->billing_cycle;
            } else {
                $subscriptionId = $payment->metadata['subscription_id'] ?? null;
                $old = is_numeric($subscriptionId) ? Subscription::query()->find((int) $subscriptionId) : null;
                $planId = $payment->metadata['plan_id'] ?? null;
                $plan = is_numeric($planId) ? Plan::query()->find((int) $planId) : null;
                $billingCycle = $old !== null ? $old->billing_cycle : config()->string('subscriptions.default_billing_cycle');
            }

            throw_unless($old && $plan, Exception::class, 'Upgrade context missing');

            $this->subscriptionService->cancelSubscription($old, true);
            $this->subscriptionService->createSubscription($payment->user, $plan, [
                'billing_cycle' => $billingCycle,
                'trial_days' => 0,
            ]);

            return;
        }

        if ($type === 'subscription_renewal') {
            if ($session !== null) {
                $subscription = $session->subscription;
            } else {
                $subscriptionId = $payment->metadata['subscription_id'] ?? null;
                $subscription = is_numeric($subscriptionId) ? Subscription::query()->find((int) $subscriptionId) : null;
            }

            throw_unless($subscription, Exception::class, 'Subscription not found for renewal');

            $this->subscriptionService->renewSubscription($subscription, (float) $payment->amount);

            return;
        }
    }
}
