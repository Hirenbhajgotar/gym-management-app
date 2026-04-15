<?php

namespace App\Jobs;

use App\Models\WhatsAppBroadcast;
use App\Providers\WhatsAppProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendBroadcastJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 3600;

    public function __construct(public int $broadcastId, public int $gymId) {}

    public function handle(): void
    {
        $broadcast = WhatsAppBroadcast::find($this->broadcastId);

        if (! $broadcast) {
            Log::warning('Broadcast not found for processing', [
                'broadcast_id' => $this->broadcastId,
            ]);

            return;
        }

        if (in_array($broadcast->status, ['completed', 'cancelled'])) {
            Log::info('Broadcast already processed, skipping', [
                'broadcast_id' => $this->broadcastId,
                'status' => $broadcast->status,
            ]);

            return;
        }

        try {
            $provider = WhatsAppProviderFactory::create((int) $this->gymId);

            $results = $provider->sendBroadcast($this->broadcastId);

            Log::info('Broadcast processing completed', [
                'broadcast_id' => $this->broadcastId,
                'gym_id' => $this->gymId,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Broadcast processing failed', [
                'broadcast_id' => $this->broadcastId,
                'gym_id' => $this->gymId,
                'error' => $e->getMessage(),
            ]);

            $broadcast->update(['status' => 'failed']);

            throw $e; // Re-throw to trigger retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendBroadcastJob permanently failed', [
            'broadcast_id' => $this->broadcastId,
            'gym_id' => $this->gymId,
            'error' => $exception->getMessage(),
        ]);

        WhatsAppBroadcast::where('id', $this->broadcastId)
            ->update(['status' => 'failed']);
    }
}
