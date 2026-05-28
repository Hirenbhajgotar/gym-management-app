<?php

namespace App\Console\Commands;

use App\Jobs\SendBroadcastJob;
use App\Models\WhatsAppBroadcast;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledBroadcastsCommand extends Command
{
    protected $signature = 'whatsapp:process-scheduled';

    protected $description = 'Process WhatsApp broadcasts that are scheduled and due for sending';

    /**
     * Execute the console command.
     *
     * Dispatches SendBroadcastJob for each broadcast that is due.
     * The job uses ShouldBeUnique (keyed by broadcast ID) so if the same
     * broadcast is already pending on the queue, the duplicate dispatch
     * is silently dropped — no risk of double-processing.
     */
    public function handle(): int
    {
        $broadcasts = WhatsAppBroadcast::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($broadcasts->isEmpty()) {
            $this->info('No scheduled broadcasts to process.');

            return self::SUCCESS;
        }

        $this->info("Found {$broadcasts->count()} broadcast(s) to process.");

        foreach ($broadcasts as $broadcast) {
            try {
                SendBroadcastJob::dispatch($broadcast->id, $broadcast->gym_id);

                $this->info("Queued broadcast #{$broadcast->id}: {$broadcast->title}");

                Log::info('Scheduled broadcast queued for processing', [
                    'broadcast_id' => $broadcast->id,
                    'gym_id' => $broadcast->gym_id,
                    'title' => $broadcast->title,
                    'scheduled_at' => $broadcast->scheduled_at,
                ]);
            } catch (\Exception $e) {
                $broadcast->update(['status' => 'failed']);

                Log::error('Failed to queue scheduled broadcast', [
                    'broadcast_id' => $broadcast->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Failed to queue broadcast #{$broadcast->id}: {$e->getMessage()}");
            }
        }

        $this->info('Scheduled broadcast processing complete.');

        return self::SUCCESS;
    }
}
