<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\DTOs\WhatsAppTemplates\CreateTemplateData;
use App\DTOs\WhatsAppTemplates\TemplateCollectionResult;
use App\DTOs\WhatsAppTemplates\TemplateSubmissionResult;
use App\Enums\TemplateCategoryEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Interfaces\TemplateManagementInterface;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Meta Template Service.
 *
 * Implements TemplateManagementInterface for template operations
 * via Meta Graph API.
 * Uses MetaContext and MetaApiClient for consistent dependency injection.
 */
class MetaTemplateService implements TemplateManagementInterface
{
    private const TEMPLATES_CACHE_TTL = 300;

    private const TEMPLATES_CACHE_STALE = 3600;

    private const LOCK_TTL = 30;

    public function __construct(
        protected MetaContext $context,
        protected MetaApiClient $api,
        protected ?WhatsAppTemplateService $templateService = null,
    ) {
        $this->templateService ??= resolve(WhatsAppTemplateService::class);
    }

    /**
     * Create a template.
     *
     * Template creation is provider-agnostic (DB storage only),
     * so we delegate to the base service.
     */
    public function createTemplate(int $gymId, CreateTemplateData $data): WhatsAppTemplate
    {
        throw_unless($this->templateService, RuntimeException::class, 'Template service not available');

        return $this->templateService->createTemplate($gymId, $data);
    }

    /**
     * Submit template to Meta for approval.
     */
    public function submitTemplate(?WhatsAppTemplate $template = null): TemplateSubmissionResult
    {
        $template = $template?->fresh();

        if (! $template) {
            return new TemplateSubmissionResult(success: false, error: 'Template no longer exists.');
        }

        if ($template->status !== WhatsAppTemplateStatusEnum::DRAFT) {
            return new TemplateSubmissionResult(
                success: false,
                error: 'Only draft templates can be submitted. ' . ($template->status?->value ? 'Current status: ' . $template->status->value : ''),
            );
        }

        if ($this->context->gymId !== $template->gym_id) {
            Log::warning('Template gym_id mismatch in submitTemplate', [
                'template_id' => $template->id,
                'context_gym' => $this->context->gymId,
                'template_gym' => $template->gym_id,
            ]);

            return new TemplateSubmissionResult(success: false, error: 'Template does not belong to this gym.');
        }

        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return new TemplateSubmissionResult(success: false, error: 'WABA not configured');
        }

        $account = $this->context->getAccount();

        if (! $account) {
            return new TemplateSubmissionResult(success: false, error: 'WhatsApp account not configured for this gym');
        }

        if ($account->isWabaRestricted() || $account->isWabaBlocked() || $account->isWabaSuspended()) {
            $reason = match (true) {
                $account->isWabaRestricted() => 'WhatsApp account is restricted. Please contact support for assistance.',
                $account->isWabaBlocked() => 'WhatsApp account is blocked. Please contact support for assistance.',
                $account->isWabaSuspended() => 'WhatsApp account is suspended. Please contact support for assistance.',
                default => 'WhatsApp account is not verified',
            };

            return new TemplateSubmissionResult(success: false, error: $reason);
        }

        $lock = Cache::lock("meta_template_submit:{$template->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            return new TemplateSubmissionResult(success: false, error: 'Template submission already in progress');
        }

        try {
            $bodyComponent = [
                'type' => 'BODY',
                'text' => $template->body,
            ];

            $bodyExample = $this->buildBodyExample($template);
            if ($bodyExample !== null) {
                $bodyComponent['example'] = $bodyExample;
            }

            Log::debug('Submitting WhatsApp template to Meta', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'meta_template_id' => $template->meta_template_id,
                'waba_id' => $wabaId,
                'language' => $this->resolveLanguage(),
                'category' => strtoupper($template->category->value ?? TemplateCategoryEnum::UTILITY->value),
                'body' => $template->body,
                'body_example' => $bodyExample,
            ]);

            // If a template with the same name already exists in Meta (e.g. a stale
            // draft whose local meta_template_id is null, or a rejected copy), resolve
            // the collision before creating a new one — otherwise Meta rejects the
            // submission with an "Invalid parameter" error.
            $stale = $this->resolveStaleTemplate($wabaId, $template);
            if ($stale !== null) {
                return $stale;
            }

            $response = $this->api->post("/{$wabaId}/message_templates", [
                'name' => $template->name,
                'language' => $this->resolveLanguage(),
                'category' => strtoupper($template->category->value ?? TemplateCategoryEnum::UTILITY->value),
                'components' => [
                    $bodyComponent,
                ],
            ]);

            if (! $response['success']) {
                return new TemplateSubmissionResult(success: false, error: $response['error'] ?? 'Failed to submit template');
            }

            $templateId = isset($response['data']['id']) ? $response['data']['id'] : null;

            if (! $templateId) {
                return TemplateSubmissionResult::fromError('Missing template ID in Meta response');
            }

            $template->meta_template_id = $templateId;
            $rawStatus = isset($response['data']['status']) && is_string($response['data']['status']) ? $response['data']['status'] : null;
            $template->status = WhatsAppTemplateStatusEnum::fromMetaTemplateStatus($rawStatus);
            $template->save();

            Cache::forget($this->templatesCacheKey());

            return TemplateSubmissionResult::fromMetaResponse(
                templateId: $templateId,
                status: $template->status->value,
            );
        } catch (LockTimeoutException $e) {
            return new TemplateSubmissionResult(success: false, error: 'Template submission is already in progress. Please wait.');
        } catch (Throwable $th) {
            Log::error('Template submission failed with exception', [
                'template_id' => $template->id,
                'error' => $th->getMessage(),
            ]);

            return new TemplateSubmissionResult(success: false, error: 'An unexpected error occurred during template submission.');
        } finally {
            $lock?->release();
        }
    }

    /**
     * Sync template status from Meta.
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        $template = $template->fresh();

        if (! $template) {
            return;
        }

        if ($this->context->gymId !== $template->gym_id) {
            Log::warning('Template gym_id mismatch in syncTemplateStatus', [
                'template_id' => $template->id,
                'context_gym' => $this->context->gymId,
                'template_gym' => $template->gym_id,
            ]);

            return;
        }

        if (! $template->meta_template_id) {
            return;
        }

        $lock = Cache::lock("meta_template_sync:{$template->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            return;
        }

        try {
            $response = $this->api->get("/{$template->meta_template_id}");

            if (! $response['success']) {
                return;
            }

            $rawStatus = $response['data']['status'] ?? 'unknown';

            $newStatus = $this->mapStatus($rawStatus);

            if (! $newStatus) {
                return;
            }

            $template->status = $newStatus;
            if ($rawStatus === 'REJECTED') {
                $template->rejection_reason = $response['data']['rejection_reason'] ?? null;
            }

            $template->save();
        } catch (LockTimeoutException $e) {
            // Skip this run, another process is syncing
        } catch (Throwable $e) {
            Log::warning("Failed to sync Meta template status for {$template->id}", ['error' => $e->getMessage()]);
        } finally {
            $lock?->release();
        }
    }

    /**
     * Get templates from Meta.
     */
    public function getTemplates(int $gymId): TemplateCollectionResult
    {
        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return new TemplateCollectionResult(success: false, templates: [], error: 'WABA not configured');
        }

        try {
            $templates = Cache::flexible($this->templatesCacheKey(), [self::TEMPLATES_CACHE_TTL, self::TEMPLATES_CACHE_STALE], function () use ($wabaId) {
                $response = $this->api->get("/{$wabaId}/message_templates");

                if (! $response['success']) {
                    throw new RuntimeException($response['error'] ?? 'API Error');
                }

                return $response['data']['data'] ?? [];
            });
        } catch (Throwable $th) {
            Log::warning('Failed to fetch fresh Meta templates, falling back to cache.', ['error' => $th->getMessage()]);
            $templates = Cache::get($this->templatesCacheKey());
            if ($templates === null) {
                return new TemplateCollectionResult(success: false, templates: [], error: 'No templates found and API unavailable.');
            }
        }

        return new TemplateCollectionResult(success: true, templates: $templates, count: count($templates));
    }

    private function mapStatus(string $rawStatus): ?WhatsAppTemplateStatusEnum
    {
        return match (strtoupper($rawStatus)) {
            'PENDING' => WhatsAppTemplateStatusEnum::PENDING,
            'IN_REVIEW' => WhatsAppTemplateStatusEnum::IN_REVIEW,
            'APPROVED' => WhatsAppTemplateStatusEnum::APPROVED,
            'REJECTED' => WhatsAppTemplateStatusEnum::REJECTED,
            'DISABLED', 'IN_APPEAL', 'PAUSED', 'LIMIT_EXCEEDED', 'PENDING_DELETION' => WhatsAppTemplateStatusEnum::DISABLED,
            default => null,
        };
    }

    /**
     * Build the Meta BODY `example` payload from the template's variable placeholders.
     *
     * Mirrors the model's `formattedBody()` accessor: a placeholder value may be a
     * plain string OR an array with `example`/`placeholder` keys. Meta rejects an
     * `example` whose value contains the literal `{{n}}` token, and it rejects a
     * BODY component that has variables but no `example` at all — so we always
     * return an example when variables exist, using a safe sample as the fallback.
     *
     * @return array{body_text: array<int, array<int, string>>}|null
     */
    private function buildBodyExample(WhatsAppTemplate $template): ?array
    {
        preg_match_all('/\{\{(\d+)\}\}/', (string) $template->body, $matches);
        $indices = array_unique($matches[1]);

        if ($indices === []) {
            // No variables in the body — Meta does not require an example.
            return null;
        }

        $placeholders = $template->getVariablePlaceholders();

        $examples = [];

        foreach ($indices as $index) {
            $entry = $placeholders[$index] ?? null;

            if (is_string($entry) && $entry !== '') {
                // Stored as a plain scalar value (mirrors formattedBody()).
                $examples[] = $entry;

                continue;
            }

            if (is_array($entry)) {
                $example = $entry['example'] ?? null;
                if (is_scalar($example) && $example !== '') {
                    $examples[] = (string) $example;

                    continue;
                }

                $placeholder = $entry['placeholder'] ?? null;
                if (is_scalar($placeholder) && $placeholder !== '') {
                    $examples[] = (string) $placeholder;

                    continue;
                }
            }

            // Fallback: never emit the literal placeholder token, which Meta rejects.
            $examples[] = "Sample {$index}";
        }

        return ['body_text' => [$examples]];
    }

    /**
     * Resolve a stale template that already exists in Meta under the same name.
     *
     * Returns a {@see TemplateSubmissionResult} when the submission must stop (e.g.
     * the remote template is already approved), or null when it is safe to proceed
     * (after deleting any stale/rejected copy).
     */
    private function resolveStaleTemplate(string $wabaId, WhatsAppTemplate $template): ?TemplateSubmissionResult
    {
        $metaId = $template->meta_template_id;

        if ($metaId === null) {
            $metaId = $this->findMetaTemplateIdByName($wabaId, $template->name);
        }

        if ($metaId === null) {
            return null;
        }

        $statusResponse = $this->api->get("/{$metaId}");

        if (! $statusResponse['success']) {
            return null;
        }

        $rawStatus = is_string($statusResponse['data']['status'] ?? null)
            ? $statusResponse['data']['status']
            : 'unknown';
        $remoteStatus = $this->mapStatus($rawStatus);

        if ($remoteStatus === WhatsAppTemplateStatusEnum::APPROVED) {
            return TemplateSubmissionResult::fromError(
                'This template is already approved in Meta and cannot be resubmitted. Create a new template instead.'
            );
        }

        $deleted = $this->api->makeRequest('delete', "/{$wabaId}/message_templates", ['hsm_id' => $metaId]);

        if (! $deleted['success']) {
            Log::warning('Could not delete stale Meta template prior to resubmission', [
                'meta_template_id' => $metaId,
                'error' => isset($deleted['error']) ? $deleted['error'] : 'unknown',
            ]);
        }

        return null;
    }

    /**
     * Look up a template's Meta id by name via the Graph API name filter.
     */
    private function findMetaTemplateIdByName(string $wabaId, string $name): ?string
    {
        $response = $this->api->get("/{$wabaId}/message_templates", ['name' => $name]);

        if (! $response['success']) {
            return null;
        }

        $data = $response['data']['data'] ?? [];

        if (! is_array($data) || $data === []) {
            return null;
        }

        $first = $data[0];

        $id = is_array($first) ? ($first['id'] ?? null) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Resolve the Meta template language code.
     *
     * Meta requires a supported language code (e.g. `en_US`, `en_GB`). A bare
     * `en` is not universally accepted, so we default to the full locale.
     */
    private function resolveLanguage(): string
    {
        return config()->string('services.whatsapp.template_language', 'en_US');
    }

    private function templatesCacheKey(): string
    {
        return 'meta_templates:gym:' . $this->context->gymId . ':waba:' . $this->context->getWabaId();
    }
}
