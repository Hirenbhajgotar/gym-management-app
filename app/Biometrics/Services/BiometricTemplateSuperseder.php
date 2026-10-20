<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Enums\BiometricProvenanceEnum;
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
 *
 * A supersede may only ever demote a row of the SAME provenance class as the enrolment
 * driving it, which is why every method takes the caller's `BiometricProvenanceEnum`. On
 * ADR-004's dual-mode device a hardware and a cloud credential share a PIN but are two
 * credentials, and `Superseded` is excluded from every live-credential predicate and from
 * the erasure reconciler, so demoting across classes strands a live credential that no
 * path revisits.
 *
 * The erasure-override audit discriminator deliberately reads `erasure_requested_at`, whose
 * semantics are wider than "the member filed an Art. 17 request". That marker is written by
 * {@see \App\Biometrics\Services\BiometricDataDeletionService::deleteSingleTemplate()} —
 * the single chokepoint every explicit erasure request goes through (the GDPR deletion
 * service, the operator "De-provision" action and the device `deleteTemplate()` endpoint) —
 * as opposed to the automated archive/sweep/reconciler parks, which call
 * {@see \App\Biometrics\Services\BiometricDeProvisioner::deprovision()} directly and carry no
 * marker. An operator-driven de-provision therefore reads as an overridden erasure too; that
 * is intentional, because the operator explicitly asked for that credential to be erased.
 */
class BiometricTemplateSuperseder
{
    /**
     * Demote every template that a new enrolment replaces on this member, device and modality.
     *
     * Only rows of the enrolment's own provenance class are candidates: a terminal-held
     * enrolment must not supersede the cloud row that shares its PIN (nor the reverse),
     * because `Superseded` is excluded from every live-credential predicate and would strand
     * the other class's credential.
     *
     * @return int the number of rows superseded
     */
    public function supersedeForNewEnrolment(
        int $gymId,
        GymMember $member,
        int $deviceId,
        string $biometricType,
        BiometricProvenanceEnum $provenance,
        ?int $exceptTemplateId = null
    ): int {
        return $this->demote(
            BiometricTemplate::query()
                ->supersedableForNewEnrolment($gymId, $member->id, $deviceId, $biometricType, $provenance, $exceptTemplateId)
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
     * A fan-out can only discharge a credential of its own class: it passes the origin's
     * provenance, so demoting the other class is impossible. A cross-class demotion would
     * strand a live credential in `Superseded` (excluded from every live-credential predicate
     * and the reconciler) while writing a false "GDPR biometric erasure request overridden by
     * re-enrolment" audit line for an erasure the origin never touched.
     *
     * @return int the number of rows superseded
     */
    public function supersedeErasureOverrides(
        int $gymId,
        GymMember $member,
        int $deviceId,
        string $biometricType,
        BiometricProvenanceEnum $provenance
    ): int {
        return $this->demote(
            BiometricTemplate::query()
                ->where('gym_id', $gymId)
                ->where('gym_member_id', $member->id)
                ->where('enrolled_by_device_id', $deviceId)
                ->where('biometric_type', $biometricType)
                ->where('is_erasure_obligation', false)
                ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
                ->ofProvenance($provenance)
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
            // `PendingDeletion` is the universal parked state (an archive, the sweep, the
            // reconciler), so the status alone does not mean an explicit erasure request was
            // made. Only a row the deletion service marked with `erasure_requested_at` carries
            // that obligation; a machine park is an ordinary re-enrolment supersede.
            $overrodeErasure = $template->sync_status === BiometricTemplateSyncStatusEnum::PendingDeletion
                && $template->erasure_requested_at !== null;

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
