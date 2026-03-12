<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class WhatsAppTemplateManager
{
    private string $tenantId;

    protected bool $useSandbox;

    public function __construct(string $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->useSandbox = config('services.twilio.use_sandbox', true);
    }

    private function isSystemTenant(): bool
    {
        return $this->tenantId === config('app.system_tenant_id', 'system')
            || $this->tenantId === 'system';
    }

    private function getAccount(): ?WhatsAppAccount
    {
        if ($this->isSystemTenant()) {
            return null;
        }

        return WhatsAppAccount::where('gym_id', $this->tenantId)->first();
    }

    private function getClient(): ?Client
    {
        if ($this->isSystemTenant()) {
            return null;
        }

        $account = $this->getAccount();

        if (! $account) {
            return null;
        }

        if ($this->useSandbox) {
            return new Client(
                config('services.twilio.master_sid'),
                config('services.twilio.master_token'),
            );
        }

        return new Client(
            $account->twilio_account_sid,
            $account->twilio_auth_token,
        );
    }

    public function fetchTemplates(): array
    {
        try {
            if ($this->isSystemTenant()) {
                return [
                    'success' => false,
                    'error' => 'Templates are not available for system tenant',
                    'templates' => [],
                ];
            }

            $client = $this->getClient();

            if (! $client) {
                return [
                    'success' => false,
                    'error' => 'No WhatsApp account found',
                    'templates' => [],
                ];
            }

            $contents = $client->content->v1->contents->read();

            $templates = [];
            foreach ($contents as $content) {
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

    public function syncTemplatesToDatabase(): array
    {
        try {
            if ($this->isSystemTenant()) {
                return [
                    'success' => false,
                    'error' => 'Templates are not available for system tenant',
                ];
            }

            $result = $this->fetchTemplates();

            if (! $result['success']) {
                return $result;
            }

            $synced = 0;
            foreach ($result['templates'] as $template) {
                WhatsAppTemplate::updateOrCreate(
                    [
                        'gym_id' => $this->tenantId,
                        'name' => $template['friendly_name'],
                    ],
                    [
                        'twilio_template_sid' => $template['sid'],
                        'language' => $template['language'],
                        'variables' => $template['variables'],
                        'status' => 'approved',
                        'is_active' => true,
                    ],
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

    public function getTemplate(string $templateName): ?WhatsAppTemplate
    {
        if ($this->isSystemTenant()) {
            return null;
        }

        return WhatsAppTemplate::where('gym_id', $this->tenantId)
            ->where('name', $templateName)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->first();
    }

    public function clearCache(?string $templateName = null): void
    {
        if ($templateName) {
            $cacheKey = "whatsapp_template:{$this->tenantId}:{$templateName}";
            Cache::forget($cacheKey);
        } else {
            Cache::forget("whatsapp_templates:{$this->tenantId}");
        }
    }
}
