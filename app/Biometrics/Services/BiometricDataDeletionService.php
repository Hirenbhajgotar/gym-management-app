<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\BiometricConsent;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BiometricDataDeletionService
{
    public function __construct(
        private BiometricDeProvisioner $deProvisioner
    ) {}

    /**
     * Delete all biometric data for a gym member (GDPR compliance)
     *
     * This method is idempotent and resilient:
     * - Skips already inactive and fully deleted templates
     * - Catches exceptions per template and continues processing
     * - Throws an aggregated exception after the loop when a deletion is genuinely retryable
     *
     * For hardware-backed templates, success means the erasure obligation is durably
     * recorded, not that a terminal has confirmed removal. Templates whose hardware
     * erasure is still pending are reported in the activity log properties.
     *
     * The anonymise/delete-consent step is conditional on the *outcome*, not unconditional:
     *
     * - A **retryable** failure (a provider that may confirm on the next run) is accumulated and
     *   rethrown, so nothing is anonymised, consent is untouched, and the listener retries.
     * - A **terminal** failure — a cloud row with no provider reference (or one whose reference
     *   cannot even be read), or one whose provider has exhausted its erasure budget — does not
     *   throw. It is reported as a residual and the anonymise/delete-consent step still runs,
     *   because a row the system can never act on must not gate an erasure the application can
     *   complete.
     * - `$finalAttempt` is the queue's own bound, and it must not become an erasure bound either.
     *   When the retry budget is spent, the member's erasure still completes and the outstanding
     *   obligations are left for the hourly reconciler — parked at `PendingDeletion` while the
     *   provider cooldown has not elapsed, or at the terminal `DeletionUnresolvable` once it has
     *   — rather than the job being discarded with the anonymise step never run.
     *
     * Note: individual template deletions are processed independently and not wrapped in a
     * single encompassing DB transaction. That is deliberate: it prevents long-lived database
     * locks and lock-wait timeouts while external network I/O calls to cloud biometric providers
     * (e.g. AWS Rekognition, Azure Face) are in flight.
     *
     * @param  bool  $finalAttempt  the caller has no retries left; complete the member's erasure
     *                              even while template obligations remain outstanding
     *
     * @throws RuntimeException when a retryable template deletion fails and retries remain
     */
    public function deleteForGymMember(GymMember $gymMember, bool $finalAttempt = false): void
    {
        $templates = BiometricTemplate::query()
            ->with(['enrolledByDevice'])
            ->where('gym_member_id', $gymMember->id)
            ->get();

        $errors = [];
        $pendingHardwareErasures = [];
        $retryableErasures = [];
        $unresolvableErasures = [];
        $exceptionErasures = [];

        foreach ($templates as $template) {
            if (! $template->is_active && $template->sync_status === BiometricTemplateSyncStatusEnum::Deleted) {
                continue;
            }

            // A row already at the terminal `DeletionUnresolvable` state has two producers, and
            // only one of them is beyond this service. A cloud row with a blank — or unreadable
            // — `template_ref` has nothing left to erase with, so re-driving it would only
            // re-log the same error on every erasure request. A reference-present row whose
            // provider outlasted the cooldown still holds its reference and may still hold the
            // credential, so a fresh explicit erasure request — a new member Art. 17 call or a
            // later consent revocation — must re-attempt it rather than trust the cooldown
            // transitively.
            if ($template->sync_status === BiometricTemplateSyncStatusEnum::DeletionUnresolvable) {
                try {
                    $referenceIsBlank = blank($template->template_ref);
                } catch (Throwable $e) {
                    // The encrypted cast throws on read (a rotated APP_KEY, a corrupted row).
                    // One unreadable row must not abort the whole erasure: the remaining
                    // templates and the member's own anonymise/consent step still run.
                    $unresolvableErasures[] = [
                        'template_id' => $template->id,
                        'provider' => $template->provider_type,
                        'error_type' => $e::class,
                    ];

                    Log::warning('Biometric erasure de-provisioning skipped: the template reference could not be read', [
                        'template_id' => $template->id,
                        'error_type' => $e::class,
                    ]);

                    continue;
                }

                if ($referenceIsBlank) {
                    $unresolvableErasures[] = [
                        'template_id' => $template->id,
                        'provider' => $template->provider_type,
                    ];

                    continue;
                }
            }

            try {
                $success = $this->deleteSingleTemplate($template);

                if (! $success) {
                    $retryableErasures[] = [
                        'template_id' => $template->id,
                        'device_id' => $template->enrolled_by_device_id,
                    ];

                    $errors[] = [
                        'template_id' => $template->id,
                        'external_id' => BiometricLogRedactor::mask($template->template_ref),
                        'provider' => $template->provider_type,
                        'error' => 'Provider did not confirm template deletion',
                    ];

                    continue;
                }

                $outcomeStatus = $template->refresh()->sync_status;

                if ($outcomeStatus === BiometricTemplateSyncStatusEnum::PendingDeletion) {
                    $pendingHardwareErasures[] = [
                        'template_id' => $template->id,
                        'device_id' => $template->enrolled_by_device_id,
                    ];
                } elseif ($outcomeStatus === BiometricTemplateSyncStatusEnum::DeletionUnresolvable) {
                    $unresolvableErasures[] = [
                        'template_id' => $template->id,
                        'provider' => $template->provider_type,
                    ];
                }
            } catch (Throwable $e) {
                $exceptionErasures[] = [
                    'template_id' => $template->id,
                    'provider' => $template->provider_type,
                    'error_type' => $e::class,
                ];

                $errors[] = [
                    'template_id' => $template->id,
                    'external_id' => BiometricLogRedactor::mask($template->template_ref),
                    'provider' => $template->provider_type,
                    'error' => 'deprovisioning_failed: ' . $e::class,
                ];

                Log::warning('Biometric erasure de-provisioning failed for template', [
                    'template_id' => $template->id,
                    'error_type' => $e::class,
                ]);
            }
        }

        if (filled($errors)) {
            if (! $finalAttempt) {
                throw new RuntimeException(
                    'Biometric data deletion failed for ' . count($errors) . ' template(s). See logs for details.',
                    0
                );
            }

            // Retries are spent. The member's own erasure must not be abandoned with the job:
            // the outstanding rows are left to the hourly reconciler, parked at
            // `PendingDeletion` while the cooldown has not elapsed and at the terminal
            // `DeletionUnresolvable` once it has.
            Log::error('Biometric data deletion: queue retries exhausted with erasures outstanding; completing the member\'s own erasure and leaving the obligations to the reconciler', [
                'gym_member_id' => $gymMember->id,
                'outstanding_erasure_count' => count($errors),
                'outstanding' => $errors,
            ]);
        }

        DB::transaction(function () use ($gymMember): void {
            $gymMember->attendances()
                ->biometric()
                ->update([
                    'check_in_method' => 'anonymized',
                    'biometric_type' => null,
                    'provider_type' => null,
                    'confidence_score' => null,
                    'liveness_score' => null,
                    'fusion_score' => null,
                    'check_in_result' => null,
                    'device_id' => null,
                ]);

            BiometricConsent::query()->where('gym_member_id', $gymMember->id)->delete();
        });

        activity('biometric')
            ->performedOn($gymMember)
            ->withProperties([
                'gym_member_id' => $gymMember->id,
                'pending_hardware_erasures' => $pendingHardwareErasures,
                'retryable_erasures' => $retryableErasures,
                'unresolvable_erasures' => $unresolvableErasures,
                'exception_erasures' => $exceptionErasures,
            ])
            ->log(match (true) {
                $exceptionErasures !== [] => 'Biometric data deletion completed per GDPR request; some erasures failed with an unexpected error and remain outstanding for the reconciler',
                $unresolvableErasures !== [] => 'Biometric data deletion completed per GDPR request; some erasures are unresolvable and require an operator',
                $pendingHardwareErasures !== [] => 'Biometric data deletion requested per GDPR request; hardware erasure pending terminal confirmation',
                $retryableErasures !== [] => 'Biometric data deletion completed per GDPR request; provider erasures remain outstanding for the reconciler',
                default => 'Biometric data deletion completed per GDPR request',
            });
    }

    /**
     * Revoke consent and optionally delete biometric data
     */
    public function revokeConsent(GymMember $gymMember, bool $deleteData = false): void
    {
        // Revoke all consents
        BiometricConsent::query()->where('gym_member_id', $gymMember->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($deleteData) {
            $this->deleteForGymMember($gymMember);
        }
    }

    /**
     * Delete an individual biometric template across cloud providers or hardware devices.
     *
     * Returns true when the erasure obligation is durably recorded — for hardware-backed
     * templates that means the request is parked at `PendingDeletion` and will be revisited
     * by the hourly reconciler; it does not mean the terminal has confirmed removal. It
     * returns false only when the obligation is still retryable (a provider that did not
     * confirm the erase and may succeed next run), which is what lets the GDPR listener
     * retry and the panel report a genuine failure.
     *
     * Every call is an explicit erasure request and records it: `erasure_requested_at` is
     * stamped once, here, when unset. This is the single chokepoint every operator- or
     * member-initiated erasure flows through — the GDPR deletion service, the panel's
     * De-provision and Retry Erasure actions and the device `deleteTemplate()` endpoint — so a
     * later re-enrolment of the same PIN is auditable as an erasure override rather than an
     * ordinary re-enrolment. Callers must not write the marker themselves: a row parked by the
     * archive service, a sweep or the reconciler goes through
     * {@see BiometricDeProvisioner} directly and deliberately carries no marker.
     *
     * Routing (cloud vs hardware) lives in {@see BiometricDeProvisioner} so this method and
     * the archive service, the sweeps and the reconciler cannot disagree about it.
     */
    public function deleteSingleTemplate(BiometricTemplate $template): bool
    {
        if (! $template->is_active && $template->sync_status === BiometricTemplateSyncStatusEnum::Deleted) {
            return true;
        }

        if ($template->erasure_requested_at === null) {
            $template->forceFill(['erasure_requested_at' => now()])->save();
        }

        $outcome = $this->deProvisioner->deprovision($template);

        return $outcome !== BiometricDeProvisionOutcome::CloudRetry;
    }
}
