<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Jobs\SendBroadcastJob;
use App\Models\WhatsAppBroadcast;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledBroadcastsCommand extends Command
{
    protected $signature = 'whatsapp:process-scheduled';

    protected $description = 'Process WhatsApp broadcasts that are scheduled and due for sending';

    private const MAX_BROADCASTS_PER_RUN = 100;

    /**
     * Execute the console command.
     *
     * Atomically claims each scheduled broadcast (status → 'sending') before
     * dispatching SendBroadcastJob with skipCreditDeduction=true, because
     * credits were already deducted upfront by BroadcastOrchestrator.
     */
    public function handle(): int
    {
        $broadcasts = WhatsAppBroadcast::query()->where('status', WhatsAppBroadcastStatusEnum::SCHEDULED->value)
            ->where('scheduled_at', '<=', now())
            ->limit(self::MAX_BROADCASTS_PER_RUN)
            ->lazy();

        $processed = 0;

        foreach ($broadcasts as $broadcast) {
            $claimed = WhatsAppBroadcast::query()->where('id', $broadcast->id)
                ->where('status', WhatsAppBroadcastStatusEnum::SCHEDULED->value)
                ->update([
                    'status' => WhatsAppBroadcastStatusEnum::SENDING->value,
                    'started_at' => now(),
                ]);

            if ($claimed === 0) {
                $this->warn("Broadcast #{$broadcast->id} was already claimed by another process, skipping.");

                continue;
            }

            try {
                dispatch(new SendBroadcastJob($broadcast->id, $broadcast->gym_id, skipCreditDeduction: true));

                $this->info("Dispatched broadcast #{$broadcast->id}: {$broadcast->title}");

                Log::info('Scheduled broadcast dispatched', [
                    'broadcast_id' => $broadcast->id,
                    'gym_id' => $broadcast->gym_id,
                    'title' => $broadcast->title,
                    'scheduled_at' => $broadcast->scheduled_at,
                ]);

                $processed++;
            } catch (Exception $e) {
                $broadcast->update(['status' => WhatsAppBroadcastStatusEnum::FAILED->value]);

                Log::error('Failed to dispatch scheduled broadcast', [
                    'broadcast_id' => $broadcast->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Failed to dispatch broadcast #{$broadcast->id}: {$e->getMessage()}");
            }
        }

        if ($processed === 0) {
            $this->info('No scheduled broadcasts to process.');

            return self::SUCCESS;
        }

        $this->info("Dispatched {$processed} broadcast(s).");

        return self::SUCCESS;
    }
}
