<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\TenantSocialConfig;
use App\Models\Gym;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FacebookLeadProcessor extends SocialMediaLeadProcessor
{
    public function __construct()
    {
        parent::__construct('facebook');
    }

    /**
     * Process Facebook lead data
     */
    public function processLead(array $data): ?Lead
    {
        try {
            $this->logWebhook($data, 'processing');

            // Extract lead data from Facebook format
            $leadData = $this->extractLeadData($data);

            if (empty($leadData)) {
                Log::warning('No lead data extracted from Facebook webhook', ['data' => $data]);
                return null;
            }

            // Get tenant configuration
            $config = $this->getTenantConfig($data['page_id'] ?? $data['form_id'] ?? '');
            if (!$config) {
                Log::warning('No tenant configuration found for Facebook page', ['data' => $data]);
                return null;
            }

            // Set gym context
            $leadData['gym_id'] = $config->gym_id;

            // Create lead
            $lead = $this->createLead($leadData);

            if ($lead) {
                $this->logWebhook($data, 'success');
            } else {
                $this->logWebhook($data, 'failed', 'Lead creation failed');
            }

            return $lead;
        } catch (\Exception $e) {
            Log::error('Facebook lead processing failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            $this->logWebhook($data, 'error', $e->getMessage());
            return null;
        }
    }

    /**
     * Verify Facebook webhook signature
     */
    public function verifyWebhook(array $data, array $headers): bool
    {
        try {
            $signature = $headers['x-hub-signature-256'][0] ?? '';
            if (empty($signature)) {
                return false;
            }

            $appSecret = config('services.facebook.app_secret');
            if (empty($appSecret)) {
                Log::warning('Facebook app secret not configured');
                return false;
            }

            // Extract signature hash
            $signatureParts = explode('=', $signature);
            if (count($signatureParts) !== 2 || $signatureParts[0] !== 'sha256') {
                return false;
            }

            $expectedSignature = $signatureParts[1];
            $payload = json_encode($data);

            // Calculate expected signature
            $calculatedSignature = hash_hmac('sha256', $payload, $appSecret);

            // Compare signatures
            return hash_equals($expectedSignature, $calculatedSignature);
        } catch (\Exception $e) {
            Log::error('Facebook webhook verification failed', [
                'error' => $e->getMessage(),
                'headers' => $headers,
            ]);
            return false;
        }
    }

    /**
     * Extract lead data from Facebook webhook format
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

            // Get lead ID from webhook data
            $leadId = $data['leadgen_id'] ?? $data['lead_id'] ?? null;
            if (!$leadId) {
                Log::warning('No lead ID found in Facebook webhook data', ['data' => $data]);
                return [];
            }

            // Get form ID
            $formId = $data['form_id'] ?? null;
            if (!$formId) {
                Log::warning('No form ID found in Facebook webhook data', ['data' => $data]);
                return [];
            }

            // Get tenant configuration
            $config = $this->getTenantConfig($formId);
            if (!$config) {
                Log::warning('No tenant configuration found for Facebook form', ['form_id' => $formId]);
                return [];
            }

            // Fetch detailed lead data from Facebook API
            $leadDetails = $this->fetchLeadDetails($leadId, $config);
            if (!$leadDetails) {
                return [];
            }

            // Extract field data
            if (isset($leadDetails['field_data']) && is_array($leadDetails['field_data'])) {
                foreach ($leadDetails['field_data'] as $field) {
                    $fieldName = strtolower($field['name'] ?? '');
                    $fieldValue = $field['values'][0] ?? '';

                    switch ($fieldName) {
                        case 'full_name':
                        case 'name':
                            $leadData['name'] = $fieldValue;
                            break;
                        case 'email':
                            $leadData['email'] = $fieldValue;
                            break;
                        case 'phone_number':
                        case 'phone':
                            $leadData['phone'] = $fieldValue;
                            break;
                        case 'city':
                        case 'location':
                            $leadData['notes'] .= "City: {$fieldValue}\n";
                            break;
                        case 'age':
                        case 'date_of_birth':
                            $leadData['notes'] .= "Age/DOB: {$fieldValue}\n";
                            break;
                        case 'fitness_goals':
                        case 'goals':
                            $leadData['notes'] .= "Fitness Goals: {$fieldValue}\n";
                            break;
                        default:
                            $leadData['notes'] .= ucfirst($fieldName) . ": {$fieldValue}\n";
                            break;
                    }
                }
            }

            // Add metadata
            $leadData['metadata'] = [
                'facebook_lead_id' => $leadId,
                'facebook_form_id' => $formId,
                'facebook_page_id' => $data['page_id'] ?? null,
                'ad_id' => $data['ad_id'] ?? null,
                'ad_name' => $data['ad_name'] ?? null,
                'created_time' => $leadDetails['created_time'] ?? now()->toIso8601String(),
            ];

            return $leadData;
        } catch (\Exception $e) {
            Log::error('Failed to extract lead data from Facebook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return [];
        }
    }

    /**
     * Fetch detailed lead data from Facebook API
     */
    private function fetchLeadDetails(string $leadId, TenantSocialConfig $config): ?array
    {
        try {
            $accessToken = $config->access_token ?? config('services.facebook.access_token');
            if (!$accessToken) {
                Log::warning('No access token available for Facebook API');
                return null;
            }

            $apiVersion = config('services.facebook.api_version', 'v18.0');
            $url = "https://graph.facebook.com/{$apiVersion}/{$leadId}";

            $response = Http::get($url, [
                'access_token' => $accessToken,
                'fields' => 'id,created_time,field_data,ad_id,ad_name,form_id,page_id',
            ]);

            if (!$response->successful()) {
                Log::error('Facebook API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'lead_id' => $leadId,
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to fetch lead details from Facebook', [
                'error' => $e->getMessage(),
                'lead_id' => $leadId,
            ]);
            return null;
        }
    }
}
