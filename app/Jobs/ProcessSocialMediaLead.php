<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;
use App\Services\SocialMediaLeadProcessor;
use App\Services\FacebookLeadProcessor;
use App\Services\InstagramLeadProcessor;
use App\Services\WhatsAppLeadProcessor;
use App\Services\IndiaMartLeadProcessor;
use App\Services\JustDialLeadProcessor;
use App\Events\LeadCreated;
use Throwable;

class ProcessSocialMediaLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $leadData,
        public string $platform,
        public ?string $webhookId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing social media lead', [
                'platform' => $this->platform,
                'webhook_id' => $this->webhookId,
                'lead_data' => $this->leadData,
            ]);

            // Get the appropriate processor
            $processor = $this->getProcessor($this->platform);

            if (!$processor) {
                Log::error('No processor available for platform', ['platform' => $this->platform]);
                $this->fail(new \Exception("No processor available for platform: {$this->platform}"));
                return;
            }

            // Process the lead
            $lead = $processor->processLead($this->leadData);

            if ($lead) {
                Log::info('Social media lead processed successfully', [
                    'platform' => $this->platform,
                    'lead_id' => $lead->id,
                    'webhook_id' => $this->webhookId,
                ]);

                // Dispatch event for real-time updates
                event(new LeadCreated($lead));

                // Additional processing based on platform
                $this->postProcessLead($lead, $this->platform);
            } else {
                Log::warning('Social media lead processing failed', [
                    'platform' => $this->platform,
                    'webhook_id' => $this->webhookId,
                    'lead_data' => $this->leadData,
                ]);

                // Don't fail the job if lead creation failed due to validation
                // This prevents infinite retries for invalid data
                if ($this->isValidationError($this->leadData)) {
                    Log::info('Lead processing failed due to validation errors, not retrying', [
                        'platform' => $this->platform,
                        'webhook_id' => $this->webhookId,
                    ]);
                    return;
                }

                // Retry for other failures
                $this->fail(new \Exception("Lead creation failed for platform: {$this->platform}"));
            }
        } catch (Throwable $e) {
            Log::error('Social media lead processing job failed', [
                'platform' => $this->platform,
                'webhook_id' => $this->webhookId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't retry for certain types of errors
            if ($this->shouldNotRetry($e)) {
                Log::info('Job failed with non-retryable error, not retrying', [
                    'platform' => $this->platform,
                    'error' => $e->getMessage(),
                ]);
                $this->fail($e);
                return;
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Social media lead processing job permanently failed', [
            'platform' => $this->platform,
            'webhook_id' => $this->webhookId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Log to webhook logs for debugging
        if ($this->webhookId) {
            $this->logWebhookFailure($exception);
        }
    }

    /**
     * Get the appropriate processor for the platform
     */
    private function getProcessor(string $platform): ?SocialMediaLeadProcessor
    {
        return match ($platform) {
            'facebook' => new FacebookLeadProcessor(),
            'instagram' => new InstagramLeadProcessor(),
            'whatsapp' => new WhatsAppLeadProcessor(),
            'indiamart' => new IndiaMartLeadProcessor(),
            'justdial' => new JustDialLeadProcessor(),
            default => null,
        };
    }

    /**
     * Post-process lead based on platform
     */
    private function postProcessLead(Lead $lead, string $platform): void
    {
        try {
            switch ($platform) {
                case 'facebook':
                case 'instagram':
                    // Add Facebook/Instagram specific processing
                    $this->addSocialMediaTags($lead);
                    break;

                case 'whatsapp':
                    // Add WhatsApp specific processing
                    $this->addWhatsAppTags($lead);
                    break;

                case 'indiamart':
                case 'justdial':
                    // Add business directory specific processing
                    $this->addBusinessDirectoryTags($lead);
                    break;
            }

            // Update lead source tracking
            $this->updateLeadSourceTracking($lead, $platform);
        } catch (\Exception $e) {
            Log::error('Post-processing failed for social media lead', [
                'lead_id' => $lead->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Add social media specific tags
     */
    private function addSocialMediaTags(Lead $lead): void
    {
        $tags = $lead->tags ?? [];
        $tags[] = 'social-media';
        $tags[] = 'facebook-instagram';

        $lead->update(['tags' => $tags]);
    }

    /**
     * Add WhatsApp specific tags
     */
    private function addWhatsAppTags(Lead $lead): void
    {
        $tags = $lead->tags ?? [];
        $tags[] = 'whatsapp';
        $tags[] = 'instant-messaging';

        $lead->update(['tags' => $tags]);
    }

    /**
     * Add business directory specific tags
     */
    private function addBusinessDirectoryTags(Lead $lead): void
    {
        $tags = $lead->tags ?? [];
        $tags[] = 'business-directory';
        $tags[] = 'local-search';

        $lead->update(['tags' => $tags]);
    }

    /**
     * Update lead source tracking
     */
    private function updateLeadSourceTracking(Lead $lead, string $platform): void
    {
        try {
            // Update gym's lead source statistics
            $gym = $lead->gym;
            if ($gym) {
                $stats = $gym->lead_source_stats ?? [];
                $stats[$platform] = ($stats[$platform] ?? 0) + 1;
                $gym->update(['lead_source_stats' => $stats]);
            }

            // Update tenant's lead source statistics if applicable
            if ($gym->tenant) {
                $tenantStats = $gym->tenant->lead_source_stats ?? [];
                $tenantStats[$platform] = ($tenantStats[$platform] ?? 0) + 1;
                $gym->tenant->update(['lead_source_stats' => $tenantStats]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update lead source tracking', [
                'lead_id' => $lead->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if the error is a validation error that shouldn't be retried
     */
    private function isValidationError(array $data): bool
    {
        // Check for obvious validation issues
        if (empty($data['name']) && empty($data['phone']) && empty($data['email'])) {
            return true;
        }

        // Check for invalid phone format
        if (isset($data['phone']) && !preg_match('/^[+]?[0-9\s\-\(\)]+$/', $data['phone'])) {
            return true;
        }

        // Check for invalid email format
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the job should not be retried based on the exception
     */
    private function shouldNotRetry(Throwable $e): bool
    {
        // Don't retry for validation errors
        if (str_contains($e->getMessage(), 'validation')) {
            return true;
        }

        // Don't retry for missing required data
        if (str_contains($e->getMessage(), 'required')) {
            return true;
        }

        // Don't retry for invalid platform
        if (str_contains($e->getMessage(), 'No processor available')) {
            return true;
        }

        return false;
    }

    /**
     * Log webhook failure for debugging
     */
    private function logWebhookFailure(Throwable $exception): void
    {
        try {
            // This would log to your webhook logs table
            Log::channel('webhook')->error('Webhook processing failed', [
                'webhook_id' => $this->webhookId,
                'platform' => $this->platform,
                'error' => $exception->getMessage(),
                'attempts' => $this->attempts(),
                'data' => $this->leadData,
            ]);
        } catch (\Exception $e) {
            // Ignore logging errors to prevent infinite loops
        }
    }
}
