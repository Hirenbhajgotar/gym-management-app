<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class WhatsAppTemplateService
{
    private Client $masterClient;

    private string $masterAccountSid;

    private string $masterAuthToken;

    private bool $useSandbox = true;

    public function __construct()
    {
        $this->useSandbox = config('services.twilio.use_sandbox', true);
        $this->masterAccountSid = config('services.twilio.master_sid');
        $this->masterAuthToken = config('services.twilio.master_token');
        $this->masterClient = new Client($this->masterAccountSid, $this->masterAuthToken);
    }

    public function createDefaultTemplates(int $gymId): void
    {
        $templates = WhatsAppTemplate::getDefaults();

        foreach ($templates as $template) {
            $this->createTemplate($gymId, $template->toArray());
        }

        Log::info("Default WhatsApp templates created for gym: {$gymId}");
    }

    public function createTemplate(int $gymId, array $data): WhatsAppTemplate
    {
        $variables = $this->extractVariables($data['body']);

        $template = WhatsAppTemplate::create([
            'gym_id' => $gymId,
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'body' => $data['body'],
            'category' => $data['category'] ?? 'utility',
            'variables' => $variables,
            'variable_count' => count($variables),
            'variable_placeholder' => $data['variable_placeholder'] ?? $this->generateDefaultPlaceholders($variables),
            'status' => 'draft',
            'is_default' => $data['is_default'] ?? false,
            'is_active' => true,
        ]);

        return $template;
    }

    public function updateTemplate(WhatsAppTemplate $template, array $data): WhatsAppTemplate
    {
        $needsResubmission = in_array($template->status, ['pending', 'approved', 'rejected'])
            && isset($data['body'])
            && $data['body'] !== $template->body;

        if (isset($data['body'])) {
            $data['variables'] = $this->extractVariables($data['body']);
            $data['variable_count'] = count($data['variables']);

            if (! isset($data['variable_placeholder'])) {
                $data['variable_placeholder'] = $this->generateDefaultPlaceholders($data['variables']);
            }
        }

        $template->update($data);

        if ($needsResubmission && $template->twilio_template_sid) {
            $this->submitForVerification($template);
        }

        return $template;
    }

    public function submitForVerification(WhatsAppTemplate $template): array
    {
        $whatsAppAccount = WhatsAppAccount::where('gym_id', $template->gym_id)->first();

        if (! $whatsAppAccount) {
            Log::error('WhatsApp account not found for gym', [
                'template_id' => $template->id,
                'gym_id' => $template->gym_id,
            ]);

            return ['success' => false, 'error' => 'WhatsApp account not configured for this gym'];
        }

        if (! $whatsAppAccount->isActive()) {
            Log::error('WhatsApp account is not active', [
                'template_id' => $template->id,
                'whatsapp_account_id' => $whatsAppAccount->id,
                'status' => $whatsAppAccount->status->value,
            ]);

            return ['success' => false, 'error' => 'WhatsApp account is not active'];
        }

        if (! $whatsAppAccount->isVerified()) {
            Log::error('WhatsApp account is not verified', [
                'template_id' => $template->id,
                'whatsapp_account_id' => $whatsAppAccount->id,
                'verification_status' => $whatsAppAccount->verification_status->value,
            ]);

            return ['success' => false, 'error' => 'WhatsApp account is not verified'];
        }

        $subaccountClient = new Client(
            $whatsAppAccount->twilio_account_sid,
            $whatsAppAccount->twilio_auth_token
        );

        try {
            $twilioTemplate = $subaccountClient->conversations->v1->configuration
                ->templates
                ->whatsappTemplates
                ->create([
                    'friendlyName' => $template->display_name,
                    'language' => 'en',
                    'body' => $template->body,
                    'category' => strtoupper($template->category),
                ]);

            $template->update([
                'twilio_template_sid' => $twilioTemplate->sid,
                'status' => 'pending',
            ]);

            Log::info('Template submitted for verification', [
                'template_id' => $template->id,
                'twilio_sid' => $twilioTemplate->sid,
            ]);

            return ['success' => true, 'status' => 'pending', 'sid' => $twilioTemplate->sid];
        } catch (RestException $e) {
            Log::error('Failed to submit template for verification', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        if (! $template->twilio_template_sid) {
            return;
        }

        $whatsAppAccount = WhatsAppAccount::where('gym_id', $template->gym_id)->first();

        if (! $whatsAppAccount || ! $whatsAppAccount->isActive()) {
            return;
        }

        $subaccountClient = new Client(
            $whatsAppAccount->twilio_account_sid,
            $whatsAppAccount->twilio_auth_token
        );

        try {
            $twilioTemplate = $subaccountClient->conversations->v1->configuration
                ->templates
                ->whatsappTemplates($template->twilio_template_sid)
                ->fetch();

            $statusMap = [
                'PENDING' => 'pending',
                'APPROVED' => 'approved',
                'REJECTED' => 'rejected',
                'DISABLED' => 'disabled',
            ];

            $newStatus = $statusMap[$twilioTemplate->status] ?? $template->status;

            $update = ['status' => $newStatus];

            if ($twilioTemplate->status === 'REJECTED') {
                $update['rejection_reason'] = $twilioTemplate->rejectionReason ?? 'No reason provided';
            }

            $template->update($update);

            Log::info('Template status synced', [
                'template_id' => $template->id,
                'new_status' => $newStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync template status', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getApprovedTemplates(int $gymId): \Illuminate\Database\Eloquent\Collection
    {
        return WhatsAppTemplate::where('gym_id', $gymId)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();
    }

    public function validateVariables(WhatsAppTemplate $template, array $variables): bool
    {
        $requiredVariables = $template->variable_count;
        $providedVariables = count($variables);

        return $providedVariables >= $requiredVariables;
    }

    public function parseVariables(string $body, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $body;
    }

    protected function extractVariables(string $body): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        $variables = [];
        $usedNumbers = [];

        foreach ($matches[1] as $number) {
            if (! in_array($number, $usedNumbers)) {
                $usedNumbers[] = $number;
                $variables[] = [
                    'name' => 'variable_' . $number,
                    'format' => 'text',
                    'example' => 'Example ' . $number,
                ];
            }
        }

        return $variables;
    }

    protected function generateDefaultPlaceholders(array $variables): array
    {
        $placeholders = [];
        $defaultLabels = [
            '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
            '2' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text', 'source' => 'gym.name'],
            '3' => ['placeholder' => 'Date', 'name' => 'date', 'example' => 'January 15, 2026', 'type' => 'text', 'source' => 'member.membership_end'],
            '4' => ['placeholder' => 'Amount', 'name' => 'amount', 'example' => '₹1,499', 'type' => 'number', 'source' => 'payment.formatted_amount'],
            '5' => ['placeholder' => 'Time', 'name' => 'time', 'example' => '7:00 AM', 'type' => 'text', 'source' => 'custom'],
            '6' => ['placeholder' => 'Plan Name', 'name' => 'plan_name', 'example' => 'Premium Plan', 'type' => 'text', 'source' => 'plan.name'],
        ];

        foreach ($variables as $variable) {
            if (preg_match('/variable_(\d+)/', $variable['name'], $matches)) {
                $num = $matches[1];
                $placeholders[$num] = $defaultLabels[$num] ?? [
                    'placeholder' => 'Variable ' . $num,
                    'name' => 'variable_' . $num,
                    'example' => 'Example ' . $num,
                    'type' => 'text',
                    'source' => 'custom',
                ];
            }
        }

        return $placeholders;
    }
}
