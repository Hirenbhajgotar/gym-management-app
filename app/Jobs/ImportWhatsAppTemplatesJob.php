<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\WhatsAppTemplates\TemplateCollectionResult;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Services\Meta\MetaApiClient;
use App\Services\Meta\MetaContext;
use App\Services\Meta\MetaTemplateService;
use App\Services\Support\TemplateVariableHelper;
use App\Services\Twilio\TwilioContext;
use App\Services\Twilio\TwilioTemplateService;
use App\Services\WhatsAppTemplateService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Imports WhatsApp templates from Meta/Twilio for a specific gym.
 *
 * This job is dispatched when a tenant manually sets up their WhatsApp account
 * and wants to import existing templates from their Meta/Twilio account.
 * Templates are imported as DRAFT status to allow manual review before submission.
 *
 * Uses ShouldBeUnique to prevent concurrent imports for the same gym.
 * Templates are upserted (updateOrCreate) to handle re-runs gracefully.
 */
class ImportWhatsAppTemplatesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    /**
     * Create a new job instance.
     *
     * @param  int  $gymId  The gym (tenant) ID to import templates for
     * @param  WhatsAppProviderTypeEnum|null  $providerType  Override provider type (auto-detected from account if null)
     */
    public function __construct(
        public readonly int $gymId,
        public readonly ?WhatsAppProviderTypeEnum $providerType = null,
    ) {}

    /**
     * Unique job key based on gym ID.
     * Prevents concurrent imports for the same gym.
     */
    public function uniqueId(): string
    {
        return "import-whatsapp-templates:gym:{$this->gymId}";
    }

    /**
     * The queue name for this job.
     */
    public function queue(): string
    {
        return 'whatsapp-templates';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ImportWhatsAppTemplatesJob: starting', ['gym_id' => $this->gymId]);

        $account = WhatsAppAccount::query()->where('gym_id', $this->gymId)->first();

        if (! $account) {
            Log::warning('ImportWhatsAppTemplatesJob: no WhatsApp account found', [
                'gym_id' => $this->gymId,
            ]);

            return;
        }

        if (! $account->isActive() || ! $account->isVerified()) {
            Log::warning('ImportWhatsAppTemplatesJob: WhatsApp account not active or verified', [
                'gym_id' => $this->gymId,
                'is_active' => $account->isActive(),
                'is_verified' => $account->isVerified(),
            ]);

            return;
        }

        $providerType = $this->providerType ?? $account->provider_type;

        $templateResult = match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => $this->importFromTwilio(),
            WhatsAppProviderTypeEnum::WABA => $this->importFromMeta(),
            default => $this->importFromProviderFallback($account),
        };

        if (! $templateResult->success) {
            Log::error('ImportWhatsAppTemplatesJob: failed to fetch templates', [
                'gym_id' => $this->gymId,
                'provider' => $providerType->value ?? 'unknown',
                'error' => $templateResult->error,
            ]);

            $this->fail('Failed to fetch templates');

            return;
        }

        $syncedCount = $this->syncTemplatesToDatabase($templateResult->templates, $providerType);

        Log::info('ImportWhatsAppTemplatesJob: completed', [
            'gym_id' => $this->gymId,
            'provider' => $providerType->value ?? 'unknown',
            'synced_count' => $syncedCount,
        ]);
    }

    /**
     * Import templates from Twilio.
     */
    private function importFromTwilio(): TemplateCollectionResult
    {
        $context = new TwilioContext($this->gymId);
        $templateService = new TwilioTemplateService($context, resolve(WhatsAppTemplateService::class));

        return $templateService->getTemplates($this->gymId);
    }

    /**
     * Import templates from Meta.
     */
    private function importFromMeta(): TemplateCollectionResult
    {
        $context = new MetaContext($this->gymId);
        $api = new MetaApiClient($context);
        $templateService = new MetaTemplateService($context, $api);

        return $templateService->getTemplates($this->gymId);
    }

    /**
     * Fallback import when provider type is unknown.
     * Attempts to import based on available credentials.
     */
    private function importFromProviderFallback(WhatsAppAccount $account): TemplateCollectionResult
    {
        // Try Twilio first if twilio credentials exist
        if (filled($account->twilio_account_sid) && filled($account->twilio_auth_token)) {
            return $this->importFromTwilio();
        }

        // Try Meta if waba_id exists
        if (filled($account->waba_id) && filled($account->meta_access_token)) {
            return $this->importFromMeta();
        }

        Log::warning('ImportWhatsAppTemplatesJob: no provider credentials found', [
            'gym_id' => $this->gymId,
        ]);

        return new TemplateCollectionResult(
            success: false,
            templates: [],
            error: 'No WhatsApp provider credentials configured',
        );
    }

    /**
     * Sync templates to the database.
     *
     * @param  array<string, mixed>  $templates  Array of template data from API
     */
    private function syncTemplatesToDatabase(
        array $templates,
        ?WhatsAppProviderTypeEnum $providerType,
    ): int {
        $syncedCount = 0;

        foreach ($templates as $templateData) {
            $templateName = $this->extractTemplateName($templateData, $providerType);

            if (! $templateName) {
                Log::warning('ImportWhatsAppTemplatesJob: skipping template without name', [
                    'gym_id' => $this->gymId,
                    'template_data' => $templateData,
                ]);

                continue;
            }

            $providerSid = $this->extractProviderSid($templateData, $providerType);
            $body = $this->extractBody($templateData, $providerType);

            $record = WhatsAppTemplate::query()->updateOrCreate([
                'gym_id' => $this->gymId,
                'name' => $templateName,
            ], [
                'display_name' => $templateName,
                'body' => $body,
                'category' => $this->extractCategory($templateData, $providerType),
                'status' => WhatsAppTemplateStatusEnum::DRAFT,
                'is_active' => true,
            ]);

            // Set provider-specific ID via direct property assignment
            if ($providerType === WhatsAppProviderTypeEnum::TWILIO) {
                $record->twilio_template_sid = $providerSid ?: null;
            } elseif ($providerType === WhatsAppProviderTypeEnum::WABA) {
                $record->meta_template_id = $providerSid ?: null;
            }

            // Extract and set variables if available
            $variables = $this->extractVariables($templateData, $body, $providerType);
            if (filled($variables)) {
                $record->variables = $variables;
                $record->variable_count = count($variables);
                $record->variable_placeholder = TemplateVariableHelper::generateDefaultPlaceholders($variables);
            }

            $record->save();
            $syncedCount++;
        }

        return $syncedCount;
    }

    /**
     * Extract template name based on provider type.
     */
    private function extractTemplateName(array $templateData, ?WhatsAppProviderTypeEnum $providerType): ?string
    {
        return match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => $templateData['friendly_name'] ?? $templateData['name'] ?? null,
            WhatsAppProviderTypeEnum::WABA => $templateData['name'] ?? null,
            default => $templateData['friendly_name'] ?? $templateData['name'] ?? null,
        };
    }

    /**
     * Extract provider-specific template ID (SID).
     */
    private function extractProviderSid(array $templateData, ?WhatsAppProviderTypeEnum $providerType): ?string
    {
        return match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => $templateData['sid'] ?? null,
            WhatsAppProviderTypeEnum::WABA => $templateData['id'] ?? null,
            default => $templateData['sid'] ?? $templateData['id'] ?? null,
        };
    }

    /**
     * Extract template body based on provider type.
     */
    private function extractBody(array $templateData, ?WhatsAppProviderTypeEnum $providerType): ?string
    {
        return match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => $templateData['types']['twilio/text']['body'] ?? null,
            WhatsAppProviderTypeEnum::WABA => $this->extractMetaBody($templateData),
            default => $templateData['body'] ?? $templateData['types']['twilio/text']['body'] ?? null,
        };
    }

    /**
     * Extract body from Meta template components.
     */
    private function extractMetaBody(array $templateData): ?string
    {
        $components = $templateData['components'] ?? [];

        foreach ($components as $component) {
            if (($component['type'] ?? '') === 'BODY') {
                return $component['text'] ?? null;
            }
        }

        return null;
    }

    /**
     * Extract category from template data.
     */
    private function extractCategory(array $templateData, ?WhatsAppProviderTypeEnum $providerType): string
    {
        return match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => 'UTILITY',
            WhatsAppProviderTypeEnum::WABA => strtoupper($templateData['category'] ?? 'UTILITY'),
            default => 'UTILITY',
        };
    }

    /**
     * Extract variables from template body.
     *
     * @return array<int, array{name: string, format: string, example: string}>
     */
    private function extractVariables(array $templateData, ?string $body, ?WhatsAppProviderTypeEnum $providerType): array
    {
        if ($body) {
            return TemplateVariableHelper::extractVariables($body);
        }

        // Try to extract from Twilio variables field
        if ($providerType === WhatsAppProviderTypeEnum::TWILIO) {
            $twilioVars = $templateData['variables'] ?? [];
            if (is_array($twilioVars) && filled($twilioVars)) {
                return $this->convertTwilioVariables($twilioVars);
            }
        }

        return [];
    }

    /**
     * Convert Twilio variable format to our internal format.
     *
     * @param  array<int, mixed>  $twilioVars
     * @return array<int, array{name: string, format: string, example: string}>
     */
    private function convertTwilioVariables(array $twilioVars): array
    {
        $result = [];
        $index = 1;

        foreach ($twilioVars as $var) {
            if (is_array($var)) {
                $result[] = [
                    'name' => $var['name'] ?? "{{$index}}",
                    'format' => $var['format'] ?? 'text',
                    'example' => $var['example'] ?? "{{$index}}",
                ];
            }
            $index++;
        }

        return $result;
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ImportWhatsAppTemplatesJob: job failed', [
            'gym_id' => $this->gymId,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);
    }
}
