<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AdmsAccessDenied implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $gymId,
        public readonly ?int $memberId,
        public readonly ?string $memberName,
        public readonly string $deviceName,
        public readonly string $denialReason,
        public readonly string $timestamp,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("gym.{$this->gymId}.attendance");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'member_id' => $this->memberId,
            'member_name' => $this->memberName,
            'gym_id' => $this->gymId,
            'device_name' => $this->deviceName,
            'denial_reason' => $this->denialReason,
            'timestamp' => $this->timestamp,
        ];
    }
}
