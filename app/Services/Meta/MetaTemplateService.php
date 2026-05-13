<?php

namespace App\Services\Meta;

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
    // Cache TTLs
    private const TEMPLATES_CACHE_TTL = 300;  // 5 min fresh

    private const TEMPLATES_CACHE_STALE = 3600; // 1 hr stale

    private const LOCK_TTL = 30;   // seconds

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
    public function createTemplate(int $gymId, array $data): WhatsAppTemplate
    {
        return $this->templateService->createTemplate($gymId, $data);
    }

    /**
     * Submit template to Meta for approval.
     */
    public function submitTemplate(WhatsAppTemplate $template): array
    {
        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return ['success' => false, 'error' => 'WABA not configured'];
        }

        $lock = Cache::lock("meta_template_submit:{$template->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            return ['success' => false, 'error' => 'Template submission already in progress'];
        }

        try {
            // Submit template to Meta for approval
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
                $template->update([
                    'meta_template_id' => $response['data']['id'] ?? null,
                    'status' => WhatsAppTemplateStatusEnum::PENDING,
                ]);

                // Bust templates cache so next getTemplates() reflects the new pending template
                Cache::forget($this->templatesCacheKey());

                return [
                    'success' => true,
                    'status' => WhatsAppTemplateStatusEnum::PENDING,
                    'template_id' => $response['data']['id'],
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Failed to submit template',
            ];
        } catch (LockTimeoutException $e) {
            return ['success' => false, 'error' => 'Template submission is already in progress. Please wait.'];
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
            // Another process is already syncing this template — skip silently
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
    public function getTemplates(int $gymId): array
    {
        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return ['success' => false, 'error' => 'WABA not configured', 'templates' => []];
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
            // Fallback to stale cache if the API fails, otherwise empty array
            Log::warning('Failed to fetch fresh Meta templates, falling back to cache.', ['error' => $th->getMessage()]);
            $templates = Cache::get($this->templatesCacheKey());
            if ($templates === null) {
                return ['success' => false, 'error' => 'No templates found and API unavailable.', 'templates' => []];
            }
        }

        return [
            'success' => true,
            'templates' => $templates,
            'count' => count($templates),
        ];
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
