<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Services\SubscriptionProvisioningService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Queued listener for the PaymentCompleted event. Delegates all provisioning
 * logic to the shared SubscriptionProvisioningService so the same idempotent
 * path is used by the interactive checkout flow and the scheduled reconcile.
 *
 * The webhook dispatches this event asynchronously; failures are logged and
 * re-thrown so the queue worker retries (and the reconcile command is the
 * eventual safety net for orphans).
 */
class SubscriptionProvisioner implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PaymentCompleted $event): void
    {
        try {
            resolve(SubscriptionProvisioningService::class)->provision($event->paymentId);
        } catch (Exception $e) {
            Log::error('Subscription provisioning failed in queued listener', [
                'payment_id' => $event->paymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
