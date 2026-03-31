<?php

namespace App\Services;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class WhatsAppTemplateService
{
    private Client $masterClient;

    private ?string $masterAccountSid;

    private ?string $masterAuthToken;

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

        $sid = $whatsAppAccount->twilio_account_sid;
        $token = $whatsAppAccount->twilio_auth_token;

        try {
            // Step 1: Create content template
            $createResponse = Http::withBasicAuth($sid, $token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://content.twilio.com/v1/Content', [
                    'friendly_name' => $template->display_name,
                    'language' => 'en',
                    'types' => [
                        'twilio/text' => [
                            'body' => $template->body,
                        ],
                    ],
                ]);

            if ($createResponse->failed()) {
                $error = $createResponse->json('message') ?? $createResponse->body();
                Log::error('Twilio content create failed', ['error' => $error, 'template_id' => $template->id]);

                return ['success' => false, 'error' => $error];
            }

            $contentSid = $createResponse->json('sid');

            // Step 2: Submit for WhatsApp approval
            $approvalResponse = Http::withBasicAuth($sid, $token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://content.twilio.com/v1/Content/{$contentSid}/ApprovalRequests/whatsapp", [
                    'name' => $template->name, // lowercase_underscored
                    'category' => strtoupper($template->category ?? 'UTILITY'),
                ]);

            if ($approvalResponse->failed()) {
                $error = $approvalResponse->json('message') ?? $approvalResponse->body();
                Log::error('Twilio approval submit failed', ['error' => $error, 'template_id' => $template->id]);

                return ['success' => false, 'error' => $error];
            }

            $template->update([
                'twilio_template_sid' => $contentSid,
                'status' => 'pending',
            ]);

            Log::info('Template submitted for verification', [
                'template_id' => $template->id,
                'twilio_sid' => $contentSid,
            ]);

            return ['success' => true, 'status' => 'pending', 'sid' => $contentSid];
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

        $sid = $whatsAppAccount->twilio_account_sid;
        $token = $whatsAppAccount->twilio_auth_token;

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->get("https://content.twilio.com/v1/Content/{$template->twilio_template_sid}/ApprovalRequests");

            if ($response->failed()) {
                Log::error('Failed to fetch template approval status', [
                    'template_id' => $template->id,
                    'error' => $response->json('message') ?? $response->body(),
                ]);

                return;
            }

            $data = $response->json();
            $whatsapp = $data['whatsapp'] ?? null;
            $rawStatus = strtoupper($whatsapp['status'] ?? '');

            $statusMap = [
                'PENDING' => 'pending',
                'APPROVED' => 'approved',
                'REJECTED' => 'rejected',
                'DISABLED' => 'disabled',
            ];

            $newStatus = $statusMap[$rawStatus] ?? $template->status;

            $update = ['status' => $newStatus];

            if ($rawStatus === WhatsAppTemplateStatusEnum::REJECTED->value) {
                $update['rejection_reason'] = $whatsapp['rejection_reason'] ?? 'No reason provided';
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

    public function deleteFromTwilio(WhatsAppTemplate $template): bool
    {
        if (! $template->twilio_template_sid) {
            return false;
        }

        $whatsAppAccount = WhatsAppAccount::where('gym_id', $template->gym_id)->first();

        if (! $whatsAppAccount) {
            return false;
        }

        if (! $whatsAppAccount->twilio_account_sid || ! $whatsAppAccount->twilio_auth_token) {
            return false;
        }

        try {
            $subaccountClient = new Client(
                $whatsAppAccount->twilio_account_sid,
                $whatsAppAccount->twilio_auth_token
            );

            $subaccountClient->content->v1->contents($template->twilio_template_sid)
                ->delete();

            Log::info('Deleted Twilio WhatsApp template', [
                'template_id' => $template->id,
                'twilio_sid' => $template->twilio_template_sid,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete Twilio WhatsApp template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function softDeleteTemplate(WhatsAppTemplate $template): bool
    {
        Log::info('Soft deleting WhatsApp template', ['template_id' => $template->id]);

        return $template->delete();
    }

    public function restoreTemplate(WhatsAppTemplate $template): bool
    {
        Log::info('Restoring WhatsApp template', ['template_id' => $template->id]);

        return $template->restore();
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

    public function syncTemplatesFromTwilio(): array
    {
        Log::info('Starting WhatsApp template sync from Twilio main account');
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        try {
            $contents = $this->masterClient->content->v1->contents->read();

            $statusMap = [
                'PENDING' => WhatsAppTemplateStatusEnum::PENDING,
                'APPROVED' => WhatsAppTemplateStatusEnum::APPROVED,
                'REJECTED' => WhatsAppTemplateStatusEnum::REJECTED,
                'DISABLED' => WhatsAppTemplateStatusEnum::DISABLED,
                'UNAPPROVED' => WhatsAppTemplateStatusEnum::PENDING,
                'PAUSED' => WhatsAppTemplateStatusEnum::DISABLED,
                'UNSUBMITTED' => WhatsAppTemplateStatusEnum::UNSUBMITTED,
            ];

            foreach ($contents as $record) {
                try {
                    $types = $record->types;

                    // Extract body from supported types
                    $body = null;
                    if (isset($types['twilio/text'])) {
                        $body = $types['twilio/text']['body'];
                    } elseif (isset($types['twilio/media'])) {
                        $body = $types['twilio/media']['body'];
                    } elseif (isset($types['twilio/location'])) {
                        $body = $types['twilio/location']['body'];
                    } elseif (isset($types['twilio/card'])) {
                        $body = $types['twilio/card']['body'];
                    }

                    if (! $body) {
                        continue;
                    }

                    $variables = $this->extractVariables($body);
                    $variableCount = count($variables);
                    $placeholders = $this->generateDefaultPlaceholders($variables);

                    // Fetch approval status to get correct category and status
                    $whatsapp = [];
                    try {
                        $approvalFetch = $this->masterClient->content->v1->contents($record->sid)->approvalFetch()->fetch();
                        $whatsapp = $approvalFetch->whatsapp ?? [];
                    } catch (\Exception $e) {
                        Log::warning("Could not fetch approval status for {$record->sid}: " . $e->getMessage());
                    }

                    $rawStatus = strtoupper($whatsapp['status'] ?? 'PENDING');

                    $status = $statusMap[$rawStatus] ?? WhatsAppTemplateStatusEnum::PENDING;

                    if ($status !== WhatsAppTemplateStatusEnum::APPROVED) {
                        continue;
                    }

                    $category = strtolower($whatsapp['category'] ?? 'utility');
                    $rejectionReason = $whatsapp['rejection_reason'] ?? null;

                    $attributes = [
                        'twilio_template_sid' => $record->sid,
                        'gym_id' => null,
                    ];

                    $values = [
                        'name' => $record->friendlyName, // Twilio friendly name as internal name
                        'display_name' => ucwords(str_replace(['_', '-'], ' ', $record->friendlyName)),
                        'body' => $body,
                        'category' => $category,
                        'variables' => $variables,
                        'variable_count' => $variableCount,
                        'variable_placeholder' => $placeholders,
                        'status' => $status,
                        'rejection_reason' => $rejectionReason,
                        'is_active' => true,
                        'is_default' => true,
                    ];

                    try {
                        WhatsAppTemplate::updateOrCreate($attributes, $values);
                    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                        // Concurrent sync caused a duplicate key error, fallback to explicit update
                        WhatsAppTemplate::where($attributes)->update($values);
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Fallback constraint violation check
                        if ($e->getCode() === '23000') {
                            WhatsAppTemplate::where($attributes)->update($values);
                        } else {
                            throw $e;
                        }
                    }

                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Error syncing template {$record->sid}: " . $e->getMessage();
                    Log::error("Error syncing Twilio template {$record->sid}", ['error' => $e->getMessage()]);
                }
            }

            return [
                'success' => true,
                'synced_count' => $successCount,
                'error_count' => $errorCount,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to sync WhatsApp templates from Twilio', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
