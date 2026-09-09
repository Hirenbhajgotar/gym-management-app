<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\WhatsAppTemplates\CreateTemplateData;
use App\DTOs\WhatsAppTemplates\UpdateTemplateData;
use App\Enums\TemplateCategoryEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Services\Meta\MetaTemplateService;
use App\Services\Support\TemplateVariableHelper;
use App\Services\Twilio\TwilioTemplateService;
use App\Services\WhatsApp\TemplateFieldMapper;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class WhatsAppTemplateService
{
    public function createDefaultTemplates(int $gymId): void
    {
        $existingNames = WhatsAppTemplate::query()
            ->where('gym_id', $gymId)
            ->pluck('name')
            ->toArray();

        $templates = WhatsAppTemplate::getDefaults()->reject(
            function ($template) use ($existingNames): bool {
                /** @var WhatsAppTemplate $template */
                return in_array($template->name, $existingNames, true);
            }
        );

        foreach ($templates as $template) {
            $this->createTemplate($gymId, CreateTemplateData::fromArray($template->toArray()));
        }

        Log::info("Default WhatsApp templates created for gym: {$gymId}", [
            'created_count' => $templates->count(),
            'skipped_count' => WhatsAppTemplate::getDefaults()->count() - $templates->count(),
        ]);
    }

    public function createTemplate(int $gymId, CreateTemplateData $data): WhatsAppTemplate
    {
        $variables = TemplateVariableHelper::extractVariables($data->body);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => $data->name,
            'display_name' => $data->display_name,
            'body' => $data->body,
            'category' => $data->category,
            'language' => $data->language ?? MetaTemplateService::defaultTemplateLanguage(),
            'variables' => $variables,
            'variable_count' => count($variables),
            'variable_placeholder' => $data->variable_placeholder ?? TemplateVariableHelper::generateDefaultPlaceholders($variables),
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_active' => true,
        ]);

        if ($template) {
            $template->is_default = $data->is_default ?? false;
            $template->save();
        }

        return $template;
    }

    public function updateTemplate(WhatsAppTemplate $template, UpdateTemplateData $data): ?WhatsAppTemplate
    {
        $updateData = $data->toArray();

        if (isset($updateData['body'])) {
            $updateData['variables'] = TemplateVariableHelper::extractVariables($updateData['body']);
            $updateData['variable_count'] = count($updateData['variables']);

            if (! isset($updateData['variable_placeholder'])) {
                $updateData['variable_placeholder'] = TemplateVariableHelper::generateDefaultPlaceholders($updateData['variables']);
            }
        }

        $template->update($updateData);

        if ($template && $data->is_default !== null) {
            $template->is_default = $data->is_default;
            $template->save();
        }

        return $template->fresh();
    }

    public function softDeleteTemplate(WhatsAppTemplate $template): bool
    {
        return $template->delete();
    }

    public function restoreTemplate(WhatsAppTemplate $template): bool
    {
        return $template->restore();
    }

    /**
     * @return Collection<int, WhatsAppTemplate>
     */
    public function getApprovedTemplates(int $gymId): Collection
    {
        return WhatsAppTemplate::query()->where('gym_id', $gymId)
            ->where('status', WhatsAppTemplateStatusEnum::APPROVED->value)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();
    }

    public function validateVariables(WhatsAppTemplate $template, array $variables): bool
    {
        return count($variables) >= $template->variable_count;
    }

    /**
     * @deprecated 1.x will be removed in 2.x
     * Use TemplateVariableHelper::parseVariables instead.
     */
    public function parseVariables(string $body, array $variables): string
    {
        return TemplateVariableHelper::parseVariables($body, $variables);
    }

    /**
     * @deprecated 1.x will be removed in 2.x
     * use submitTemplate in TwilioTemplateService instead.
     */
    public function submitForVerification(WhatsAppTemplate $template): array
    {
        $whatsAppAccount = WhatsAppAccount::query()->where('gym_id', $template->gym_id)->first();

        if (! $whatsAppAccount) {
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
            $createResponse = Http::withBasicAuth($sid, $token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://content.twilio.com/v1/Content', [
                    'friendly_name' => $template->display_name,
                    'language' => $template->language ?: TwilioTemplateService::TWILIO_DEFAULT_LANGUAGE,
                    'types' => [
                        'twilio/text' => [
                            'body' => $template->body,
                        ],
                    ],
                ]);

            if ($createResponse->failed()) {
                $error = $createResponse->json('message') ?? $createResponse->body();
                Log::error('Twilio content create failed', ['error' => $error, 'template_id' => $template->id]);

                return ['success' => false, 'error' => 'Template submission failed. Please try again later.'];
            }

            $contentSid = $createResponse->json('sid');

            $approvalResponse = Http::withBasicAuth($sid, $token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://content.twilio.com/v1/Content/{$contentSid}/ApprovalRequests/whatsapp", [
                    'name' => $template->name,
                    'category' => strtoupper($template->category->value ?? TemplateCategoryEnum::UTILITY->value),
                ]);

            if ($approvalResponse->failed()) {
                $error = $approvalResponse->json('message') ?? $approvalResponse->body();
                Log::error('Twilio approval submit failed', ['error' => $error, 'template_id' => $template->id]);

                return ['success' => false, 'error' => 'Template approval failed. Please try again later.'];
            }

            $template->twilio_template_sid = $contentSid;
            $template->status = WhatsAppTemplateStatusEnum::PENDING;
            $template->save();

            return ['success' => true, 'status' => WhatsAppTemplateStatusEnum::PENDING->value, 'sid' => $contentSid];
        } catch (RestException $e) {
            Log::error('Failed to submit template for verification', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Template submission failed. Please try again later.'];
        }
    }

    /**
     * @deprecated 1.x will be removed in 2.x
     * use syncTemplateStatus in TwilioTemplateService instead.
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        if (! $template->twilio_template_sid) {
            return;
        }

        $whatsAppAccount = WhatsAppAccount::query()->where('gym_id', $template->gym_id)->first();

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
                'PENDING' => WhatsAppTemplateStatusEnum::PENDING,
                'APPROVED' => WhatsAppTemplateStatusEnum::APPROVED,
                'REJECTED' => WhatsAppTemplateStatusEnum::REJECTED,
                'DISABLED' => WhatsAppTemplateStatusEnum::DISABLED,
            ];

            $newStatus = $statusMap[$rawStatus] ?? $template->status;

            $template->status = $newStatus;

            if ($rawStatus === WhatsAppTemplateStatusEnum::REJECTED->value) {
                $template->rejection_reason = $whatsapp['rejection_reason'] ?? 'No reason provided';
            }

            $template->save();

            Log::info('Template status synced', [
                'template_id' => $template->id,
                'new_status' => $newStatus,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to sync template status', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @deprecated 1.x will be removed in 2.x
     * use deleteFromTwilio in TwilioTemplateService instead.
     */
    public function deleteFromTwilio(WhatsAppTemplate $template): bool
    {
        if (! $template->twilio_template_sid) {
            return false;
        }

        $whatsAppAccount = WhatsAppAccount::query()->where('gym_id', $template->gym_id)->first();

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
        } catch (Exception $e) {
            Log::error('Failed to delete Twilio WhatsApp template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @deprecated 1.x will be removed in 2.x
     * Use extractVariables in TemplateVariableHelper instead.
     */
    protected function extractVariables(string $body): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        $variables = [];
        $usedNumbers = [];

        foreach ($matches[1] as $number) {
            if (! in_array($number, $usedNumbers, true)) {
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

    /**
     * @deprecated 1.x will be removed in 2.x
     * Use generateDefaultPlaceholders in TemplateVariableHelper instead.
     */
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

    /**
     * @deprecated 1.x will be removed in 2.x
     * Use syncTemplatesFromTwilioMasterAccount in TwilioTemplateService instead.
     */
    public function syncTemplatesFromTwilio(): array
    {
        $masterAccountSid = config('services.twilio.master_sid');
        $masterAuthToken = config('services.twilio.master_token');

        if (! $masterAccountSid || ! $masterAuthToken) {
            return [
                'success' => false,
                'error' => 'Twilio credentials not configured',
                'count' => 0,
            ];
        }

        $masterClient = new Client($masterAccountSid, $masterAuthToken);

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        try {
            $contents = $masterClient->content->v1->contents->read();

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

                    $whatsapp = [];
                    try {
                        $approvalFetch = $masterClient->content->v1->contents($record->sid)->approvalFetch()->fetch();
                        $whatsapp = $approvalFetch->whatsapp ?? [];
                    } catch (Exception $e) {
                        Log::warning("Could not fetch approval status for {$record->sid}: " . $e->getMessage());
                    }

                    $rawStatus = strtoupper($whatsapp['status'] ?? 'PENDING');

                    $status = $statusMap[$rawStatus] ?? WhatsAppTemplateStatusEnum::PENDING;

                    if ($status !== WhatsAppTemplateStatusEnum::APPROVED) {
                        continue;
                    }

                    $category = strtolower($whatsapp['category'] ?? TemplateCategoryEnum::UTILITY->value);
                    $rejectionReason = $whatsapp['rejection_reason'] ?? null;

                    $attributes = [
                        'twilio_template_sid' => $record->sid,
                        'gym_id' => null,
                    ];

                    $values = [
                        'name' => $record->friendlyName,
                        'display_name' => ucwords(str_replace(['_', '-'], ' ', $record->friendlyName)),
                        'body' => $body,
                        'category' => $category,
                        'language' => TemplateFieldMapper::sanitizeLanguage($record->language ?? null, WhatsAppProviderTypeEnum::TWILIO),
                        'variables' => $variables,
                        'variable_count' => $variableCount,
                        'variable_placeholder' => $placeholders,
                        'status' => $status,
                        'rejection_reason' => $rejectionReason,
                        'is_active' => true,
                        'is_default' => true,
                    ];

                    try {
                        $templateRecord = WhatsAppTemplate::query()->updateOrCreate($attributes, $values);
                    } catch (UniqueConstraintViolationException $e) {
                        $templateRecord = WhatsAppTemplate::query()->where($attributes)->first();
                    } catch (QueryException $e) {
                        if ($e->getCode() === '23000') {
                            $templateRecord = WhatsAppTemplate::query()->where($attributes)->first();
                        } else {
                            throw $e;
                        }
                    }

                    if ($templateRecord) {
                        $templateRecord->twilio_template_sid = $record->sid;
                        $templateRecord->rejection_reason = $rejectionReason;
                        $templateRecord->is_default = true;
                        $templateRecord->save();
                    }

                    $successCount++;
                } catch (Exception $e) {
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
        } catch (Exception $e) {
            Log::error('Failed to sync WhatsApp templates from Twilio', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
