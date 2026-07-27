<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\WhatsAppTemplates\TemplateCollectionResult;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Services\Meta\MetaApiClient;
use App\Services\Meta\MetaContext;
use App\Services\Meta\MetaTemplateService;
use App\Services\Twilio\TwilioContext;
use App\Services\Twilio\TwilioTemplateService;
use App\Services\WhatsApp\TemplateFieldMapper;
use App\Services\WhatsAppTemplateService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportWhatsAppTemplatesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 180;

    /**
     * Lock TTL in seconds. Prevents stale unique locks from permanently
     * blocking future dispatches if the worker crashes mid-execution.
     */
    public int $uniqueFor = 300;

    /**
     * @param  WhatsAppProviderTypeEnum|null  $providerType  Override provider type (auto-detected from account if null)
     */
    public function __construct(
        public readonly int $gymId,
        public readonly ?WhatsAppProviderTypeEnum $providerType = null,
    ) {
        $this->onQueue('whatsapp-templates');
    }

    public function uniqueId(): string
    {
        return "import-whatsapp-templates:gym:{$this->gymId}";
    }

    public function handle(): void
    {
        try {
            Log::info('ImportWhatsAppTemplatesJob: starting', ['gym_id' => $this->gymId]);

            $account = WhatsAppAccount::query()->where('gym_id', $this->gymId)->first();

            if (! $account) {
                Log::warning('ImportWhatsAppTemplatesJob: no WhatsApp account found', [
                    'gym_id' => $this->gymId,
                ]);

                return;
            }

            if ($account->isWabaRestricted() || $account->isWabaBlocked() || $account->isWabaSuspended()) {
                Log::warning('ImportWhatsAppTemplatesJob: WhatsApp account is in a non-functional state', [
                    'gym_id' => $this->gymId,
                    'status' => $account->status->value ?? 'unknown',
                    'verification_status' => $account->verification_status->value ?? 'unknown',
                ]);

                return;
            }

            $providerType = $this->providerType ?? $account->provider_type;

            $templateResult = $this->fetchTemplates($providerType, $account);

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
        } catch (Exception $e) {
            Log::error('ImportWhatsAppTemplatesJob: job failed', [
                'gym_id' => $this->gymId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    private function fetchTemplates(?WhatsAppProviderTypeEnum $providerType, WhatsAppAccount $account): TemplateCollectionResult
    {
        return match ($providerType) {
            WhatsAppProviderTypeEnum::TWILIO => $this->importFromTwilio(),
            WhatsAppProviderTypeEnum::WABA => $this->importFromMeta(),
            default => $this->importFromProviderFallback($account),
        };
    }

    private function importFromTwilio(): TemplateCollectionResult
    {
        $context = new TwilioContext($this->gymId);
        $templateService = new TwilioTemplateService($context, new WhatsAppTemplateService());

        return $templateService->getTemplates($this->gymId);
    }

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
        if (filled($account->twilio_account_sid) && filled($account->twilio_auth_token)) {
            return $this->importFromTwilio();
        }

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
     * @param  array<int, array<string, mixed>>  $templates
     */
    private function syncTemplatesToDatabase(array $templates, ?WhatsAppProviderTypeEnum $providerType): int
    {
        $mapper = new TemplateFieldMapper();
        $validTemplates = $mapper->filterValidTemplates($templates, $providerType);
        $syncedCount = 0;

        foreach ($validTemplates as $templateData) {
            $fields = $mapper->mapToTemplateFields($templateData, $providerType);

            $record = WhatsAppTemplate::query()->updateOrCreate([
                'gym_id' => $this->gymId,
                'name' => $fields['name'],
            ], [
                'display_name' => $fields['display_name'],
                'body' => $fields['body'],
                'category' => $fields['category'],
                'status' => $fields['status'],
                'is_active' => true,
            ]);

            if ($providerType === WhatsAppProviderTypeEnum::TWILIO) {
                $record->twilio_template_sid = $fields['provider_sid'] ?: null;
            } elseif ($providerType === WhatsAppProviderTypeEnum::WABA) {
                $record->meta_template_id = $fields['provider_sid'] ?: null;
            }

            $record->variables = filled($fields['variables']) ? $fields['variables'] : null;
            $record->variable_count = filled($fields['variables']) ? $fields['variable_count'] : 0;
            $record->variable_placeholder = filled($fields['variables']) ? $fields['variable_placeholder'] : null;

            $record->save();

            $syncedCount++;
        }

        return $syncedCount;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ImportWhatsAppTemplatesJob: job failed', [
            'gym_id' => $this->gymId,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);
    }
}
