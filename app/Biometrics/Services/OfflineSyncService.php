<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Enums\AttendanceStatus;
use App\Models\Attendances;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;

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
     * @param  array<int, array<string, mixed>>  $events  Array of offline event data
     * @param  BiometricDevice  $device  The device that recorded the events
     * @return array<int, array<string, mixed>> Results per event
     */
    public function processBatch(array $events, BiometricDevice $device): array
    {
        $results = [];
        $provider = $device->hasCloudProvider() && $device->provider_type
            ? $this->registry->resolve($device->provider_type, $device->provider_config ?? [])
            : null;

        foreach ($events as $event) {
            $results[] = $this->processEvent($event, $device, $provider);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  BiometricProvider|null  $provider
     * @return array<string, mixed>
     */
    protected function processEvent(array $event, BiometricDevice $device, $provider): array
    {
        $capturedAt = Date::parse($event['captured_at']);

        // ── Device-Native Mode ──────────────────────────────────────────
        if ($device->isDeviceNative()) {
            $memberIdentifier = $event['gym_member_id']
                ?? $event['member_id']
                ?? $event['user_id']
                ?? $event['template_ref']
                ?? null;

            $gymMember = null;
            if (is_int($memberIdentifier) || (is_string($memberIdentifier) && ctype_digit($memberIdentifier))) {
                $gymMember = GymMember::query()->where('gym_id', $device->gym_id)->find((int) $memberIdentifier);
            }

            if (! $gymMember && filled($memberIdentifier) && (is_string($memberIdentifier) || is_int($memberIdentifier))) {
                $template = BiometricTemplate::findByTemplateRef($device->gym_id, (string) $memberIdentifier);

                $gymMember = $template?->gymMember;
            }

            if (! $gymMember) {
                return [
                    'offline_id' => $event['offline_id'],
                    'status' => 'retroactively_denied',
                    'reason' => 'member_not_found',
                ];
            }

            $guardResult = $this->membershipGuard->canCheckIn($gymMember);

            if (! $guardResult->allowed) {
                return [
                    'offline_id' => $event['offline_id'],
                    'status' => 'retroactively_denied',
                    'reason' => $guardResult->reason,
                ];
            }

            return $this->recordOfflineAttendance($event, $device, $gymMember, $capturedAt, 'device_native');
        }

        // ── Cloud Provider Mode ─────────────────────────────────────────
        if (! $provider) {
            return [
                'offline_id' => $event['offline_id'] ?? null,
                'status' => 'retroactively_denied',
                'reason' => 'no_provider_configured',
            ];
        }

        $rawType = $event['biometric_type'] ?? 'face';
        $rawData = $event['data'] ?? '';
        $rawMetadata = $event['metadata'] ?? [];

        /** @var array<string, mixed> $metadata */
        $metadata = is_array($rawMetadata) ? $rawMetadata : [];

        $sample = new BiometricSample(
            type: is_scalar($rawType) ? (string) $rawType : 'face',
            data: is_scalar($rawData) ? (string) $rawData : '',
            metadata: $metadata,
        );

        // Step 1: Re-run AntiSpoofGuard
        if (! $this->antiSpoofGuard->check($sample, $provider)) {
            return [
                'offline_id' => $event['offline_id'],
                'status' => 'retroactively_denied',
                'reason' => 'liveness_failed',
            ];
        }

        // Step 2: Load ALL BiometricTemplates for this gym and biometric type
        $templates = BiometricTemplate::query()->where('gym_id', $device->gym_id)
            ->where('biometric_type', $event['biometric_type'] ?? 'face')
            ->where('is_active', true)
            ->with('gymMember')
            ->get();

        if ($templates->isEmpty()) {
            return [
                'offline_id' => $event['offline_id'],
                'status' => 'retroactively_denied',
                'reason' => 'no_template_enrolled',
            ];
        }

        // Step 3: Try to match against each template
        $matchedTemplate = null;
        $confidenceThreshold = config('biometrics.confidence_threshold', 0.80);

        foreach ($templates as $template) {
            $templateRef = TemplateRef::fromJson($template->template_ref);
            $verificationResult = $provider->verify($sample, $templateRef);

            if ($verificationResult->matched && $verificationResult->confidence >= $confidenceThreshold) {
                $matchedTemplate = $template;
                break; // Found a match, stop iterating
            }
        }

        if (! $matchedTemplate) {
            return [
                'offline_id' => $event['offline_id'],
                'status' => 'retroactively_denied',
                'reason' => 'verification_failed',
            ];
        }

        $gymMember = $matchedTemplate->gymMember;

        if (! $gymMember) {
            return [
                'offline_id' => $event['offline_id'],
                'status' => 'retroactively_denied',
                'reason' => 'member_not_found',
            ];
        }

        $guardResult = $this->membershipGuard->canCheckIn($gymMember);

        if (! $guardResult->allowed) {
            return [
                'offline_id' => $event['offline_id'],
                'status' => 'retroactively_denied',
                'reason' => $guardResult->reason,
            ];
        }

        return $this->recordOfflineAttendance($event, $device, $gymMember, $capturedAt, $device->provider_type?->value);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function recordOfflineAttendance(
        array $event,
        BiometricDevice $device,
        GymMember $gymMember,
        Carbon|\Illuminate\Support\Carbon $capturedAt,
        ?string $providerType
    ): array {

        // Step 6: Create or update attendance with offline timestamps (idempotent)
        $rawOfflineId = $event['offline_id'] ?? null;
        if (blank($rawOfflineId)) {
            // Derive deterministic ID from event properties
            $offlineId = hash('sha256', $device->id . '|' . ($event['captured_at'] ?? '') . '|' . ($event['biometric_type'] ?? ''));
        } else {
            $offlineId = is_string($rawOfflineId) || is_int($rawOfflineId) ? (string) $rawOfflineId : (string) json_encode($rawOfflineId);
        }

        // Devices designated as door access only should not create attendance records
        if (! $device->shouldCreateAttendance()) {
            return [
                'offline_id' => $event['offline_id'] ?? $offlineId,
                'status' => 'accepted',
                'attendance_id' => null,
                'is_suspicious' => false,
                'requires_review' => false,
                'action' => 'access_granted_no_attendance',
            ];
        }

        // Check for existing attendance (prevent duplicates from retry)
        $existingAttendance = Attendances::query()->where('device_id', $device->id)
            ->where('offline_id', $offlineId)
            ->first();

        if ($existingAttendance) {
            // Already synced - return existing record
            return [
                'offline_id' => $event['offline_id'] ?? $offlineId,
                'status' => 'accepted',
                'attendance_id' => $existingAttendance->id,
                'is_suspicious' => false,
                'already_synced' => true,
            ];
        }

        $rawDirection = $event['direction'] ?? 'entry';
        $direction = strtolower(is_string($rawDirection) ? $rawDirection : 'entry');
        $isExit = in_array($direction, ['exit', 'check_out', 'out'], true);

        if ($isExit) {
            $openAttendance = Attendances::query()
                ->where('attendanceable_id', $gymMember->id)
                ->where('attendanceable_type', GymMember::class)
                ->where('gym_id', $device->gym_id)
                ->whereNull('check_out_time')
                ->where('check_in_time', '<=', $capturedAt)
                ->latest('check_in_time')
                ->first();

            if ($openAttendance) {
                $openAttendance->update([
                    'status' => AttendanceStatus::Completed,
                    'check_out_time' => $capturedAt,
                ]);

                return [
                    'offline_id' => $event['offline_id'] ?? $offlineId,
                    'status' => 'accepted',
                    'attendance_id' => $openAttendance->id,
                    'action' => 'check_out',
                    'is_suspicious' => false,
                ];
            }

            return [
                'offline_id' => $event['offline_id'] ?? $offlineId,
                'status' => 'retroactively_denied',
                'reason' => 'no_active_check_in',
            ];
        }

        // Semantic duplicate check: scan within anti-passback window for open, granted check-in
        $fraudWindow = config()->integer('biometrics.anti_passback_window_seconds', 60);
        $semanticDuplicate = Attendances::query()
            ->where('attendanceable_id', $gymMember->id)
            ->where('attendanceable_type', GymMember::class)
            ->where('gym_id', $device->gym_id)
            ->whereNull('check_out_time')
            ->where('check_in_result', 'granted')
            ->whereBetween('check_in_time', [
                $capturedAt->copy()->subSeconds($fraudWindow),
                $capturedAt->copy()->addSeconds($fraudWindow),
            ])
            ->latest('check_in_time')
            ->first();

        if ($semanticDuplicate) {
            return [
                'offline_id' => $event['offline_id'] ?? $offlineId,
                'status' => 'accepted',
                'attendance_id' => $semanticDuplicate->id,
                'is_suspicious' => true,
                'already_synced' => true,
                'duplicate_suppressed' => true,
            ];
        }

        $isSuspicious = $this->fraudDetector->isDuplicate($gymMember);

        // Events older than 24 hours (offline_ttl_hours) require manager manual review
        $maxTtlHours = config()->integer('biometrics.offline_ttl_hours', 24);
        $isOverTtl = $capturedAt->diffInHours(now()) > $maxTtlHours;

        $checkInResult = $isOverTtl
            ? 'pending_review'
            : ($isSuspicious ? 'suspicious' : 'granted');

        $note = $isOverTtl
            ? "Offline event older than {$maxTtlHours} hours. Requires manager review."
            : ($isSuspicious ? 'Suspicious offline duplicate check-in flagged for review.' : null);

        $attendance = $gymMember->attendances()->create([
            'gym_id' => $device->gym_id,
            'check_in_time' => $capturedAt,
            'status' => AttendanceStatus::Present,
            'check_in_method' => 'biometric',
            'device_id' => $device->id,
            'offline_id' => $offlineId,
            'biometric_type' => $event['biometric_type'] ?? $device->device_type ?? 'face',
            'provider_type' => $providerType ?? $device->provider_type?->value,
            'confidence_score' => $event['confidence_score'] ?? null,
            'liveness_score' => $event['liveness_score'] ?? null,
            'check_in_result' => $checkInResult,
            'synced_at' => now(),
            'note' => $note,
        ]);

        return [
            'offline_id' => $event['offline_id'] ?? $offlineId,
            'status' => $isOverTtl ? 'requires_review' : 'accepted',
            'attendance_id' => $attendance->id,
            'is_suspicious' => $isSuspicious,
            'requires_review' => $isOverTtl,
        ];
    }
}
