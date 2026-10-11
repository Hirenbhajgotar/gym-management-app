<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Illuminate\Support\Collection;

/**
 * Single owner for demoting the templates a new enrolment replaces.
 *
 * Four call sites used to perform this write independently: enrolment initiation, the two
 * controller confirmation paths, and the sibling propagator. Three of them ran a bulk
 * `update()` that stamped `re_enrolment` on every row, so a live GDPR Art. 17 erasure that a
 * re-enrolment overrode was recorded as an ordinary supersede and lost its compliance
 * discriminator. Deriving the reason from the row's prior status here makes the paths agree
 * by construction.
 */
class BiometricTemplateSuperseder
{
    /**
     * Demote every template that a new enrolment replaces on this member, device and modality.
     *
     * @return int the number of rows superseded
     */
    public function supersedeForNewEnrolment(
        int $gymId,
        GymMember $member,
        int $deviceId,
        string $biometricType,
        ?int $exceptTemplateId = null
    ): int {
        return $this->demote(
            BiometricTemplate::query()
                ->supersedableForNewEnrolment($gymId, $member->id, $deviceId, $biometricType, $exceptTemplateId)
                ->get(),
            $member,
            ['gym_id' => $gymId, 'device_id' => $deviceId, 'biometric_type' => $biometricType]
        );
    }

    /**
     * Demote only the rows awaiting hardware erasure, leaving live sibling templates intact.
     *
     * Used by the sibling propagator, where an already-confirmed sibling row must keep
     * tracking its device rather than being demoted by the origin device's fan-out.
     *
     * @return int the number of rows superseded
     */
    public function supersedeErasureOverrides(
        int $gymId,
        GymMember $member,
        int $deviceId,
        string $biometricType
    ): int {
        return $this->demote(
            BiometricTemplate::query()
                ->where('gym_id', $gymId)
                ->where('gym_member_id', $member->id)
                ->where('enrolled_by_device_id', $deviceId)
                ->where('biometric_type', $biometricType)
                ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
                ->get(),
            $member,
            ['gym_id' => $gymId, 'device_id' => $deviceId, 'biometric_type' => $biometricType]
        );
    }

    /**
     * @param  Collection<int, BiometricTemplate>  $templates
     * @param  array{gym_id: int, device_id: int, biometric_type: string}  $context
     */
    protected function demote(Collection $templates, GymMember $member, array $context): int
    {
        if ($templates->isEmpty()) {
            return 0;
        }

        $overriddenErasureIds = [];

        foreach ($templates as $template) {
            $overrodeErasure = $template->sync_status === BiometricTemplateSyncStatusEnum::PendingDeletion;

            $template->update([
                'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
                'is_active' => false,
                'superseded_reason' => $overrodeErasure
                    ? BiometricTemplate::SUPERSEDED_BY_ERASURE_OVERRIDE
                    : BiometricTemplate::SUPERSEDED_BY_RE_ENROLMENT,
            ]);

            if ($overrodeErasure) {
                $overriddenErasureIds[] = $template->id;
            }
        }

        if ($overriddenErasureIds !== []) {
            activity('biometric')
                ->performedOn($member)
                ->withProperties([...$context, 'template_ids' => $overriddenErasureIds])
                ->log('GDPR biometric erasure request overridden by re-enrolment');
        }

        return $templates->count();
    }
}
