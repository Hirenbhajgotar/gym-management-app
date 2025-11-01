<?php

namespace App\Services;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\TenantSocialConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

abstract class SocialMediaLeadProcessor
{
    protected string $platform;

    protected array $config;

    protected Gym $gym;

    public function __construct(string $platform)
    {
        $this->platform = $platform;
    }

    /**
     * Process incoming lead data from social media platform
     */
    abstract public function processLead(array $data): ?Lead;

    /**
     * Verify webhook signature/token
     */
    abstract public function verifyWebhook(array $data, array $headers): bool;

    /**
     * Extract lead data from platform-specific format
     */
    abstract protected function extractLeadData(array $data): array;

    /**
     * Get tenant configuration for the platform
     */
    protected function getTenantConfig(string $identifier): ?TenantSocialConfig
    {
        return TenantSocialConfig::where('platform', $this->platform)
            ->where(function ($query) use ($identifier) {
                $query->where('page_id', $identifier)
                    ->orWhere('form_id', $identifier)
                    ->orWhere('phone_number_id', $identifier);
            })
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create or update lead from extracted data
     */
    protected function createLead(array $leadData): ?Lead
    {
        try {
            // Validate required fields
            $validator = Validator::make($leadData, [
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'gym_id' => 'required|exists:gyms,id',
            ]);

            if ($validator->fails()) {
                Log::error('Lead validation failed', [
                    'platform' => $this->platform,
                    'errors' => $validator->errors()->toArray(),
                    'data' => $leadData,
                ]);

                return null;
            }

            // Check for duplicate leads (same email or phone within 30 days)
            $existingLead = Lead::where('gym_id', $leadData['gym_id'])
                ->where(function ($query) use ($leadData) {
                    if (! empty($leadData['email'])) {
                        $query->where('email', $leadData['email']);
                    }
                    if (! empty($leadData['phone'])) {
                        $query->orWhere('phone', $leadData['phone']);
                    }
                })
                ->where('created_at', '>=', now()->subDays(30))
                ->first();

            if ($existingLead) {
                Log::info('Duplicate lead detected', [
                    'platform' => $this->platform,
                    'existing_lead_id' => $existingLead->id,
                    'data' => $leadData,
                ]);

                // Update existing lead with new information
                $existingLead->update([
                    'last_contacted' => now(),
                    'notes' => $this->mergeNotes($existingLead->notes, $leadData['notes'] ?? ''),
                ]);

                return $existingLead;
            }

            // Create new lead
            $lead = Lead::create([
                'name' => $leadData['name'],
                'email' => $leadData['email'] ?? null,
                'phone' => $this->formatPhoneNumber($leadData['phone'] ?? ''),
                'status' => LeadStatus::NEW,
                'source' => $this->mapPlatformToLeadSource(),
                'gym_id' => $leadData['gym_id'],
                'notes' => $leadData['notes'] ?? '',
                'last_contacted' => now(),
                'raw_data' => $leadData['metadata'] ?? [],
                'created_by' => 1, // Placeholder for system user, assuming ID 1
            ]);

            Log::info('Lead created from social media', [
                'platform' => $this->platform,
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
            ]);

            // Trigger lead created event for notifications
            event(new \App\Events\LeadCreated($lead));

            return $lead;
        } catch (\Exception $e) {
            Log::error('Failed to create lead from social media', [
                'platform' => $this->platform,
                'error' => $e->getMessage(),
                'data' => $leadData,
            ]);

            return null;
        }
    }

    /**
     * Map platform name to LeadSource enum
     */
    protected function mapPlatformToLeadSource(): LeadSource
    {
        return match ($this->platform) {
            'facebook' => LeadSource::FACEBOOK,
            'instagram' => LeadSource::INSTAGRAM,
            'whatsapp' => LeadSource::WHATSAPP,
            'indiamart' => LeadSource::INDIAMART,
            'justdial' => LeadSource::JUSTDIAL,
            default => LeadSource::SOCIAL_MEDIA,
        };
    }

    /**
     * Format phone number to standard format
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if not present (assuming India)
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        return $phone;
    }

    /**
     * Merge notes without duplication
     */
    protected function mergeNotes(?string $existingNotes, string $newNotes): string
    {
        if (empty($newNotes)) {
            return $existingNotes ?? '';
        }

        if (empty($existingNotes)) {
            return $newNotes;
        }

        // Check if new notes already exist in existing notes
        if (str_contains($existingNotes, $newNotes)) {
            return $existingNotes;
        }

        return $existingNotes . "\n\n--- New from {$this->platform} ---\n" . $newNotes;
    }

    /**
     * Log webhook processing
     */
    protected function logWebhook(array $data, string $status, ?string $error = null): void
    {
        // Only log essential data to prevent "Over 9 levels deep" errors
        $logData = [
            'platform' => $this->platform,
            'status' => $status,
            'data_summary' => $this->getDataSummary($data),
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ];

        Log::channel('social-media-leads')->info('Webhook processed', $logData);
    }

    /**
     * Get a summary of the data to prevent logging huge nested arrays
     */
    protected function getDataSummary(array $data): array
    {
        $summary = [];

        // Extract key fields that are safe to log
        if (isset($data['entry'])) {
            $summary['entry_count'] = count($data['entry']);
        }

        if (isset($data['object'])) {
            $summary['object'] = $data['object'];
        }

        if (isset($data['field'])) {
            $summary['field'] = $data['field'];
        }

        // Add any other safe-to-log fields
        $safeFields = ['id', 'name', 'email', 'phone', 'form_id', 'page_id', 'leadgen_id'];
        foreach ($safeFields as $field) {
            if (isset($data[$field])) {
                $summary[$field] = is_string($data[$field]) ? substr($data[$field], 0, 100) : $data[$field];
            }
        }

        return $summary;
    }
}
