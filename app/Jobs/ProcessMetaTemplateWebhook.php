<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Notifications\WhatsAppTemplateApprovedNotification;
use App\Notifications\WhatsAppTemplateRejectedNotification;
use App\Notifications\WhatsAppTemplateStatusChangedNotification;
use App\Services\Support\WhatsAppTemplateStatusMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Processes Meta template status webhook events asynchronously.
 *
 * This job handles incoming template status updates from Meta's WhatsApp
 * Business API, updating the database in real-time. Implements ShouldBeUnique
 * to prevent concurrent processing of the same template.
 */
class ProcessMetaTemplateWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $wabaId,
        public string $metaTemplateId,
        public string $event,
        public ?string $reason = null,
        public ?string $previousEvent = null
    ) {}

    /**
     * Unique job key based on meta_template_id.
     * Prevents concurrent processing of the same template.
     */
    public function uniqueId(): string
    {
        return $this->metaTemplateId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $account = Cache::flexible("whatsapp:template:account:waba_id:{$this->wabaId}", [30, 120], function () {
            return WhatsAppAccount::query()->where('waba_id', $this->wabaId)->first();
        });

        if (! $account) {
            Log::warning('ProcessMetaTemplateWebhook: account not found', [
                'waba_id' => $this->wabaId,
            ]);

            return;
        }
        // Find the template by Meta's template ID
        $template = Cache::flexible("whatsapp:template:gym_id:{$account->gym_id}:template_id:{$this->metaTemplateId}", [30, 120], function () use ($account) {
            return WhatsAppTemplate::query()->where('gym_id', $account->gym_id)->where('meta_template_id', $this->metaTemplateId)->first();
        });

        if (! $template) {
            Log::warning('ProcessMetaTemplateWebhook: template not found', [
                'meta_template_id' => $this->metaTemplateId,
                'gym_id' => $account->gym_id,
            ]);

            return;
        }

        // Map Meta event to WhatsAppTemplateStatusEnum
        $newStatus = WhatsAppTemplateStatusMapper::mapStatus($this->event);

        if (! $newStatus) {
            Log::warning('ProcessMetaTemplateWebhook: unknown event type', [
                'meta_template_id' => $this->metaTemplateId,
                'event' => $this->event,
            ]);

            return;
        }

        // Skip if status hasn't changed
        if ($template->status === $newStatus) {
            Log::info('ProcessMetaTemplateWebhook: status unchanged, skipping', [
                'meta_template_id' => $this->metaTemplateId,
                'current_status' => $template->status->value,
                'new_status' => $newStatus->value,
            ]);

            return;
        }

        // Update the template
        $oldStatus = $template->status->value ?? 'none';
        $template->status = $newStatus;

        // Set rejection reason for REJECTED or FLAGGED events
        if (in_array($this->event, ['REJECTED', 'FLAGGED']) && $this->reason) {
            $template->rejection_reason = $this->reason;
        } else {
            $template->rejection_reason = null;
        }

        $template->save();

        Log::info('ProcessMetaTemplateWebhook: template status updated', [
            'template_id' => $template->id,
            'meta_template_id' => $this->metaTemplateId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus->value,
        ]);

        // Send notification to gym owner
        $this->sendStatusNotification($template, $newStatus, $oldStatus);
    }

    /**
     * Send appropriate notification based on template status change.
     */
    private function sendStatusNotification(WhatsAppTemplate $template, WhatsAppTemplateStatusEnum $newStatus, string $oldStatus): void
    {
        // Skip notification for templates without a gym (super-admin templates)
        if (! $template->gym_id) {
            return;
        }

        $template->loadMissing('gym');

        $owner = $template->gym?->owner();

        if (! $owner) {
            Log::warning('ProcessMetaTemplateWebhook: gym owner not found for notification', [
                'template_id' => $template->id,
                'gym_id' => $template->gym_id,
            ]);

            return;
        }

        try {
            match ($newStatus) {
                WhatsAppTemplateStatusEnum::APPROVED => $owner->notify(new WhatsAppTemplateApprovedNotification($template)),
                WhatsAppTemplateStatusEnum::REJECTED => $owner->notify(new WhatsAppTemplateRejectedNotification($template)),
                default => $owner->notify(new WhatsAppTemplateStatusChangedNotification($template, $newStatus, $oldStatus)),
            };

            Log::info('ProcessMetaTemplateWebhook: status notification sent', [
                'template_id' => $template->id,
                'new_status' => $newStatus->value,
                'notified_user_id' => $owner->id,
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the job - notification failure shouldn't block status updates
            Log::error('ProcessMetaTemplateWebhook: notification failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessMetaTemplateWebhook: job failed', [
            'meta_template_id' => $this->metaTemplateId,
            'event' => $this->event,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);
    }
}
