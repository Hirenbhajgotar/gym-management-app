<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WhatsAppTemplateStatusEnum;
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
use Illuminate\Support\Facades\Log;

/**
 * Processes Twilio template status webhook events asynchronously.
 *
 * This job handles incoming template status updates from Twilio's WhatsApp
 * Content API, updating the database in real-time. Implements ShouldBeUnique
 * to prevent concurrent processing of the same template.
 */
class ProcessTwilioTemplateWebhook implements ShouldBeUnique, ShouldQueue
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
     *
     * @param  string  $contentSid  Twilio Content SID (template identifier)
     * @param  string  $status  Raw status string from Twilio
     * @param  string|null  $errorCode  Optional error code for rejected templates
     * @param  string|null  $errorMessage  Optional error message for rejected templates
     */
    public function __construct(
        public string $contentSid,
        public string $status,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    /**
     * Unique job key based on content_sid.
     * Prevents concurrent processing of the same template.
     */
    public function uniqueId(): string
    {
        return $this->contentSid;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Find the template by Twilio's Content SID
        $template = WhatsAppTemplate::where('twilio_template_sid', $this->contentSid)->first();

        if (! $template) {
            Log::warning('ProcessTwilioTemplateWebhook: template not found', [
                'content_sid' => $this->contentSid,
            ]);

            // Don't retry if template doesn't exist
            $this->delete();

            return;
        }

        // Map Twilio status to WhatsAppTemplateStatusEnum
        $newStatus = WhatsAppTemplateStatusMapper::mapStatus($this->status);

        if (! $newStatus) {
            Log::warning('ProcessTwilioTemplateWebhook: unknown status', [
                'content_sid' => $this->contentSid,
                'status' => $this->status,
            ]);

            return;
        }

        // Skip if status hasn't changed
        if ($template->status === $newStatus) {
            Log::info('ProcessTwilioTemplateWebhook: status unchanged, skipping', [
                'content_sid' => $this->contentSid,
                'current_status' => $template->status->value,
                'new_status' => $newStatus->value,
            ]);

            return;
        }

        // Update the template
        $oldStatus = $template->status->value ?? 'none';
        $template->status = $newStatus;

        // Set rejection reason for rejected templates
        if ($newStatus === WhatsAppTemplateStatusEnum::REJECTED) {
            $parts = [];
            if ($this->errorCode) {
                $parts[] = "[Error {$this->errorCode}]";
            }
            if ($this->errorMessage) {
                $parts[] = $this->errorMessage;
            }
            $template->rejection_reason = $parts !== []
                ? implode(' ', $parts)
                : 'Template rejected by Twilio';
        } else {
            $template->rejection_reason = null;
        }

        $template->save();

        Log::info('ProcessTwilioTemplateWebhook: template status updated', [
            'template_id' => $template->id,
            'content_sid' => $this->contentSid,
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
            Log::warning('ProcessTwilioTemplateWebhook: gym owner not found for notification', [
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

            Log::info('ProcessTwilioTemplateWebhook: status notification sent', [
                'template_id' => $template->id,
                'new_status' => $newStatus->value,
                'notified_user_id' => $owner->id,
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the job - notification failure shouldn't block status updates
            Log::error('ProcessTwilioTemplateWebhook: notification failed', [
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
        Log::error('ProcessTwilioTemplateWebhook: job failed', [
            'content_sid' => $this->contentSid,
            'status' => $this->status,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);
    }
}
