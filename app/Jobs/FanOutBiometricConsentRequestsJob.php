<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Helpers\WhatsAppSanitizer;
use App\Models\GymMember;
use App\Models\User;
use App\Services\Biometric\BiometricConsentEligibility;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fans a biometric consent request out to every eligible member of a gym.
 *
 * The header action used to chunk and dispatch inline inside the Livewire request,
 * so a 2,000-member gym performed 2,000 queue pushes before the modal returned
 * (SPEC constraint C4). This job moves that loop into the worker.
 *
 * Authorization is re-checked per record against GymMemberPolicy as the operator
 * who dispatched it rather than the ambient session — a worker has no
 * authenticated user, and the eligible set is re-derived here rather than trusted
 * from the dispatcher.
 *
 * That check only resolves roles once the Spatie team context is set.
 * setPermissionsTeamId() is otherwise called solely by TeamsPermission, which is
 * registered on the web middleware group and so never runs in a worker: without a
 * team id HasRoles::roles() filters model_has_roles on gym_id IS NULL, every
 * gym-scoped role becomes invisible, and enrollBiometric() denies every record —
 * so the job would log dispatched => 0 and exit successfully while the admin had
 * already been told the requests were queued.
 */
class FanOutBiometricConsentRequestsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Far shorter than the hourly send throttle on purpose: the throttle bounds
     * volume, this collapses the queue amplification of an immediate re-click,
     * and a deliberate second broadcast later must still get through.
     */
    private const UNIQUE_WINDOW = 300;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(
        public readonly int $gymId,
        public readonly ?int $actorId = null,
        public readonly bool $sendEmail = true,
        public readonly bool $sendWhatsApp = false,
        public readonly ?string $broadcastId = null,
    ) {
        $this->onQueue(config()->string('queue.notifications_queue', 'notifications'));
    }

    public function uniqueId(): string
    {
        return 'biometric_consent_fanout_' . $this->gymId . '_' . ($this->actorId ?? 'none') . '_' . today()->toDateString();
    }

    public function uniqueFor(): int
    {
        return self::UNIQUE_WINDOW;
    }

    public function handle(): void
    {
        $actor = $this->actorId === null ? null : User::query()->find($this->actorId);

        if (! $actor instanceof User) {
            Log::error('Biometric consent fan-out aborted: the dispatching operator no longer exists', [
                'gym_id' => $this->gymId,
                'actor_id' => $this->actorId,
            ]);

            return;
        }

        if ($actor->status !== UserStatus::ACTIVE) {
            Log::error('Biometric consent fan-out aborted: the dispatching operator is no longer active', [
                'gym_id' => $this->gymId,
                'actor_id' => $actor->id,
                'status' => $actor->status->value,
            ]);

            return;
        }

        $previousTeamId = getPermissionsTeamId();

        $dispatched = 0;
        $skipped = 0;

        try {
            setPermissionsTeamId($this->gymId);
            $actor->unsetRelation('roles')->unsetRelation('permissions');

            BiometricConsentEligibility::constrain(GymMember::query(), $this->gymId)
                ->chunkById(100, function ($members) use ($actor, &$dispatched, &$skipped): void {
                    foreach ($members as $member) {
                        if (! Gate::forUser($actor)->allows('enrollBiometric', $member)) {
                            $skipped++;

                            continue;
                        }

                        dispatch(new SendBiometricConsentNotificationJob(
                            gymMember: $member,
                            sendEmail: $this->sendEmail,
                            sendWhatsApp: $this->sendWhatsApp,
                        ));

                        $dispatched++;
                    }
                });
        } finally {
            setPermissionsTeamId($previousTeamId);
        }

        // An operator who reaches this action is authorized for at least one member
        // by construction, so zero dispatched against a non-zero eligible set means
        // the per-record check refused every record — a failure, not an outcome.
        $level = $dispatched === 0 && $skipped > 0 ? 'warning' : 'info';

        Log::{$level}('Biometric consent fan-out completed', [
            'broadcast_id' => $this->broadcastId,
            'gym_id' => $this->gymId,
            'actor_id' => $actor->id,
            'dispatched' => $dispatched,
            'skipped' => $skipped,
            'send_email' => $this->sendEmail,
            'send_whatsapp' => $this->sendWhatsApp,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Biometric consent fan-out failed', [
            'broadcast_id' => $this->broadcastId,
            'gym_id' => $this->gymId,
            'actor_id' => $this->actorId,
            'error' => WhatsAppSanitizer::sanitizeError((string) $exception?->getMessage()),
        ]);
    }
}
