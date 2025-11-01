<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class WhatsAppTemplateManager
{
    private string $tenantId;

    private bool $useSandbox;

    public function __construct(string $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->useSandbox = config('services.twilio.use_sandbox', true);
    }

    /**
     * Fetch all approved templates from Twilio
     */
    public function fetchTemplates(): array
    {
        try {
            $settings = NotificationSetting::where('gym_id', $this->tenantId)->first();

            if (! $settings) {
                throw new \Exception('No Twilio settings found');
            }

            $client = new Client($settings->twilio_account_sid, $settings->twilio_auth_token);

            // Fetch all content templates
            $contents = $client->content->v1->contents->read();

            $templates = [];
            foreach ($contents as $content) {
                // Only get approved templates
                if ($content->approvalRequests && isset($content->approvalRequests['whatsapp'])) {
                    $whatsappStatus = $content->approvalRequests['whatsapp']['status'] ?? null;

                    if ($whatsappStatus === 'approved') {
                        $templates[] = [
                            'sid' => $content->sid,
                            'friendly_name' => $content->friendlyName,
                            'language' => $content->language,
                            'variables' => $content->variables ?? [],
                            'types' => $content->types ?? [],
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'templates' => $templates,
                'count' => count($templates),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch WhatsApp templates', [
                'error' => $e->getMessage(),
                'tenant_id' => $this->tenantId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'templates' => [],
            ];
        }
    }

    /**
     * Get a specific template by name
     */
    public function getTemplateByName(string $templateName): ?array
    {
        $cacheKey = "whatsapp_template:{$this->tenantId}:{$templateName}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($templateName) {
            $result = $this->fetchTemplates();

            if (! $result['success']) {
                return null;
            }

            foreach ($result['templates'] as $template) {
                if ($template['friendly_name'] === $templateName) {
                    return $template;
                }
            }

            return null;
        });
    }

    /**
     * Sync templates to database for easy access
     */
    public function syncTemplatesToDatabase(): array
    {
        try {
            $result = $this->fetchTemplates();

            if (! $result['success']) {
                return $result;
            }

            $synced = 0;
            foreach ($result['templates'] as $template) {
                WhatsAppTemplate::updateOrCreate(
                    [
                        'gym_id' => $this->tenantId,
                        'template_name' => $template['friendly_name'],
                    ],
                    [
                        'template_sid' => $template['sid'],
                        'language' => $template['language'],
                        'variables' => $template['variables'],
                        'status' => 'approved',
                        'synced_at' => now(),
                    ]
                );
                $synced++;
            }

            return [
                'success' => true,
                'synced' => $synced,
                'message' => "Successfully synced {$synced} templates",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to sync templates to database', [
                'error' => $e->getMessage(),
                'tenant_id' => $this->tenantId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get template from database (faster than API call)
     */
    public function getTemplate(string $templateName): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::where('gym_id', $this->tenantId)
            ->where('template_name', $templateName)
            ->where('status', 'approved')
            ->first();
    }

    /**
     * Clear template cache
     */
    public function clearCache(?string $templateName = null): void
    {
        if ($templateName) {
            $cacheKey = "whatsapp_template:{$this->tenantId}:{$templateName}";
            Cache::forget($cacheKey);
        } else {
            // Clear all templates for this tenant
            Cache::forget("whatsapp_templates:{$this->tenantId}");
        }
    }
}
