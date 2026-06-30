<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\BiometricDevice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoorControllerUnreachable implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $gymId;

    public readonly int $deviceId;

    public readonly string $deviceName;

    public readonly string $errorMessage;

    public readonly string $timestamp;

    public function __construct(
        BiometricDevice $device,
        string $errorMessage
    ) {
        $this->gymId = $device->gym_id;
        $this->deviceId = $device->id;
        $this->deviceName = $device->device_name;
        $this->errorMessage = $errorMessage;
        $this->timestamp = now()->toISOString();
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("gym.{$this->gymId}.door");
    }

    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'device_name' => $this->deviceName,
            'error_message' => $this->errorMessage,
            'timestamp' => $this->timestamp,
        ];
    }
}
