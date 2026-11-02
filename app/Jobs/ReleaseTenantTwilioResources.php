<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WhatsAppAccountRelease;
use App\Services\TwilioAccountManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Release a tenant's Twilio resources after the tenant delete has committed.
 *
 * The intent row carries the encrypted subaccount token because the original
 * `whatsapp_accounts` row is cascade-deleted with the tenant. The release is
 * idempotent (a 404 is a success), so a retry after a partial failure finishes the
 * remaining steps. A row that never succeeds stays `failed` for the recovery sweep
 * instead of disappearing with the tenant.
 */
class ReleaseTenantTwilioResources implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $releaseId) {}

    public function handle(TwilioAccountManager $manager): void
    {
        $release = WhatsAppAccountRelease::query()->find($this->releaseId);

        if ($release === null || $release->isReleased()) {
            return;
        }

        $release->forceFill(['attempts' => $release->attempts + 1])->save();

        try {
            $result = $manager->releaseCapturedResources($release);
        } catch (Throwable $exception) {
            $this->recordFailure($release, [$exception->getMessage()]);

            throw $exception;
        }

        if (($result['success'] ?? false) !== true) {
            /** @var array<int, string> $errors */
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            $this->recordFailure($release, $errors);

            throw new RuntimeException(
                "Twilio resources for deleted gym {$release->gym_id} could not be released: "
                . (implode('; ', $errors) ?: 'the provider did not confirm success')
            );
        }

        $release->forceFill([
            'status' => WhatsAppAccountRelease::STATUS_RELEASED,
            'released_at' => now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function recordFailure(WhatsAppAccountRelease $release, array $errors): void
    {
        $release->forceFill([
            'status' => WhatsAppAccountRelease::STATUS_FAILED,
            'last_error' => Str::limit(implode('; ', $errors) ?: 'Twilio release did not confirm success.', 2000),
        ])->save();
    }
}
