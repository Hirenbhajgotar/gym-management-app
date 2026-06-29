<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsAppBroadcast;
use Illuminate\Support\Facades\Cache;

class BroadcastRateLimiter
{
    private const LOCK_TIMEOUT = 10;

    public function getQuota(int $gymId): array
    {
        $dailyLimit = (int) config('app.whatsapp_broadcast_limit_per_day', 2);
        $used = WhatsAppBroadcast::query()->where('gym_id', $gymId)
            ->whereDate('created_at', today())
            ->count();

        return [
            'used' => $used,
            'remaining' => max(0, $dailyLimit - $used),
            'limit' => $dailyLimit,
        ];
    }

    public function canSend(int $gymId): bool
    {
        $quota = $this->getQuota($gymId);

        if ($quota['remaining'] <= 0) {
            return false;
        }

        if ($this->getCooldown($gymId) !== null) {
            return false;
        }

        return true;
    }

    public function getCooldown(int $gymId): ?int
    {
        $lastBroadcast = WhatsAppBroadcast::query()->where('gym_id', $gymId)
            ->latest()
            ->first();

        if (! $lastBroadcast) {
            return null;
        }

        $nextAllowed = $lastBroadcast->created_at?->addHour();

        if (now()->greaterThanOrEqualTo($nextAllowed)) {
            return null;
        }

        return (int) now()->diffInMinutes($nextAllowed, false);
    }

    public function acquireLock(int $gymId): bool
    {
        return Cache::lock("broadcast-rate-{$gymId}", self::LOCK_TIMEOUT)->get();
    }
}
