<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lead;
use App\Models\TenantSocialConfig;
use Exception;
use Illuminate\Support\Facades\Log;

class JustDialLeadProcessor extends SocialMediaLeadProcessor
{
    public function __construct()
    {
        parent::__construct('justdial');
    }

    /**
     * Process JustDial lead data
     */
    public function processLead(array $data): ?Lead
    {
        try {
            $this->logWebhook($data, 'processing');

            // Extract lead data from JustDial format
            $leadData = $this->extractLeadData($data);

            if (blank($leadData)) {
                Log::warning('No lead data extracted from JustDial webhook', ['data' => $data]);

                return null;
            }

            // Get tenant configuration
            $config = $this->getTenantConfig($data['vendor_id'] ?? $data['company_id'] ?? '');
            if (! $config) {
                Log::warning('No tenant configuration found for JustDial account', ['data' => $data]);

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
            Log::error('JustDial lead processing failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            $this->logWebhook($data, 'error', $e->getMessage());

            return null;
        }
    }

    /**
     * Verify JustDial webhook signature
     */
    public function verifyWebhook(array $data, array $headers): bool
    {
        try {
            // JustDial uses API key verification
            $apiKey = $headers['x-api-key'][0] ?? '';
            if (blank($apiKey)) {
                return false;
            }

            // Get tenant configuration and verify API key
            $vendorId = $data['vendor_id'] ?? $data['company_id'] ?? '';
            if (blank($vendorId)) {
                return false;
            }

            $config = $this->getTenantConfig($vendorId);
            if (! $config) {
                return false;
            }

            // Verify API key matches
            $expectedApiKey = $config->api_key ?? config('services.justdial.api_key');

            return $apiKey === $expectedApiKey;
        } catch (Exception $e) {
            Log::error('JustDial webhook verification failed', [
                'error' => $e->getMessage(),
                'headers' => $headers,
            ]);

            return false;
        }
    }

    /**
     * Extract lead data from JustDial webhook format
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

            // Handle different JustDial webhook formats
            if (isset($data['lead_id'])) {
                // Standard JustDial lead format
                return $this->extractStandardLeadData($data);
            } elseif (isset($data['inquiry'])) {
                // Alternative format
                return $this->extractAlternativeLeadData($data);
            } elseif (isset($data['leads']) && is_array($data['leads'])) {
                // Batch lead format
                return $this->extractBatchLeadData($data);
            }

            Log::warning('Unknown JustDial webhook format', ['data' => $data]);

            return [];
        } catch (Exception $e) {
            Log::error('Failed to extract lead data from JustDial', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [];
        }
    }

    /**
     * Extract data from standard JustDial lead format
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
        $leadData['name'] = $this->cleanString($data['name'] ?? '');
        $leadData['phone'] = $this->cleanPhoneNumber($data['mobile'] ?? $data['phone'] ?? '');
        $leadData['email'] = $this->cleanString($data['email'] ?? '');

        // Build detailed notes
        $notes = [];

        // Service/Category information
        if (filled($data['category'])) {
            $notes[] = 'Category: ' . $this->cleanString($data['category']);
        }

        if (filled($data['subcategory'])) {
            $notes[] = 'Subcategory: ' . $this->cleanString($data['subcategory']);
        }

        if (filled($data['service_name'])) {
            $notes[] = 'Service: ' . $this->cleanString($data['service_name']);
        }

        // Inquiry details
        if (filled($data['requirement'])) {
            $notes[] = 'Requirement: ' . $this->cleanString($data['requirement']);
        }

        if (filled($data['description'])) {
            $notes[] = 'Description: ' . $this->cleanString($data['description']);
        }

        if (filled($data['message'])) {
            $notes[] = 'Message: ' . $this->cleanString($data['message']);
        }

        // Location information
        if (filled($data['city'])) {
            $notes[] = 'City: ' . $this->cleanString($data['city']);
        }

        if (filled($data['area'])) {
            $notes[] = 'Area: ' . $this->cleanString($data['area']);
        }

        if (filled($data['address'])) {
            $notes[] = 'Address: ' . $this->cleanString($data['address']);
        }

        // Company information
        if (filled($data['company_name'])) {
            $notes[] = 'Company: ' . $this->cleanString($data['company_name']);
        }

        // Budget and timeline
        if (filled($data['budget'])) {
            $notes[] = 'Budget: ' . $this->cleanString($data['budget']);
        }

        if (filled($data['timeline'])) {
            $notes[] = 'Timeline: ' . $this->cleanString($data['timeline']);
        }

        $leadData['notes'] = implode("\n", $notes);

        // Add metadata
        $leadData['metadata'] = [
            'justdial_lead_id' => $data['lead_id'] ?? '',
            'vendor_id' => $data['vendor_id'] ?? '',
            'company_id' => $data['company_id'] ?? '',
            'listing_id' => $data['listing_id'] ?? '',
            'category_id' => $data['category_id'] ?? '',
            'subcategory_id' => $data['subcategory_id'] ?? '',
            'enquiry_type' => $data['enquiry_type'] ?? '',
            'enquiry_source' => $data['enquiry_source'] ?? '',
            'lead_type' => $data['lead_type'] ?? '',
            'priority' => $data['priority'] ?? '',
            'status' => $data['status'] ?? '',
            'generated_time' => $data['generated_time'] ?? '',
            'generated_date' => $data['generated_date'] ?? '',
            'call_duration' => $data['call_duration'] ?? '',
            'call_recording_url' => $data['call_recording_url'] ?? '',
            'source' => 'justdial',
        ];

        return $leadData;
    }

    /**
     * Extract data from alternative JustDial format
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

        if (isset($data['inquiry']) && is_array($data['inquiry'])) {
            $inquiry = $data['inquiry'];

            $leadData['name'] = $this->cleanString($inquiry['name'] ?? '');
            $leadData['phone'] = $this->cleanPhoneNumber($inquiry['mobile'] ?? $inquiry['phone'] ?? '');
            $leadData['email'] = $this->cleanString($inquiry['email'] ?? '');

            $notes = [];
            if (filled($inquiry['message'])) {
                $notes[] = 'Message: ' . $this->cleanString($inquiry['message']);
            }
            if (filled($inquiry['service'])) {
                $notes[] = 'Service: ' . $this->cleanString($inquiry['service']);
            }
            if (filled($inquiry['location'])) {
                $notes[] = 'Location: ' . $this->cleanString($inquiry['location']);
            }

            $leadData['notes'] = implode("\n", $notes);

            $leadData['metadata'] = [
                'justdial_inquiry_id' => $inquiry['inquiry_id'] ?? '',
                'service_id' => $inquiry['service_id'] ?? '',
                'source' => 'justdial',
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
     * Send acknowledgment to JustDial user
     */
    private function sendAcknowledgment(Lead $lead, TenantSocialConfig $config): void
    {
        try {
            if (! $config->auto_reply_enabled) {
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
            Log::error('Failed to send JustDial acknowledgment', [
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
        try {
            // This would integrate with your SMS service
            // For now, just log the intent
            $message = "Thank you {$lead->name} for your inquiry on JustDial. We'll contact you soon regarding your fitness requirements.";

            Log::info('SMS acknowledgment would be sent', [
                'lead_id' => $lead->id,
                'phone' => $lead->phone,
                'message' => $message,
            ]);

            // Here you would integrate with your SMS provider
            // Example: SMS::send($lead->phone, $message);

        } catch (Exception $e) {
            Log::error('Failed to send SMS acknowledgment', [
                'error' => $e->getMessage(),
                'lead_id' => $lead->id,
            ]);
        }
    }

    /**
     * Send email acknowledgment
     */
    private function sendEmailAcknowledgment(Lead $lead): void
    {
        try {
            // This would integrate with your email service
            // For now, just log the intent
            $subject = 'Thank you for your inquiry on JustDial';
            $body = "Dear {$lead->name},\n\nThank you for your inquiry regarding fitness services. We have received your request and will contact you soon to discuss your requirements.\n\nBest regards,\nFitness Team";

            Log::info('Email acknowledgment would be sent', [
                'lead_id' => $lead->id,
                'email' => $lead->email,
                'subject' => $subject,
            ]);

            // Here you would integrate with your email service
            // Example: Mail::to($lead->email)->send(new LeadAcknowledgmentEmail($lead, $subject, $body));

        } catch (Exception $e) {
            Log::error('Failed to send email acknowledgment', [
                'error' => $e->getMessage(),
                'lead_id' => $lead->id,
            ]);
        }
    }
}
