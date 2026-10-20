<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single authority for biometric de-provisioning of a template.
 *
 * Every erasure decision is made here — the GDPR deletion service, the archive service, the
 * hourly `biometrics:reconcile-pending-deletions` reconciler and the expired-member cleanup all
 * route through this class (or the reconciler re-selects the rows this class parks) — and it owns
 * the whole dispatch: cloud/provider-backed
 * rows are erased through the provider, hardware-backed rows through the terminal. That is
 * deliberate. When the routing lived in the callers, the archive service and the sweep sent
 * every row down the hardware path, so a cloud credential could be parked behind a
 * `user_delete` and then falsely converged to `Deleted` by a terminal ACK.
 *
 * ADMS exposes only `DATA DELETE user Pin=N`, which erases the whole hardware credential for a
 * PIN. A member holding both a fingerprint and a face template on one device shares a single
 * PIN and therefore a single hardware credential, so template-scoped erasure is not
 * expressible and neither is a template-scoped idempotency key. `Deleted` is written in
 * exactly two circumstances: the template provably never reached the hardware, or a terminal
 * (or provider) confirmed the delete. Every other outcome leaves the obligation outstanding in
 * a state the reconciler revisits.
 */
class BiometricDeProvisioner
{
    public function __construct(
        private BiometricProviderRegistry $registry,
    ) {}

    /**
     * Request erasure of a template, wherever its credential actually lives.
     *
     * A cloud/provider-backed row is erased through the provider API, which is
     * authoritative for it; a hardware-backed row is driven through the terminal. The
     * caller never has to know which, which is what stops the two paths disagreeing.
     */
    public function deprovision(BiometricTemplate $template): BiometricDeProvisionOutcome
    {
        if ($template->hasCloudProvider()) {
            return $this->deprovisionCloudTemplate($template);
        }

        // No device bound this template, so no terminal can hold a credential for it and
        // the hardware branch has nothing outstanding. A cloud-backed row never reaches
        // this branch — it was dispatched above — so this converges to Deleted rather than
        // claiming a deletion nothing can confirm. `NotDelivered` is the honest outcome:
        // there was never a credential on hardware to erase.
        if ($template->enrolled_by_device_id === null) {
            $template->update([
                'is_active' => false,
                'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
            ]);

            return BiometricDeProvisionOutcome::NotDelivered;
        }

        $pin = $template->resolvedCommandPin();

        // Capture delivery BEFORE cancelling in-flight commands. A `SENT` `user_add` means the
        // terminal has dequeued the profile and may already have applied it, so the credential
        // may exist even though no acknowledgement was recorded. Cancelling first downgrades
        // that row to `CANCELLED`, after which the delivery check can no longer see it — the
        // write-off below would then mark a live credential `Deleted` with no erase queued.
        $possiblyDelivered = $template->wasDeliveredToHardware();

        // Cancel first: a queued or in-flight user_add would otherwise be delivered after the
        // user_delete and re-create the credential the erasure is trying to remove.
        $cancelled = AdmsDeviceCommand::cancelInFlightFor($template->enrolled_by_device_id, $pin);

        if ($cancelled > 0) {
            Log::info('BiometricDeProvisioner: cancelled in-flight user_add for this PIN', [
                'template_id' => $template->id,
                'device_id' => $template->enrolled_by_device_id,
                'cancelled_count' => $cancelled,
            ]);
        }

        if ($template->sync_status === BiometricTemplateSyncStatusEnum::Pending
            && ! $possiblyDelivered) {
            $template->update([
                'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
                'is_active' => false,
            ]);

            return BiometricDeProvisionOutcome::NotDelivered;
        }

        if ($template->hasActiveSiblingOnSameDevice()) {
            Log::info('BiometricDeProvisioner: hardware credential retained for a sibling template on the same device', [
                'template_id' => $template->id,
                'gym_id' => $template->gym_id,
                'member_id' => $template->gym_member_id,
                'device_id' => $template->enrolled_by_device_id,
            ]);

            $this->parkAsPendingDeletion($template);

            return BiometricDeProvisionOutcome::DeferredToSibling;
        }

        // Resolve the device explicitly instead of reading the relation lazily. This class is
        // the single erase authority and is reached from paths that do not eager-load (the
        // panel action, the device `deleteTemplate()` endpoint, the archive service), and a
        // lazy load there throws under `Model::preventLazyLoading()` outside production — which
        // is how a hardware credential silently survived an archive whose caller forgot the
        // eager load. `loadMissing()` is a no-op when the caller already loaded it.
        $template->loadMissing('enrolledByDevice');

        $device = $template->enrolledByDevice;

        // The template and the device must belong to the same gym, otherwise the queued
        // command would be attributed to the wrong tenant.
        if ($device === null || $device->gym_id !== $template->gym_id) {
            Log::warning('BiometricDeProvisioner: device record missing or belongs to another gym; hardware clearance cannot be verified', [
                'template_id' => $template->id,
                'gym_id' => $template->gym_id,
                'enrolled_by_device_id' => $template->enrolled_by_device_id,
                'device_gym_id' => $device?->gym_id,
            ]);

            $this->parkAsPendingDeletion($template);

            return BiometricDeProvisionOutcome::DeviceUnavailable;
        }

        $this->parkAsPendingDeletion($template);

        if (! $device->adms_enabled || $device->status !== BiometricDeviceStatusEnum::Active) {
            Log::info('BiometricDeProvisioner: device not ADMS-enabled or not active; awaiting terminal online', [
                'template_id' => $template->id,
                'device_id' => $device->id,
                'status' => $device->status->value,
            ]);

            return BiometricDeProvisionOutcome::DeviceUnavailable;
        }

        return $this->ensureUserDeleteCommand($template, $device->id, $pin);
    }

    /**
     * Erase a provider-backed credential through its provider API.
     *
     * A row with no provider reference cannot be erased through the API. Reporting success
     * would claim a live credential is gone when nothing confirmed it; parking it at
     * `PendingDeletion` re-selects it every hour forever. It is instead moved to the terminal
     * `DeletionUnresolvable` state — excluded from the reconciler, surfaced to operators — and
     * logged once, at the transition, rather than on every sweep.
     */
    protected function deprovisionCloudTemplate(BiometricTemplate $template): BiometricDeProvisionOutcome
    {
        if (blank($template->template_ref)) {
            $template->update([
                'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
                'is_active' => false,
            ]);

            Log::error('BiometricDeProvisioner: cloud template has no provider reference; erasure needs an operator', [
                'template_id' => $template->id,
                'gym_id' => $template->gym_id,
                'member_id' => $template->gym_member_id,
                'provider' => $template->provider_type,
            ]);

            return BiometricDeProvisionOutcome::CloudUnresolvable;
        }

        $templateRef = $this->resolveTemplateRef($template);

        try {
            $result = $this->registry->resolve($template->provider_type)->deleteTemplate($templateRef);
        } catch (Throwable $e) {
            Log::warning('BiometricDeProvisioner: provider throw while erasing a cloud template; obligation stays retryable', [
                'template_id' => $template->id,
                'provider' => $template->provider_type,
                'error_type' => $e::class,
            ]);

            return $this->parkCloudRetry($template);
        }

        if ($result !== true) {
            Log::warning('BiometricDeProvisioner: provider did not confirm template deletion', [
                'template_id' => $template->id,
                'external_id' => BiometricLogRedactor::mask($template->template_ref),
                'provider' => $template->provider_type,
            ]);

            return $this->parkCloudRetry($template);
        }

        $template->forceFill([
            'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
            'is_active' => false,
            'erasure_attempts' => 0,
            'erasure_first_attempted_at' => null,
        ])->save();

        return BiometricDeProvisionOutcome::CloudErased;
    }

    /**
     * Park a cloud obligation the provider declined, capping the provider retries.
     *
     * A transient provider outage must stay retryable, so the row returns to `PendingDeletion`
     * for the hourly reconciler. A permanently-refusing provider — a stale reference, a revoked
     * API key — would then be re-selected every hour forever, and because
     * `deleteForGymMember()` throws on a retryable failure, that loop also gated the member's
     * own erasure indefinitely. The budget gives the permanent case a terminal
     * `DeletionUnresolvable` state (excluded from the reconciler, surfaced to operators) so a
     * row the system can never erase stops looping and stops blocking an erasure it can complete.
     *
     * The budget is measured from `erasure_first_attempted_at`, i.e. in TIME, not in retries.
     * One obligation is shared by callers with wildly different cadences: the hourly reconciler
     * and the consent-revoked queue listener (`$tries = 3`, 60/180/300s backoff). Counting
     * retries let the listener spend the entire budget inside four minutes, so a transient
     * provider blip drove the *dominant* Art. 17 path terminal, completed the local erasure and
     * armed no automated re-arm. The cooldown makes a burst of retries unable to exhaust it: the
     * row only terminals once the provider has been failing for `cloud_erasure_cooldown_hours`.
     * `erasure_attempts` is kept as the observability counter (incremented atomically, so two
     * concurrent writers cannot under-count). `erasure_first_attempted_at` is stamped by a
     * guarded UPDATE that only writes when it is still null, so the bound is the database's
     * earliest observation and a stale model instance cannot move it forward.
     */
    protected function parkCloudRetry(BiometricTemplate $template): BiometricDeProvisionOutcome
    {
        $cooldownHours = $this->cloudErasureCooldownHours();

        // Stamp the bound monotonically in SQL: only the first writer's value survives, so the
        // clock is the database's earliest observation, not the clock of whichever (possibly
        // stale) model instance read the row last. A PHP read-modify-write here could move the
        // bound forward and undo an operator's "Retry Erasure" reset.
        BiometricTemplate::query()
            ->whereKey($template->getKey())
            ->whereNull('erasure_first_attempted_at')
            ->update(['erasure_first_attempted_at' => now()]);

        // Measure the budget from the row's actual bound, not the (possibly stale) instance's:
        // an operator's "Retry Erasure" reset nulls the column while an in-flight instance
        // still holds the pre-reset stamp, and measuring from that stale model would
        // re-terminalise the row on the first failure after the reset.
        $firstAttemptedAt = $template->refresh()->erasure_first_attempted_at ?? now();

        $budgetExhausted = $firstAttemptedAt->lte(now()->subHours($cooldownHours));

        // The counter and the parked status ride one atomic UPDATE, so concurrent writers
        // cannot under-count the attempts.
        $template->increment('erasure_attempts', 1, [
            'sync_status' => $budgetExhausted
                ? BiometricTemplateSyncStatusEnum::DeletionUnresolvable
                : BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        // `increment()` fires `updated` but not `saved`, and the model's `saved` hook is what
        // clears the template-ref cache. Clearing it here is an optimisation —
        // `findByTemplateRef()` self-heals on the next read — not a correctness requirement.
        $template->clearTemplateRefCache();

        if ($budgetExhausted) {
            Log::error('BiometricDeProvisioner: cloud erasure budget exhausted; erasure needs an operator', [
                'template_id' => $template->id,
                'gym_id' => $template->gym_id,
                'member_id' => $template->gym_member_id,
                'provider' => $template->provider_type,
                'erasure_first_attempted_at' => $firstAttemptedAt->toIso8601String(),
                'cooldown_hours' => $cooldownHours,
                'erasure_attempts' => $template->erasure_attempts,
            ]);

            return BiometricDeProvisionOutcome::CloudUnresolvable;
        }

        Log::warning('BiometricDeProvisioner: provider did not confirm the erase; obligation stays retryable', [
            'template_id' => $template->id,
            'gym_id' => $template->gym_id,
            'provider' => $template->provider_type,
            'erasure_first_attempted_at' => $firstAttemptedAt->toIso8601String(),
            'cooldown_hours' => $cooldownHours,
            'erasure_attempts' => $template->erasure_attempts,
        ]);

        return BiometricDeProvisionOutcome::CloudRetry;
    }

    /**
     * Hours a cloud provider may keep refusing an erase before the row goes terminal.
     *
     * Floored at one hour, because a zero/negative value would make the very first failure
     * terminal and re-introduce the transient-as-permanent conflation the cooldown exists to
     * remove.
     */
    protected function cloudErasureCooldownHours(): int
    {
        return max(1, config()->integer('biometrics.cloud_erasure_cooldown_hours', 24));
    }

    /**
     * Ensure exactly one live `user_delete` command exists for this PIN on this device.
     *
     * The idempotency key is PIN-scoped because the command is PIN-scoped: the UNIQUE index on
     * `idempotency_key` is what actually enforces the invariant, and a template id in the key
     * would let two templates sharing a PIN each insert their own command.
     *
     * The key represents the *effective in-flight* command, not the historical one. A member
     * who is archived, restored and re-enrolled holds a fresh hardware credential for the same
     * PIN, so the previous `user_delete` — already acknowledged — must be re-driven in place
     * rather than counted as a duplicate. Ignoring the terminal row is what let an ordinary
     * archive → restore → re-enrol → archive cycle leave a live credential behind forever: the
     * UNIQUE index rejected every new insert and the ACKNOWLEDGED row held the key, so nothing
     * could ever be queued again.
     *
     * "New epoch or the same obligation?" is therefore decided BEFORE the exhausted
     * short-circuit, from an explicit fact rather than a status bit: a completed erasure
     * (`ACKNOWLEDGED`) or a `user_add` the terminal has accepted that this delete has not
     * already credited (`epoch_credential_user_add_id`) proves a fresh credential exists.
     * The credit is consumed by the re-drive that records it, so the same acknowledgement
     * cannot start a second epoch — the row's `created_at` never advances, and a timestamp
     * anchor alone would satisfy every later re-drive after one re-enrolment. Deciding the
     * epoch after the short-circuit made a `FAILED`-at-cap row re-driven against a credential
     * that did not exist when it failed — archive, unarchive, re-enrol, archive left the new
     * credential live with no automated path and no operator surface on the template. A
     * `FAILED` row with no unconsumed credential is the *same* obligation, so its delivery
     * counter is preserved: resetting it would make the cap, the `Exhausted` outcome and the
     * dead-letter signal unreachable for a terminal that answers and rejects, turning a
     * bounded and alerted failure into an unbounded hourly hardware write.
     */
    protected function ensureUserDeleteCommand(BiometricTemplate $template, int $deviceId, string $pin): BiometricDeProvisionOutcome
    {
        $hasLiveCommand = AdmsDeviceCommand::query()
            ->where('device_id', $deviceId)
            ->where('command_type', 'user_delete')
            ->where('command_payload->Pin', $pin)
            ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
            ->exists();

        if ($hasLiveCommand) {
            return BiometricDeProvisionOutcome::AlreadyQueued;
        }

        $deleteKey = AdmsCommandKey::buildUserDelete($deviceId, $pin);

        $existing = AdmsDeviceCommand::query()
            ->where('device_id', $deviceId)
            ->where('idempotency_key', $deleteKey)
            ->first();

        if ($existing !== null) {
            $previousStatus = $existing->status;

            $epochUserAddId = $this->newestEpochCredentialUserAddId($deviceId, $pin, $existing);
            $startsNewEpoch = $previousStatus === AdmsCommandStatusEnum::ACKNOWLEDGED
                || $epochUserAddId !== null;

            if (! $startsNewEpoch
                && $previousStatus === AdmsCommandStatusEnum::FAILED
                && $existing->attempt_count >= AdmsCommandKey::maxAttempts()) {
                Log::error('BiometricDeProvisioner: hardware erase delivery budget exhausted; manual intervention required', [
                    'template_id' => $template->id,
                    'gym_id' => $template->gym_id,
                    'member_id' => $template->gym_member_id,
                    'device_id' => $deviceId,
                    'command_id' => $existing->id,
                    'attempt_count' => $existing->attempt_count,
                    'attempt_cap' => AdmsCommandKey::maxAttempts(),
                    // Device-controlled input: keep it structured, never interpolated into a
                    // log message or rendered as markup.
                    'device_return_code' => $existing->device_return_code,
                ]);

                return BiometricDeProvisionOutcome::Exhausted;
            }

            // Only a discharged obligation starts a fresh budget: an `ACKNOWLEDGED` row, or a
            // delete with an unconsumed `user_add` proving its PIN was re-enrolled since the
            // last failure, means the previous credential is gone. Anything else is the same
            // obligation and keeps its counter.
            $attemptCount = $startsNewEpoch ? 0 : $existing->attempt_count;

            // Compare-and-set on a terminal row: a concurrent lease between the read above and
            // this write would otherwise be regressed to PENDING and delivered twice.
            //
            // The `$affected === 0` arm is defensive. `$hasLiveCommand` above already refused a
            // row that was live at read time, so the arm can only fire for a row that went live
            // in the window between that read and this update — a race no test can schedule. It
            // is kept because the window is real under concurrency, and the cost of being wrong
            // is a duplicate hardware delivery.
            $affected = AdmsDeviceCommand::query()
                ->whereKey($existing->getKey())
                ->where('device_id', $deviceId)
                ->whereNotIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
                ->update([
                    'status' => AdmsCommandStatusEnum::PENDING,
                    'sent_at' => null,
                    'acknowledged_at' => null,
                    'leased_at' => null,
                    'expires_at' => null,
                    'device_return_code' => null,
                    'attempt_count' => $attemptCount,
                    'epoch_credential_user_add_id' => $epochUserAddId ?? $existing->epoch_credential_user_add_id,
                ]);

            if ($affected === 0) {
                // Someone moved the row back into flight between the read and the CAS; it is
                // live, so re-driving it here would duplicate the delivery.
                return BiometricDeProvisionOutcome::AlreadyQueued;
            }

            Log::info('BiometricDeProvisioner: re-drove a terminal user_delete for a renewed PIN', [
                'template_id' => $template->id,
                'device_id' => $deviceId,
                'command_id' => $existing->id,
                'previous_status' => $previousStatus->value,
                'starts_new_epoch' => $startsNewEpoch,
                'attempt_count' => $attemptCount,
            ]);

            return BiometricDeProvisionOutcome::ReDriven;
        }

        try {
            AdmsDeviceCommand::query()->create([
                'gym_id' => $template->gym_id,
                'device_id' => $deviceId,
                'command_type' => 'user_delete',
                'command_payload' => ['Pin' => $pin],
                'idempotency_key' => $deleteKey,
                'status' => AdmsCommandStatusEnum::PENDING,
            ]);

            return BiometricDeProvisionOutcome::Queued;
        } catch (UniqueConstraintViolationException) {
            Log::warning('BiometricDeProvisioner: duplicate delete command collision', [
                'template_id' => $template->id,
                'device_id' => $deviceId,
                'idempotency_key' => $deleteKey,
            ]);

            return BiometricDeProvisionOutcome::AlreadyQueued;
        }
    }

    /**
     * The newest `user_add` that proves the terminal holds a credential this delete does not
     * account for, or null when there is none.
     *
     * The epoch question — "is this the same erasure obligation, or a fresh credential?" — has
     * to be an explicit fact rather than inferred from the delete row's status. A
     * `FAILED`-at-cap delete whose PIN has since been re-enrolled is a new obligation on the
     * same PIN (whose delete key is therefore the same), and must be re-driven; without this
     * check, archive → restore → re-enrol → archive leaves the new credential live with no
     * automated path and no operator surface on the template.
     *
     * The delete row is re-driven in place, so its `created_at` never advances, and a single
     * re-enrolment's `user_add` satisfied a `created_at` comparison for the row's whole life.
     * The matched command id is therefore consumed exactly once: once
     * `epoch_credential_user_add_id` is recorded, only a strictly newer id can start another
     * epoch. Ids are monotonic, so a second-precision timestamp tie cannot hide a new
     * credential.
     *
     * Until the first epoch is credited, the anchor is the delete's last delivery OUTCOME
     * rather than its creation: the rejection acknowledgement for a row the terminal answered
     * (`acknowledged_at`), the last send for a lease that timed out at the cap (`sent_at`), or
     * `created_at` as the fallback when neither exists. The original credential's `user_add`
     * was either cancelled at archive time by
     * {@see AdmsDeviceCommand::cancelInFlightFor()} or acknowledged before that failure.
     *
     * A `SENT` `user_add` counts: the terminal has dequeued the profile and may already hold
     * the credential even though no acknowledgement was recorded — the same rule
     * {@see BiometricTemplate::wasDeliveredToHardware()} uses.
     */
    protected function newestEpochCredentialUserAddId(int $deviceId, string $pin, AdmsDeviceCommand $deleteCommand): ?int
    {
        $query = AdmsDeviceCommand::query()
            ->where('gym_id', $deleteCommand->gym_id)
            ->where('device_id', $deviceId)
            ->where('command_type', 'user_add')
            ->where('command_payload->Pin', $pin)
            ->whereIn('status', [AdmsCommandStatusEnum::SENT, AdmsCommandStatusEnum::ACKNOWLEDGED]);

        if ($deleteCommand->epoch_credential_user_add_id !== null) {
            $maxId = $query->where('id', '>', $deleteCommand->epoch_credential_user_add_id)->max('id');

            return is_numeric($maxId) ? (int) $maxId : null;
        }

        $since = $deleteCommand->acknowledged_at ?? $deleteCommand->sent_at ?? $deleteCommand->created_at;

        if ($since === null) {
            return null;
        }

        $maxId = $query
            ->where(function (Builder $query) use ($since): void {
                $query->where('acknowledged_at', '>', $since)
                    ->orWhere(function (Builder $sub) use ($since): void {
                        $sub->whereNull('acknowledged_at')
                            ->where('sent_at', '>', $since);
                    });
            })
            ->max('id');

        return is_numeric($maxId) ? (int) $maxId : null;
    }

    /**
     * True when the terminal has accepted a credential the last consumed epoch did not
     * account for — a fresh obligation on a PIN whose previous `user_delete` was
     * dead-lettered. The reconciler asks this BEFORE its cooldown short-circuit so a
     * re-enrolment is re-driven promptly instead of waiting out the dead-letter window.
     *
     * The delete lookup is scoped by `gym_id` as well as device: device ids are not
     * guaranteed globally unique across a restored or sharded database, and a cross-tenant
     * command must never re-drive this gym's erasure.
     */
    public function hasUnconsumedHardwareCredential(int $deviceId, string $pin, int $gymId): bool
    {
        $delete = AdmsDeviceCommand::query()
            ->where('gym_id', $gymId)
            ->where('device_id', $deviceId)
            ->where('idempotency_key', AdmsCommandKey::buildUserDelete($deviceId, $pin))
            ->first();

        return $delete !== null
            && $this->newestEpochCredentialUserAddId($deviceId, $pin, $delete) !== null;
    }

    /**
     * Safely resolve a TemplateRef DTO from a template's stored reference.
     */
    protected function resolveTemplateRef(BiometricTemplate $template): TemplateRef
    {
        $ref = (string) $template->template_ref;

        if (str_starts_with($ref, '{') && str_ends_with($ref, '}')) {
            try {
                return TemplateRef::fromJson($ref);
            } catch (Throwable $e) {
                Log::warning('BiometricDeProvisioner: failed to parse JSON template ref; falling back to raw ref', [
                    'template_id' => $template->id,
                    'error_type' => $e::class,
                ]);
            }
        }

        return new TemplateRef(
            provider: $template->provider_type,
            externalId: $ref,
            meta: []
        );
    }

    protected function parkAsPendingDeletion(BiometricTemplate $template): void
    {
        $template->update([
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);
    }
}
