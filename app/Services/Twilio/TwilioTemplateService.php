<?php

namespace App\Services\Twilio;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Interfaces\TemplateManagementInterface;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Services\Support\TemplateVariableHelper;
use App\Services\WhatsAppTemplateService as BaseTemplateService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

/**
 * Twilio Template Service.
 *
 * Implements TemplateManagementInterface for template operations.
 * Uses TwilioContext for consistent dependency injection pattern.
 */
class TwilioTemplateService implements TemplateManagementInterface
{
    private const TEMPLATES_CACHE_TTL = 300;

    private const TEMPLATES_CACHE_STALE = 3600;

    private const LOCK_TTL = 30;

    private const SYNC_LOCK_TTL = 300; // master sync can take longer

    public function __construct(
        protected TwilioContext $context,
        protected BaseTemplateService $templateService,
    ) {}

    /**
     * Create a new template.
     *
     * Template creation is provider-agnostic (DB storage only),
     * so we delegate to the base service.
     */
    public function createTemplate(int $gymId, array $data): WhatsAppTemplate
    {
        return $this->templateService->createTemplate($gymId, $data);
    }

    /**
     * Submit template for verification.
     */
    public function submitTemplate(?WhatsAppTemplate $template = null): array
    {
        if (! $template) {
            return ['success' => false, 'error' => 'Template no longer exists.'];
        }

        $account = $this->context->getAccount();

        if (! $account || ! $account->isActive() || ! $account->isVerified()) {
            $reason = match (true) {
                ! $account => 'WhatsApp account not configured for this gym',
                ! $account->isActive() => 'WhatsApp account is not active',
                default => 'WhatsApp account is not verified',
            };

            return ['success' => false, 'error' => $reason];
        }

        $lock = Cache::lock("twilio_template_submit:{$template->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            return ['success' => false, 'error' => 'Template submission already in progress'];
        }

        $template = $template->fresh();

        if ($template->status === WhatsAppTemplateStatusEnum::PENDING) {
            return ['success' => false, 'error' => 'Template is already pending verification.'];
        }

        try {
            return $this->processTwilioSubmission($template, $account);
        } catch (RestException $e) {
            Log::error('Twilio REST exception during template submit', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Sync template status from Twilio.
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        if ($template->gym_id !== $this->context->gymId) {
            Log::warning('Template gym_id mismatch in syncTemplateStatus', [
                'template_id' => $template->id,
                'expected_gym' => $this->context->gymId,
                'actual_gym' => $template->gym_id,
            ]);

            return;
        }

        if (! $template->twilio_template_sid) {
            return;
        }

        $account = $this->context->getAccount();

        if (! $account || ! $account->isActive()) {
            return;
        }

        $lock = Cache::lock("twilio_template_sync:{$template->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            return;
        }

        try {
            $response = Http::withBasicAuth($account->twilio_account_sid, $account->twilio_auth_token)
                ->get("{$this->context->getApiBaseUrlv1()}/Content/{$template->twilio_template_sid}/ApprovalRequests");

            if ($response->failed()) {
                Log::error('Failed to fetch template approval status', [
                    'template_id' => $template->id,
                    'error' => $response->json('message') ?? $response->body(),
                ]);

                return;
            }

            $whatsapp = $response->json('whatsapp', []);
            $rawStatus = strtoupper($whatsapp['status'] ?? '');
            $newStatus = $this->mapStatus($rawStatus);

            if (! $newStatus) {
                return;
            }

            $template->status = $newStatus;
            if ($newStatus === WhatsAppTemplateStatusEnum::REJECTED) {
                $template->rejection_reason = $whatsapp['rejection_reason'] ?? 'No reason provided';
            }

            $template->save();
        } catch (LockTimeoutException $e) {
            // Skip
        } catch (\Throwable $e) {
            Log::error('Failed to sync template status', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Get all available templates from Twilio.
     *
     * Fetches templates directly from Twilio API using context credentials.
     */
    public function getTemplates(int $gymId): array
    {
        $account = $this->context->getAccount();

        if (! $account?->twilio_account_sid || ! $account->twilio_auth_token) {
            return ['success' => false, 'error' => 'Twilio credentials not configured', 'templates' => []];
        }

        try {
            $templates = Cache::flexible($this->templatesCacheKey($gymId), [self::TEMPLATES_CACHE_TTL, self::TEMPLATES_CACHE_STALE], function () use ($account) {
                $response = Http::withBasicAuth($account->twilio_account_sid, $account->twilio_auth_token)
                    ->get("{$this->context->getApiBaseUrlv1()}/Contents");

                if ($response->failed()) {
                    throw new \RuntimeException($response->json('message') ?? 'Failed to fetch');
                }

                return $response->json('contents', []);
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch fresh Twilio templates, falling back to cache.', ['error' => $e->getMessage()]);
            $templates = Cache::get($this->templatesCacheKey($gymId));
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

    // -------------------------------------------------------------------------
    // Additional Twilio-specific operations
    // -------------------------------------------------------------------------

    /**
     * Delete a template from Twilio Content API.
     */
    public function deleteFromTwilio(WhatsAppTemplate $template): bool
    {
        if (! $template->twilio_template_sid) {
            return false;
        }

        $account = $this->context->getAccount();

        if (! $account?->twilio_account_sid || ! $account->twilio_auth_token) {
            return false;
        }

        try {
            $client = $this->context->getClientForAccount($account);
            $client->content->v1->contents($template->twilio_template_sid)->delete();

            // Bust cache so deleted template doesn't show up in next getTemplates()
            Cache::forget($this->templatesCacheKey($this->context->gymId));

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to delete Twilio WhatsApp template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync all approved templates from the Twilio master account into the DB.
     * Typically run as a scheduled command.
     */
    public function syncTemplatesFromTwilioMasterAccount(): array
    {
        $lock = Cache::lock('twilio_master_template_sync', self::SYNC_LOCK_TTL);

        if (! $lock->get()) {
            return ['success' => false, 'error' => 'Sync already in progress'];
        }

        try {
            return $this->processMasterSync();
        } catch (LockTimeoutException $e) {
            return ['success' => false, 'error' => 'Master template sync is already in progress.'];
        } catch (\Exception $e) {
            Log::error('Failed to sync WhatsApp templates from Twilio', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            optional($lock)->release();
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function processTwilioSubmission(WhatsAppTemplate $template, WhatsAppAccount $account): array
    {
        // Step 1: Create content template
        $createResponse = Http::withBasicAuth($account->twilio_account_sid, $account->twilio_auth_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->context->getApiBaseUrlv1()}/Content", [
                'friendly_name' => $template->display_name,
                'language' => 'en',
                'types' => [
                    'twilio/text' => ['body' => $template->body],
                ],
            ]);

        if ($createResponse->failed()) {
            $error = $createResponse->json('message') ?? $createResponse->body();
            Log::error('Twilio content create failed', ['error' => $error, 'template_id' => $template->id]);

            return ['success' => false, 'error' => $error];
        }

        $contentSid = (string) $createResponse->json('sid');

        // Step 2: Submit for WhatsApp approval
        $approvalResponse = Http::withBasicAuth($account->twilio_account_sid, $account->twilio_auth_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->context->getApiBaseUrlv1()}/Content/{$contentSid}/ApprovalRequests/whatsapp", [
                'name' => $template->name,
                'category' => strtoupper($template->category ?? 'UTILITY'),
            ]);

        if ($approvalResponse->failed()) {
            $error = $approvalResponse->json('message') ?? $approvalResponse->body();
            Log::error('Twilio approval submit failed', ['error' => $error, 'template_id' => $template->id]);

            return ['success' => false, 'error' => $error];
        }

        $template->twilio_template_sid = $contentSid;
        $template->status = WhatsAppTemplateStatusEnum::PENDING;
        $template->save();

        return ['success' => true, 'status' => WhatsAppTemplateStatusEnum::PENDING->value, 'sid' => $contentSid];
    }

    private function processMasterSync(): array
    {
        $statusMap = [
            'PENDING' => WhatsAppTemplateStatusEnum::PENDING,
            'APPROVED' => WhatsAppTemplateStatusEnum::APPROVED,
            'REJECTED' => WhatsAppTemplateStatusEnum::REJECTED,
            'DISABLED' => WhatsAppTemplateStatusEnum::DISABLED,
            'UNAPPROVED' => WhatsAppTemplateStatusEnum::PENDING,
            'PAUSED' => WhatsAppTemplateStatusEnum::DISABLED,
            'UNSUBMITTED' => WhatsAppTemplateStatusEnum::UNSUBMITTED,
        ];

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        try {
            $masterClient = $this->context->getMasterClient();

            foreach ($masterClient->content->v1->contents->read() as $record) {
                try {
                    $body = TemplateVariableHelper::extractBodyFromTypes($record->types);

                    if (! $body) {
                        continue;
                    }

                    $whatsapp = $this->fetchApprovalStatus($masterClient, $record->sid);
                    $rawStatus = strtoupper($whatsapp['status'] ?? 'PENDING');
                    $status = $statusMap[$rawStatus] ?? WhatsAppTemplateStatusEnum::PENDING;

                    // Only sync approved templates as system defaults
                    if ($status !== WhatsAppTemplateStatusEnum::APPROVED) {
                        continue;
                    }

                    $variables = TemplateVariableHelper::extractVariables($body);
                    $placeholders = TemplateVariableHelper::generateDefaultPlaceholders($variables);

                    $this->upsertTemplate(
                        ['twilio_template_sid' => $record->sid, 'gym_id' => null],
                        [
                            'name' => $record->friendlyName,
                            'display_name' => ucwords(str_replace(['_', '-'], ' ', $record->friendlyName)),
                            'body' => $body,
                            'category' => strtolower($whatsapp['category'] ?? 'utility'),
                            'variables' => $variables,
                            'variable_count' => count($variables),
                            'variable_placeholder' => $placeholders,
                            'status' => $status,
                            'rejection_reason' => $whatsapp['rejection_reason'] ?? null,
                            'is_active' => true,
                            'is_default' => true,
                        ]
                    );

                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Error syncing {$record->sid}: {$e->getMessage()}";
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

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function fetchApprovalStatus(Client $masterClient, string $sid): array
    {
        try {
            $approval = $masterClient->content->v1->contents($sid)->approvalFetch()->fetch();

            return $approval->whatsapp ?? [];
        } catch (\Exception $e) {
            Log::warning("Could not fetch approval status for {$sid}: {$e->getMessage()}");

            return [];
        }
    }

    private function upsertTemplate(array $attributes, array $values): void
    {
        // Separate fillable fields (user/system data) from system fields
        $fillableValues = array_intersect_key($values, array_flip([
            'name', 'display_name', 'body', 'category',
            'variables', 'variable_count', 'variable_placeholder',
            'status', 'is_active',
        ]));
        $systemFields = array_diff_key($values, $fillableValues);

        try {
            $record = WhatsAppTemplate::updateOrCreate($attributes, $fillableValues);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $record = WhatsAppTemplate::where($attributes)->first();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                $record = WhatsAppTemplate::where($attributes)->first();
            } else {
                throw $e;
            }
        }

        // System fields updated explicitly (not mass-assignment)
        if ($record && ! empty($systemFields)) {
            foreach ($systemFields as $attribute => $value) {
                $record->{$attribute} = $value;
            }
            $record->save();
        }
    }

    private function mapStatus(string $rawStatus): ?WhatsAppTemplateStatusEnum
    {
        return match ($rawStatus) {
            'PENDING' => WhatsAppTemplateStatusEnum::PENDING,
            'APPROVED' => WhatsAppTemplateStatusEnum::APPROVED,
            'REJECTED' => WhatsAppTemplateStatusEnum::REJECTED,
            'DISABLED' => WhatsAppTemplateStatusEnum::DISABLED,
            default => null,
        };
    }

    private function templatesCacheKey(int $gymId): string
    {
        return "twilio_templates:gym:{$gymId}";
    }
}
