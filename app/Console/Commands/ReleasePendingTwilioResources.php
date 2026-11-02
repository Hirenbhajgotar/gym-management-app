<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReleaseTenantTwilioResources;
use App\Models\WhatsAppAccountRelease;
use Illuminate\Console\Command;

/**
 * Re-dispatch Twilio releases that never confirmed success.
 *
 * A tenant delete commits even when the external release cannot run, because the local
 * rows are already gone. The intent row is the durable record; this sweep is what keeps a
 * failed queue job from stranding a billed subaccount forever.
 */
class ReleasePendingTwilioResources extends Command
{
    protected $signature = 'whatsapp:release-pending-accounts {--limit=50 : Maximum number of releases to re-dispatch}';

    protected $description = 'Re-dispatch Twilio resource releases left pending or failed after a tenant deletion';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $releases = WhatsAppAccountRelease::query()
            ->whereIn('status', [
                WhatsAppAccountRelease::STATUS_PENDING,
                WhatsAppAccountRelease::STATUS_FAILED,
            ])
            // A row the queue has already retried to exhaustion is left in place for an
            // operator rather than redispatching the same failing release forever.
            ->where('attempts', '<', 5)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        foreach ($releases as $release) {
            ReleaseTenantTwilioResources::dispatch($release->getKey());
        }

        $this->info("Dispatched {$releases->count()} Twilio release(s) for retry.");

        return self::SUCCESS;
    }
}
