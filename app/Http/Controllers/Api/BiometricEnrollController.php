<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\Services\BiometricDataDeletionService;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricEnrollRequest;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BiometricEnrollController extends Controller
{
    public function __construct(
        protected BiometricProviderRegistry $registry,
        protected BiometricDataDeletionService $deletionService
    ) {}

    public function enroll(BiometricEnrollRequest $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        // Scope member lookup to device's gym (prevents cross-gym enrollment)
        /** @var GymMember $gymMember */
        $gymMember = GymMember::query()->where('gym_id', $device->gym_id)
            ->findOrFail($request->validated('gym_member_id'));

        // Enforce that active consent exists - do not auto-create
        if (! $gymMember->hasActiveBiometricConsent()) {
            return response()->json([
                'success' => false,
                'error' => 'Biometric consent required',
                'message' => 'Member must have an active biometric consent on file',
            ], 422);
        }

        if ($device->isDeviceNative() || ! $device->hasCloudProvider() || ! $device->provider_type) {
            return response()->json([
                'success' => false,
                'error' => 'Device-native enrollment required',
                'message' => 'This device operates in device-native mode. Use /enroll-sync to register on-device templates.',
            ], 422);
        }

        // Get provider and enroll
        $provider = $this->registry->resolve($device->provider_type, is_array($device->provider_config) ? $device->provider_config : []);

        $rawType = $request->validated('biometric_type');
        $biometricType = is_string($rawType) ? $rawType : 'face';
        $rawData = $request->validated('data');
        $rawMeta = $request->validated('metadata');
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($rawMeta) ? $rawMeta : [];

        $sample = new BiometricSample(
            type: $biometricType,
            data: is_string($rawData) ? $rawData : '',
            metadata: $metadata
        );

        try {
            $templateRef = $provider->enroll($gymMember, $sample);

            // Resolve command PINs (both member ID and any existing template refs)
            $existingTemplates = BiometricTemplate::query()
                ->where('gym_id', $device->gym_id)
                ->where('gym_member_id', $gymMember->id)
                ->where('enrolled_by_device_id', $device->id)
                ->get();

            $pins = [(string) $gymMember->id];
            foreach ($existingTemplates as $existingTemplate) {
                $pins[] = $existingTemplate->resolvedCommandPin();
            }
            $pins = array_values(array_unique($pins));

            $template = DB::transaction(function () use ($device, $gymMember, $pins, $templateRef, $request): BiometricTemplate {
                // Cancel any pending or in-flight delete commands on this device for this member
                AdmsDeviceCommand::cancelDeleteCommands($device->id, $pins);

                BiometricTemplate::query()
                    ->where('gym_id', $device->gym_id)
                    ->where('gym_member_id', $gymMember->id)
                    ->where('enrolled_by_device_id', $device->id)
                    ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
                    ->update([
                        'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
                        'is_active' => false,
                    ]);

                // Create BiometricTemplate record
                return BiometricTemplate::query()->create([
                    'gym_member_id' => $gymMember->id,
                    'gym_id' => $device->gym_id,
                    'enrolled_by_device_id' => $device->id,
                    'biometric_type' => $request->validated('biometric_type'),
                    'provider_type' => $device->provider_type,
                    'template_ref' => $templateRef->toJson(),
                    'quality_score' => $templateRef->meta['quality_score'] ?? null,
                    'is_active' => true,
                    'enrolled_at' => now(),
                ]);
            });

            return response()->json([
                'success' => true,
                'template_id' => $template->id,
                'quality_score' => $template->quality_score,
                'enrolled_at' => $template->enrolled_at->toISOString(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Enrollment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function syncFromDevice(Request $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        /** @var array{gym_member_id: int|string, biometric_type: string|BiometricTypeEnum, template_ref: string, quality_score?: float|int|null} $validated */
        $validated = $request->validate([
            'gym_member_id' => [
                'required',
                'exists:gym_members,id',
                // Ensure member is same gym as device
                Rule::exists('gym_members', 'id')
                    ->where('gym_id', $device->gym_id),
            ],
            'biometric_type' => ['required', Rule::enum(BiometricTypeEnum::class)],
            'template_ref' => ['required', 'string', 'max:255'], // device internal ID / PIN
            'quality_score' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $gymMemberId = (int) $validated['gym_member_id'];
        $templateRef = $validated['template_ref'];

        if (is_numeric($templateRef) && (int) $templateRef !== $gymMemberId) {
            return response()->json([
                'success' => false,
                'error' => 'Template reference mismatch',
                'message' => 'Device template PIN must match member ID',
            ], 422);
        }

        /** @var GymMember|null $gymMember */
        $gymMember = GymMember::query()->find($gymMemberId);

        // Consent check — deny if absent
        if (! $gymMember || ! $gymMember->hasActiveBiometricConsent()) {
            return response()->json([
                'success' => false,
                'error' => 'Member has not provided biometric consent',
            ], 422);
        }

        BiometricTemplate::query()->updateOrCreate([
            'gym_member_id' => $gymMemberId,
            'gym_id' => $device->gym_id,
            'biometric_type' => $validated['biometric_type'],
            'enrolled_by_device_id' => $device->id,
        ], [
            'provider_type' => $device->provider_type ? $device->provider_type->value : 'device_native',
            'template_ref' => $templateRef,
            'quality_score' => $validated['quality_score'] ?? null,
            'is_active' => true,
            'enrolled_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function deleteTemplate(Request $request, BiometricTemplate $template): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        // Verify template belongs to this device's gym
        if ($template->gym_id !== $device->gym_id) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Template does not belong to this device',
            ], 403);
        }

        try {
            $success = $this->deletionService->deleteSingleTemplate($template);

            if (! $success) {
                return response()->json([
                    'success' => false,
                    'error' => 'Deletion failed',
                    'message' => 'Provider-side template deletion was not confirmed.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Deletion failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
