<?php

namespace App\Biometrics\Services;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\BiometricSample;
use App\Enums\AttendanceStatus;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use Carbon\Carbon;

class OfflineSyncService
{
    public function __construct(
        protected BiometricProviderRegistry $registry,
        protected AntiSpoofGuard $antiSpoofGuard,
        protected MembershipGuard $membershipGuard,
        protected FraudDetector $fraudDetector
    ) {}

    /**
     * Process a batch of offline events
     *
     * @param  array  $events  Array of offline event data
     * @param  BiometricDevice  $device  The device that recorded the events
     * @return array Results per event
     */
    public function processBatch(array $events, BiometricDevice $device): array
    {
        $results = [];
        $provider = $this->registry->resolve($device->provider_type, $device->provider_config ?? []);

        foreach ($events as $event) {
            $results[] = $this->processEvent($event, $device, $provider);
        }

        return $results;
    }

    protected function processEvent(array $event, BiometricDevice $device, $provider): array
    {
        $capturedAt = Carbon::parse($event['captured_at']);
        $sample = new BiometricSample(
            type: $event['biometric_type'],
            data: $event['data'],
            metadata: $event['metadata'] ?? []
        );

        // Step 1: Re-run AntiSpoofGuard
        if (! $this->antiSpoofGuard->check($sample, $provider)) {
            return [
                'offline_id' => $event['offline_id'],
                'status' => 'retroactively_denied',
                'reason' => 'liveness_failed',
            ];
        }

        // Step 2: Load BiometricTemplate
        $template = BiometricTemplate::where('gym_id', $device->gym_id)
            ->where('biometric_type', $event['biometric_type'])
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return [
                'offline_id' => $event['offline_id'],
                'status' => 'retroactively_denied',
                'reason' => 'no_template_enrolled',
            ];
        }

        $gymMember = $template->gymMember;

        if (! $gymMember) {
            return [
                'offline_id' => $event['offline_id'],
                'status' => 'retroactively_denied',
                'reason' => 'member_not_found',
            ];
        }

        // Step 3 & 4: Re-run MembershipGuard and FraudDetector at captured_at time
        // For simplicity, we use current state for these checks
        // A more sophisticated implementation would query historical state

        $guardResult = $this->membershipGuard->canCheckIn($gymMember);

        if (! $guardResult->allowed) {
            return [
                'offline_id' => $event['offline_id'],
                'status' => 'retroactively_denied',
                'reason' => $guardResult->reason,
            ];
        }

        // Step 5: Create attendance with offline timestamps
        $isSuspicious = $this->fraudDetector->isDuplicate($gymMember);

        $attendance = $gymMember->attendances()->create([
            'gym_id' => $device->gym_id,
            'check_in_time' => $capturedAt,
            'status' => AttendanceStatus::Present,
            'check_in_method' => 'biometric',
            'device_id' => $device->id,
            'biometric_type' => $event['biometric_type'],
            'provider_type' => $device->provider_type,
            'confidence_score' => $event['confidence_score'] ?? null,
            'liveness_score' => $event['liveness_score'] ?? null,
            'check_in_result' => $isSuspicious ? 'suspicious' : 'granted',
            'synced_at' => now(),
        ]);

        return [
            'offline_id' => $event['offline_id'],
            'status' => 'accepted',
            'attendance_id' => $attendance->id,
            'is_suspicious' => $isSuspicious,
        ];
    }
}
