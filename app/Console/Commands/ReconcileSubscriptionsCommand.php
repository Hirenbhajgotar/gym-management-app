<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\SubscriptionProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sweeps completed subscription payments that were never provisioned
 * (provisioned_at IS NULL) and provisions them through the shared, idempotent
 * SubscriptionProvisioningService. This is the safety net for the async
 * webhook path: if the queue worker is down or a listener throws, orphans are
 * reconciled on the next scheduled run.
 *
 * Payments held for manual review (metadata.amount_mismatch) are skipped, and
 * only subscription_* payment types are processed.
 */
class ReconcileSubscriptionsCommand extends Command
{
    protected $signature = 'app:reconcile-subscriptions
                            {--dry-run : List orphaned payments without provisioning them}
                            {--since= : Only consider payments created on or after this date (Y-m-d)}';

    protected $description = 'Provision any completed subscription payment that was never provisioned';

    public function handle(SubscriptionProvisioningService $provisioningService): int
    {
        $windowDays = config()->integer('subscriptions.reconcile_window_days', 7);
        $since = $this->option('since')
            ? Date::parse($this->option('since'))
            : now()->subDays($windowDays);

        $query = Payment::query()
            ->where('status', PaymentStatus::COMPLETED->value)
            ->whereNull('provisioned_at')
            ->where('payment_type', 'like', 'subscription_%')
            ->whereNull('metadata->amount_mismatch')
            ->where('created_at', '>=', $since);

        $processed = 0;
        $provisioned = 0;
        $failed = 0;
        $skipped = 0;

        $this->info('Reconciling orphaned subscription payments...');
        Log::info('Subscription reconciliation started', [
            'since' => $since->toDateTimeString(),
            'dry_run' => $this->option('dry-run'),
        ]);

        $query->chunkById(100, function ($payments) use (
            $provisioningService,
            &$processed,
            &$provisioned,
            &$failed,
            &$skipped
        ) {
            foreach ($payments as $payment) {
                $processed++;

                if ($this->option('dry-run')) {
                    $this->info("[DRY-RUN] Would provision payment {$payment->id} ({$payment->payment_type})");
                    $skipped++;

                    continue;
                }

                try {
                    $provisioningService->provision($payment->id);
                    $provisioned++;

                    $this->info("Provisioned payment {$payment->id} ({$payment->payment_type})");
                } catch (Throwable $e) {
                    $failed++;

                    $this->error("Failed to provision payment {$payment->id}: {$e->getMessage()}");
                    Log::error('Subscription reconciliation failed for payment', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        if ($failed > 0) {
            NotificationService::notifyAdmins(
                title: 'Subscription reconciliation had failures',
                message: "{$failed} of {$processed} orphaned payment(s) could not be provisioned.",
                type: 'danger',
            );
        }

        $this->newLine();
        $this->info("Reconciliation complete: processed={$processed}, provisioned={$provisioned}, failed={$failed}, skipped/dry-run={$skipped}");

        Log::info('Subscription reconciliation completed', [
            'processed' => $processed,
            'provisioned' => $provisioned,
            'failed' => $failed,
            'skipped' => $skipped,
            'dry_run' => $this->option('dry-run'),
        ]);

        return self::SUCCESS;
    }
}
