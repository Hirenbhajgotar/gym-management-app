<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Jobs\SendBroadcastJob;
use Illuminate\Support\Facades\Log;

class WhatsAppBroadcastService
{
    public function __construct(
        protected int $gymId
    ) {}

    public function dispatchBroadcast(int $broadcastId): void
    {
        dispatch(new SendBroadcastJob($broadcastId, $this->gymId));

        Log::info('Broadcast dispatched for async processing', [
            'broadcast_id' => $broadcastId,
            'gym_id' => $this->gymId,
        ]);
    }
}
