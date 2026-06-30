<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\TenantSocialConfig;
use Exception;
use Illuminate\Support\Facades\Log;

class IndiaMartLeadProcessor extends SocialMediaLeadProcessor
{
    public function __construct()
    {
        parent::__construct('indiamart');
    }

    /**
     * Process IndiaMart lead data
     */
    public function processLead(array $data): ?Lead
    {
        try {
            $this->logWebhook($data, 'processing');

            // Extract lead data from IndiaMart format
            $leadData = $this->extractLeadData($data);

            if (blank($leadData)) {
                Log::warning('No lead data extracted from IndiaMart webhook', ['data' => $data]);

                return null;
            }

            // Get tenant configuration
            $config = $this->getTenantConfig($data['glusr_crm_key'] ?? $data['user_id'] ?? '');
            if (! $config) {
                Log::warning('No tenant configuration found for IndiaMart account', ['data' => $data]);

                return null;
            }

            // Set gym context
            $leadData['gym_id'] = $config->gym_id;

            // Create lead
            $lead = $this->createLead($leadData);

            if ($lead) {
                $this->logWebhook($data, 'success');

                // Send acknowledgment if configured
                $this->sendAcknowledgment($lead, $config);
            } else {
                $this->logWebhook($data, 'failed', 'Lead creation failed');
            }

            return $lead;
        } catch (Exception $e) {
            Log::error('IndiaMart lead processing failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            $this->logWebhook($data, 'error', $e->getMessage());

            return null;
        }
    }

    /**
     * Verify IndiaMart webhook signature
     */
    public function verifyWebhook(array $data, array $headers): bool
    {
        try {
            // IndiaMart uses API key verification
            $apiKey = $headers['x-api-key'][0] ?? '';
            if (blank($apiKey)) {
                return false;
            }

            // Get tenant configuration and verify API key
            $userId = $data['glusr_crm_key'] ?? $data['user_id'] ?? '';
            if (blank($userId)) {
                return false;
            }

            $config = $this->getTenantConfig($userId);
            if (! $config) {
                return false;
            }

            // Verify API key matches
            $expectedApiKey = $config->api_key ?? config('services.indiamart.api_key');

            return $apiKey === $expectedApiKey;
        } catch (Exception $e) {
            Log::error('IndiaMart webhook verification failed', [
                'error' => $e->getMessage(),
                'headers' => $headers,
            ]);

            return false;
        }
    }

    /**
     * Extract lead data from IndiaMart webhook format
     */
    protected function extractLeadData(array $data): array
    {
        try {
            $leadData = [
                'name' => '',
                'email' => '',
                'phone' => '',
                'notes' => '',
                'metadata' => [],
            ];

            // Handle different IndiaMart webhook formats
            if (isset($data['GLUSR_MOBILE'])) {
                // Standard IndiaMart lead format
                return $this->extractStandardLeadData($data);
            } elseif (isset($data['inquiry_details'])) {
                // Alternative format
                return $this->extractAlternativeLeadData($data);
            } elseif (isset($data['leads']) && is_array($data['leads'])) {
                // Batch lead format
                return $this->extractBatchLeadData($data);
            }

            Log::warning('Unknown IndiaMart webhook format', ['data' => $data]);

            return [];
        } catch (Exception $e) {
            Log::error('Failed to extract lead data from IndiaMart', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [];
        }
    }

    /**
     * Extract data from standard IndiaMart lead format
     */
    private function extractStandardLeadData(array $data): array
    {
        $leadData = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'notes' => '',
            'metadata' => [],
        ];

        // Extract basic contact information
        $leadData['name'] = $this->cleanString($data['GLUSR_MOBILE_NAME'] ?? '');
        $leadData['phone'] = $this->cleanPhoneNumber($data['GLUSR_MOBILE'] ?? '');
        $leadData['email'] = $this->cleanString($data['GLUSR_MOBILE_EMAIL'] ?? '');

        // Build detailed notes
        $notes = [];

        // Product information
        if (filled($data['PRODUCT_NAME'])) {
            $notes[] = 'Product: ' . $this->cleanString($data['PRODUCT_NAME']);
        }

        if (filled($data['PRODUCT_URL'])) {
            $notes[] = 'Product URL: ' . $this->cleanString($data['PRODUCT_URL']);
        }

        if (filled($data['PRODUCT_IMAGE'])) {
            $notes[] = 'Product Image: ' . $this->cleanString($data['PRODUCT_IMAGE']);
        }

        // Inquiry details
        if (filled($data['QUERY_MESSAGE'])) {
            $notes[] = 'Inquiry: ' . $this->cleanString($data['QUERY_MESSAGE']);
        }

        if (filled($data['QUERY_TYPE'])) {
            $notes[] = 'Query Type: ' . $this->cleanString($data['QUERY_TYPE']);
        }

        if (filled($data['QUERY_MCAT_NAME'])) {
            $notes[] = 'Category: ' . $this->cleanString($data['QUERY_MCAT_NAME']);
        }

        // Location information
        if (filled($data['SENDER_COMPANY'])) {
            $notes[] = 'Company: ' . $this->cleanString($data['SENDER_COMPANY']);
        }

        if (filled($data['SENDER_ADDRESS'])) {
            $notes[] = 'Address: ' . $this->cleanString($data['SENDER_ADDRESS']);
        }

        if (filled($data['SENDER_CITY'])) {
            $notes[] = 'City: ' . $this->cleanString($data['SENDER_CITY']);
        }

        if (filled($data['SENDER_STATE'])) {
            $notes[] = 'State: ' . $this->cleanString($data['SENDER_STATE']);
        }

        if (filled($data['SENDER_COUNTRY_ISO'])) {
            $notes[] = 'Country: ' . $this->cleanString($data['SENDER_COUNTRY_ISO']);
        }

        // Quantity and requirements
        if (filled($data['QUERY_QUANTITY'])) {
            $notes[] = 'Quantity: ' . $this->cleanString($data['QUERY_QUANTITY']);
        }

        if (filled($data['QUERY_UNIT'])) {
            $notes[] = 'Unit: ' . $this->cleanString($data['QUERY_UNIT']);
        }

        $leadData['notes'] = implode("\n", $notes);

        // Add metadata
        $leadData['metadata'] = [
            'indiamart_query_id' => $data['QUERY_ID'] ?? '',
            'indiamart_sender_id' => $data['SENDER_GLUSR_USR_ID'] ?? '',
            'indiamart_user_id' => $data['glusr_crm_key'] ?? '',
            'query_type' => $data['QUERY_TYPE'] ?? '',
            'query_mcat_name' => $data['QUERY_MCAT_NAME'] ?? '',
            'call_duration' => $data['CALL_DURATION'] ?? '',
            'receiver_mobile' => $data['RECEIVER_MOBILE'] ?? '',
            'generated_time' => $data['GENERATED_TIME'] ?? '',
            'generated_date' => $data['GENERATED_DATE'] ?? '',
            'disposition' => $data['DISPOSITION'] ?? '',
            'subject' => $data['SUBJECT'] ?? '',
            'source' => 'indiamart',
        ];

        return $leadData;
    }

    /**
     * Extract data from alternative IndiaMart format
     */
    private function extractAlternativeLeadData(array $data): array
    {
        $leadData = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'notes' => '',
            'metadata' => [],
        ];

        if (isset($data['inquiry_details']) && is_array($data['inquiry_details'])) {
            $inquiry = $data['inquiry_details'];

            $leadData['name'] = $this->cleanString($inquiry['name'] ?? '');
            $leadData['phone'] = $this->cleanPhoneNumber($inquiry['mobile'] ?? '');
            $leadData['email'] = $this->cleanString($inquiry['email'] ?? '');

            $notes = [];
            if (filled($inquiry['message'])) {
                $notes[] = 'Message: ' . $this->cleanString($inquiry['message']);
            }
            if (filled($inquiry['product_name'])) {
                $notes[] = 'Product: ' . $this->cleanString($inquiry['product_name']);
            }
            if (filled($inquiry['quantity'])) {
                $notes[] = 'Quantity: ' . $this->cleanString($inquiry['quantity']);
            }

            $leadData['notes'] = implode("\n", $notes);

            $leadData['metadata'] = [
                'indiamart_inquiry_id' => $inquiry['inquiry_id'] ?? '',
                'product_id' => $inquiry['product_id'] ?? '',
                'source' => 'indiamart',
            ];
        }

        return $leadData;
    }

    /**
     * Extract data from batch lead format
     */
    private function extractBatchLeadData(array $data): array
    {
        // Process the first valid lead
        if (isset($data['leads']) && is_array($data['leads']) && count($data['leads']) > 0) {
            $firstLead = $data['leads'][0];

            return $this->extractStandardLeadData($firstLead);
        }

        return [];
    }

    /**
     * Clean and validate phone number
     */
    private function cleanPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Ensure it starts with +91 for Indian numbers if not already
        if (strlen($phone) === 10 && is_numeric($phone)) {
            $phone = '+91' . $phone;
        }

        return $phone;
    }

    /**
     * Clean string data
     */
    private function cleanString(string $string): string
    {
        return trim(strip_tags($string));
    }

    /**
     * Send acknowledgment to IndiaMart user
     */
    private function sendAcknowledgment(Lead $lead, TenantSocialConfig $config): void
    {
        try {
            if (! $config->auto_reply_enabled) {
                return;
            }

            $apiKey = $config->api_key ?? config('services.indiamart.api_key');

            if (! $apiKey) {
                Log::warning('Missing IndiaMart API credentials for acknowledgment');

                return;
            }

            // Send SMS acknowledgment (if configured)
            if ($config->sms_acknowledgment_enabled && $lead->phone) {
                $this->sendSMSAcknowledgment($lead);
            }

            // Send email acknowledgment (if configured)
            if ($config->email_acknowledgment_enabled && $lead->email) {
                $this->sendEmailAcknowledgment($lead);
            }
        } catch (Exception $e) {
            Log::error('Failed to send IndiaMart acknowledgment', [
                'error' => $e->getMessage(),
                'lead_id' => $lead->id,
            ]);
        }
    }

    /**
     * Send SMS acknowledgment
     */
    private function sendSMSAcknowledgment(Lead $lead): void
    {
        // This would integrate with your SMS service
        // For now, just log the intent
        Log::info('SMS acknowledgment would be sent', [
            'lead_id' => $lead->id,
            'phone' => $lead->phone,
            'message' => "Thank you {$lead->name} for your inquiry. We'll contact you soon.",
        ]);
    }

    /**
     * Send email acknowledgment
     */
    private function sendEmailAcknowledgment(Lead $lead): void
    {
        // This would integrate with your email service
        // For now, just log the intent
        Log::info('Email acknowledgment would be sent', [
            'lead_id' => $lead->id,
            'email' => $lead->email,
            'subject' => 'Thank you for your inquiry',
        ]);
    }
}
