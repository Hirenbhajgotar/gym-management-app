<?php

namespace App\Services\Meta;

use App\Interfaces\TemplateManagementInterface;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateService;

/**
 * Meta Template Service.
 *
 * Implements TemplateManagementInterface for template operations
 * via Meta Graph API.
 */
class MetaTemplateService implements TemplateManagementInterface
{
    public function __construct(
        protected MetaContext $context,
        protected MetaApiClient $api
    ) {}

    /**
     * Create a template.
     */
    public function createTemplate(int $gymId, array $data): WhatsAppTemplate
    {
        $templateService = app(WhatsAppTemplateService::class);

        return $templateService->createTemplate($gymId, $data);
    }

    /**
     * Submit template to Meta for approval.
     */
    public function submitTemplate(WhatsAppTemplate $template): array
    {
        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
            ];
        }

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
                'status' => 'pending',
            ]);

            return [
                'success' => true,
                'status' => 'pending',
                'template_id' => $response['data']['id'],
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Failed to submit template',
        ];
    }

    /**
     * Sync template status from Meta.
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        if (! $template->meta_template_id) {
            return;
        }

        $response = $this->api->get("/{$template->meta_template_id}");

        if (! $response['success']) {
            return;
        }

        $status = $response['data']['status'] ?? 'unknown';

        $statusMap = [
            'PENDING' => 'pending',
            'APPROVED' => 'approved',
            'REJECTED' => 'rejected',
            'DISABLED' => 'disabled',
        ];

        $newStatus = $statusMap[$status] ?? $template->status;

        $update = ['status' => $newStatus];

        if ($status === 'REJECTED') {
            $update['rejection_reason'] = $response['data']['rejection_reason'] ?? null;
        }

        $template->update($update);
    }

    /**
     * Get templates from Meta.
     */
    public function getTemplates(int $gymId): array
    {
        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
                'templates' => [],
            ];
        }

        $response = $this->api->get("/{$wabaId}/message_templates");

        if (! $response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                'templates' => [],
            ];
        }

        $templates = $response['data']['data'] ?? [];

        return [
            'success' => true,
            'templates' => $templates,
            'count' => count($templates),
        ];
    }
}
