<?php

namespace App\Http\Controllers\Api;

use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\BiometricSample;
use App\Enums\BiometricTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricEnrollRequest;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BiometricEnrollController extends Controller
{
    public function __construct(
        protected BiometricProviderRegistry $registry
    ) {}

    public function enroll(BiometricEnrollRequest $request): JsonResponse
    {
        /** @var BiometricDevice $device */
        $device = $request->attributes->get('device');

        // Scope member lookup to device's gym (prevents cross-gym enrollment)
        $gymMember = GymMember::where('gym_id', $device->gym_id)
            ->findOrFail($request->validated('gym_member_id'));

        // Enforce that active consent exists - do not auto-create
        if (! $gymMember->hasActiveBiometricConsent()) {
            return response()->json([
                'success' => false,
                'error' => 'Biometric consent required',
                'message' => 'Member must have an active biometric consent on file',
            ], 422);
        }

        // Get provider and enroll
        $provider = $this->registry->resolve($device->provider_type, $device->provider_config ?? []);

        $sample = new BiometricSample(
            type: $request->validated('biometric_type'),
            data: $request->validated('data'),
            metadata: $request->validated('metadata', [])
        );

        try {
            $templateRef = $provider->enroll($gymMember, $sample);

            // Create BiometricTemplate record
            $template = BiometricTemplate::create([
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

            return response()->json([
                'success' => true,
                'template_id' => $template->id,
                'quality_score' => $template->quality_score,
                'enrolled_at' => $template->enrolled_at->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Enrollment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function syncFromDevice(Request $request)
    {
        $device = $request->attributes->get('device');

        // Validate karo
        $validated = $request->validate([
            'gym_member_id' => [
                'required',
                'exists:gym_members,id',
                // Ensure member is same gym as device
                Rule::exists('gym_members', 'id')
                    ->where('gym_id', $device->gym_id),
            ],
            'biometric_type' => ['required', Rule::enum(BiometricTypeEnum::class)],
            'template_ref' => ['required', 'string', 'max:255'], // device ka internal ID
            'quality_score' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $gymMember = GymMember::find($validated['gym_member_id']);

        // Consent check — agar nahi hai toh deny karo
        if (! $gymMember->hasActiveBiometricConsent()) {
            return response()->json([
                'success' => false,
                'error' => 'Member ne biometric consent nahi diya',
            ], 422);
        }

        // Sirf record banao — koi provider call nahi
        BiometricTemplate::updateOrCreate(
            [
                'gym_member_id' => $validated['gym_member_id'],
                'gym_id' => $device->gym_id,
                'biometric_type' => $validated['biometric_type'],
            ],
            [
                'provider_type' => $device->provider_type,
                'template_ref' => $validated['template_ref'],
                'enrolled_by_device_id' => $device->id,
                'quality_score' => $validated['quality_score'] ?? null,
                'is_active' => true,
                'enrolled_at' => now(),
            ]
        );

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
            $provider = $this->registry->resolve($template->provider_type);

            $templateRef = TemplateRef::fromJson($template->template_ref);

            if (! $provider->deleteTemplate($templateRef)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Deletion failed',
                    'message' => 'Provider-side template deletion was not confirmed.',
                ], 422);
            }
            // Deactivate template (soft delete)
            $template->is_active = false;
            $template->save();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Deletion failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
