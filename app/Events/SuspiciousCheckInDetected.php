<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\BiometricDevice;
use App\Models\GymMember;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuspiciousCheckInDetected implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $gymId;

    public readonly int $memberId;

    public readonly string $memberName;

    public readonly string $deviceName;

    public readonly string $reason;

    public readonly string $timestamp;

    public function __construct(
        GymMember $gymMember,
        BiometricDevice $device,
        string $reason
    ) {
        $this->gymId = $device->gym_id;
        $this->memberId = $gymMember->id;
        $this->memberName = $gymMember->name;
        $this->deviceName = $device->device_name;
        $this->reason = $reason;
        $this->timestamp = now()->toISO8601String();
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("gym.{$this->gymId}.attendance");
    }

    public function broadcastWith(): array
    {
        return [
            'member_id' => $this->memberId,
            'member_name' => $this->memberName,
            'gym_id' => $this->gymId,
            'device_name' => $this->deviceName,
            'reason' => $this->reason,
            'type' => 'suspicious_check_in',
            'timestamp' => $this->timestamp,
        ];
    }
}
