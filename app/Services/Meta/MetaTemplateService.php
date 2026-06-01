<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\DTOs\WhatsAppTemplates\CreateTemplateData;
use App\DTOs\WhatsAppTemplates\TemplateCollectionResult;
use App\DTOs\WhatsAppTemplates\TemplateSubmissionResult;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Interfaces\TemplateManagementInterface;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $this->templateService ??= app(WhatsAppTemplateService::class);
    }

    /**
     * Create a template.
     *
     * Template creation is provider-agnostic (DB storage only),
     * so we delegate to the base service.
     */
    public function createTemplate(int $gymId, CreateTemplateData $data): WhatsAppTemplate
    {
        if (! $this->templateService) {
            throw new \RuntimeException('Template service not available');
        }

        return $this->templateService->createTemplate($gymId, $data);
    }

    /**
     * Submit template to Meta for approval.
     */
    public function submitTemplate(?WhatsAppTemplate $template = null): TemplateSubmissionResult
    {
        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return new TemplateSubmissionResult(success: false, error: 'WABA not configured');
        }

        $account = $this->context->getAccount();

        if (! $account || ! $account->isActive() || ! $account->isVerified()) {
            $reason = match (true) {
                ! $account => 'WhatsApp account not configured for this gym',
                ! $account->isActive() => 'WhatsApp account is not active',
                default => 'WhatsApp account is not verified',
            };

            return new TemplateSubmissionResult(success: false, error: $reason);
        }

        if (! $template) {
            return new TemplateSubmissionResult(success: false, error: 'Template no longer exists.');
        }

        $lock = Cache::lock("meta_template_submit:{$template->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            return new TemplateSubmissionResult(success: false, error: 'Template submission already in progress');
        }

        try {
            $response = $this->api->post("/{$wabaId}/message_templates", [
                'name' => $template->name,
                'language' => 'en_US',
                'category' => strtoupper($template->category ?? 'UTILITY'),
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => $template->body,
                    ],
                ],
            ]);

            if ($response['success']) {
                $templateId = isset($response['data']['id']) ? $response['data']['id'] : null;

                if (! $templateId) {
                    return TemplateSubmissionResult::fromError('Missing template ID in Meta response');
                }

                $template->meta_template_id = $templateId;
                $template->status = WhatsAppTemplateStatusEnum::PENDING;
                $template->save();

                Cache::forget($this->templatesCacheKey());

                return TemplateSubmissionResult::fromMetaResponse(
                    templateId: $templateId,
                    status: WhatsAppTemplateStatusEnum::PENDING->value,
                );
            }

            return new TemplateSubmissionResult(success: false, error: $response['error'] ?? 'Failed to submit template');
        } catch (LockTimeoutException $e) {
            return new TemplateSubmissionResult(success: false, error: 'Template submission is already in progress. Please wait.');
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Sync template status from Meta.
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
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
        } catch (\Throwable $e) {
            Log::warning("Failed to sync Meta template status for {$template->id}", ['error' => $e->getMessage()]);
        } finally {
            optional($lock)->release();
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
                    throw new \RuntimeException($response['error'] ?? 'API Error');
                }

                return $response['data']['data'] ?? [];
            });
        } catch (\Throwable $th) {
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
            'APPROVED' => WhatsAppTemplateStatusEnum::APPROVED,
            'REJECTED' => WhatsAppTemplateStatusEnum::REJECTED,
            'DISABLED' => WhatsAppTemplateStatusEnum::DISABLED,
            default => null,
        };
    }

    private function templatesCacheKey(): string
    {
        return 'meta_templates:gym:' . $this->context->gymId . ':waba:' . $this->context->getWabaId();
    }
}
