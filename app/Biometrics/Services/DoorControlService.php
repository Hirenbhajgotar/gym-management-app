<?php

namespace App\Biometrics\Services;

use App\Biometrics\DTOs\DoorControlResult;
use App\Enums\BiometricDoorControlType;
use App\Events\DoorControllerUnreachable;
use App\Models\Attendances;
use App\Models\BiometricDevice;
use App\Models\DoorEvent;
use App\Models\GymMember;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DoorControlService
{
    /**
     * Called when access is GRANTED
     */
    public function unlock(BiometricDevice $device): DoorControlResult
    {
        if (! $device->isDoorAccess()) {
            return DoorControlResult::notApplicable();
        }

        $doorControlType = $device->door_control_type;

        // Relay and Wiegand: FluxFitness does NOT call any API.
        // The device handles relay/wiegand trigger by reading
        // the webhook response field: door.action = "unlock"
        // FluxFitness simply returns skipped() — success assumed.
        if ($doorControlType === BiometricDoorControlType::Relay
            || $doorControlType === BiometricDoorControlType::Wiegand) {
            return DoorControlResult::skipped();
        }

        // API: FluxFitness calls the door controller REST API
        if ($doorControlType === BiometricDoorControlType::Api) {
            return $this->callDoorApi($device);
        }

        return DoorControlResult::notApplicable();
    }

    /**
     * Call the door controller API for API-based door control
     */
    private function callDoorApi(BiometricDevice $device): DoorControlResult
    {
        if (! $device->hasDoorApiControl()) {
            return DoorControlResult::failed('door_controller_url not configured');
        }

        try {
            $response = Http::timeout(3)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $device->door_controller_token,
                    'Content-Type' => 'application/json',
                ])
                ->post($device->door_controller_url, [
                    'action' => 'unlock',
                    'duration' => $device->unlock_duration_seconds,
                    'zone' => $device->door_zone,
                ]);

            if ($response->successful()) {
                return DoorControlResult::unlocked();
            }

            return DoorControlResult::failed(
                'Door API responded with error: ' . $response->status(),
                $response->json() ?? []
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            event(new DoorControllerUnreachable($device, $e->getMessage()));

            return DoorControlResult::failed('Door controller unreachable');
        } catch (\Exception $e) {
            event(new DoorControllerUnreachable($device, $e->getMessage()));

            return DoorControlResult::failed('Door controller error: ' . $e->getMessage());
        }
    }

    /**
     * Save door event to audit log
     */
    public function logEvent(
        BiometricDevice $device,
        ?GymMember $gymMember,
        ?Attendances $attendance,
        string $eventType,
        DoorControlResult $result
    ): DoorEvent {
        return DoorEvent::create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'gym_member_id' => $gymMember?->id,
            'attendance_id' => $attendance?->id,
            'event_type' => $eventType,
            'trigger_source' => 'biometric',
            'denial_reason' => $result->failureReason,
            'door_status' => $result->status,
            'unlock_duration_seconds' => $device->unlock_duration_seconds,
            'unlocked_at' => $result->unlockedAt,
            'ip_address' => request()->ip(),
            'raw_response' => $result->raw,
        ]);
    }
}
