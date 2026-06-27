<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Attendances;
use App\Models\BiometricDevice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MemberCheckedOut implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $gymId;

    public readonly int $memberId;

    public readonly string $memberName;

    public readonly string $deviceName;

    public readonly string $checkOutTime;

    public readonly Attendances $attendance;

    public function __construct(
        Attendances $attendance,
        BiometricDevice $device
    ) {
        $this->attendance = $attendance;
        $this->gymId = $device->gym_id;
        $this->memberId = $attendance->attendanceable_id;
        $this->memberName = $attendance->attendanceable?->name ?? 'Unknown';
        $this->deviceName = $device->device_name;
        $this->checkOutTime = $attendance->check_out_time?->toISOString() ?? now()->toISOString();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('gym.' . $this->gymId . '.attendance'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->attendance->id,
            'member_id' => $this->memberId,
            'member_name' => $this->memberName,
            'gym_id' => $this->gymId,
            'check_out_time' => $this->checkOutTime,
            'check_in_method' => $this->attendance->check_in_method,
            'is_biometric' => $this->attendance->isBiometric(),
            'device_name' => $this->deviceName,
            'timestamp' => now()->toISO8601String(),
        ];
    }
}
