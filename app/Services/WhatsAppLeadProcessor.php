<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Lead;
use App\Models\TenantSocialConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppLeadProcessor extends SocialMediaLeadProcessor
{
    public function __construct()
    {
        parent::__construct('whatsapp');
    }

    /**
     * Process WhatsApp Business API lead data
     */
    public function processLead(array $data): ?Lead
    {
        try {
            $this->logWebhook($data, 'processing');

            // Extract lead data from WhatsApp format
            $leadData = $this->extractLeadData($data);

            if (empty($leadData)) {
                Log::warning('No lead data extracted from WhatsApp webhook', ['data' => $data]);

                return null;
            }

            // Get tenant configuration
            $config = $this->getTenantConfig($data['business_phone_number_id'] ?? $data['phone_number_id'] ?? '');
            if (! $config) {
                Log::warning('No tenant configuration found for WhatsApp Business number', ['data' => $data]);

                return null;
            }

            // Set gym context
            $leadData['gym_id'] = $config->gym_id;

            // Create lead
            $lead = $this->createLead($leadData);

            if ($lead) {
                $this->logWebhook($data, 'success');

                // Send acknowledgment message if configured
                $this->sendAcknowledgmentMessage($lead, $config);
            } else {
                $this->logWebhook($data, 'failed', 'Lead creation failed');
            }

            return $lead;
        } catch (\Exception $e) {
            Log::error('WhatsApp lead processing failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            $this->logWebhook($data, 'error', $e->getMessage());

            return null;
        }
    }

    /**
     * Verify WhatsApp webhook signature
     */
    public function verifyWebhook(array $data, array $headers): bool
    {
        try {
            // WhatsApp uses a different verification method
            // Check for webhook verification token
            $verifyToken = config('services.whatsapp.webhook_verify_token');
            if (empty($verifyToken)) {
                Log::warning('WhatsApp webhook verify token not configured');

                return false;
            }

            // For webhook setup verification
            if (isset($data['hub_mode']) && isset($data['hub_verify_token'])) {
                return $data['hub_verify_token'] === $verifyToken;
            }

            // For regular webhook messages, verify using signature
            $signature = $headers['x-hub-signature-256'][0] ?? '';
            if (empty($signature)) {
                return false;
            }

            $appSecret = config('services.whatsapp.app_secret');
            if (empty($appSecret)) {
                Log::warning('WhatsApp app secret not configured');

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
            Log::error('WhatsApp webhook verification failed', [
                'error' => $e->getMessage(),
                'headers' => $headers,
            ]);

            return false;
        }
    }

    /**
     * Extract lead data from WhatsApp webhook format
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

            // Handle different WhatsApp webhook types
            if (isset($data['object']) && $data['object'] === 'whatsapp_business_account') {
                return $this->extractBusinessAccountData($data);
            } elseif (isset($data['entry'])) {
                // Batch webhook format
                return $this->extractBatchData($data);
            }

            Log::warning('Unknown WhatsApp webhook format', ['data' => $data]);

            return [];
        } catch (\Exception $e) {
            Log::error('Failed to extract lead data from WhatsApp', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [];
        }
    }

    /**
     * Extract data from WhatsApp Business Account webhook
     */
    private function extractBusinessAccountData(array $data): array
    {
        $leadData = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'notes' => '',
            'metadata' => [],
        ];

        if (! isset($data['entry']) || ! is_array($data['entry'])) {
            return [];
        }

        foreach ($data['entry'] as $entry) {
            if (! isset($entry['changes']) || ! is_array($entry['changes'])) {
                continue;
            }

            foreach ($entry['changes'] as $change) {
                if (! isset($change['value']) || ! isset($change['value']['messages'])) {
                    continue;
                }

                $value = $change['value'];
                $messages = $value['messages'];

                foreach ($messages as $message) {
                    if ($message['type'] !== 'text') {
                        continue;
                    }

                    $from = $message['from'] ?? '';
                    $text = $message['text']['body'] ?? '';

                    // Extract contact information from message
                    $extractedData = $this->extractContactInfo($text);

                    // Add WhatsApp-specific information
                    $extractedData['phone'] = $from;
                    $extractedData['metadata'] = [
                        'whatsapp_user_id' => $from,
                        'message_id' => $message['id'] ?? '',
                        'timestamp' => $message['timestamp'] ?? time(),
                        'business_phone_number_id' => $value['metadata']['phone_number_id'] ?? '',
                        'display_phone_number' => $value['metadata']['display_phone_number'] ?? '',
                        'source' => 'whatsapp_business',
                    ];

                    return $extractedData;
                }
            }
        }

        return [];
    }

    /**
     * Extract data from batch webhook format
     */
    private function extractBatchData(array $data): array
    {
        // Process the first valid entry
        if (isset($data['entry']) && is_array($data['entry']) && count($data['entry']) > 0) {
            return $this->extractBusinessAccountData($data);
        }

        return [];
    }

    /**
     * Extract contact information from WhatsApp message
     */
    private function extractContactInfo(string $message): array
    {
        $data = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'notes' => $message,
        ];

        // Extract email
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $message, $emailMatches)) {
            $data['email'] = $emailMatches[0];
        }

        // Extract phone (additional to sender's phone)
        if (preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $message, $phoneMatches)) {
            $data['phone'] = $phoneMatches[0];
        }

        // Extract name using common patterns
        $namePatterns = [
            '/my name is ([a-zA-Z\s]+)/i',
            '/i am ([a-zA-Z\s]+)/i',
            '/this is ([a-zA-Z\s]+)/i',
            '/call me ([a-zA-Z\s]+)/i',
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $message, $nameMatches)) {
                $data['name'] = trim($nameMatches[1]);
                break;
            }
        }

        // If no name found, try to extract from greeting
        if (empty($data['name']) && preg_match('/hello|hi|hey/i', $message)) {
            // Look for capitalized words after greeting
            if (preg_match('/(?:hello|hi|hey)[\s,]+([A-Z][a-z]+(?:\s[A-Z][a-z]+)*)/i', $message, $greetingMatches)) {
                $data['name'] = trim($greetingMatches[1]);
            }
        }

        // Add fitness-related keywords to notes
        $fitnessKeywords = ['gym', 'fitness', 'workout', 'training', 'membership', 'join', 'enquiry', 'inquiry'];
        $messageLower = strtolower($message);

        foreach ($fitnessKeywords as $keyword) {
            if (strpos($messageLower, $keyword) !== false) {
                $data['notes'] = "Fitness enquiry: {$message}";
                break;
            }
        }

        return $data;
    }

    /**
     * Send acknowledgment message to WhatsApp user
     */
    private function sendAcknowledgmentMessage(Lead $lead, TenantSocialConfig $config): void
    {
        try {
            if (! $config->auto_reply_enabled) {
                return;
            }

            $templateName = $config->auto_reply_template ?? 'welcome_message';
            $businessPhoneId = $config->business_phone_number_id;

            if (! $businessPhoneId) {
                Log::warning('No business phone number ID configured for WhatsApp auto-reply');

                return;
            }

            $accessToken = $config->access_token ?? config('services.whatsapp.access_token');
            if (! $accessToken) {
                Log::warning('No access token available for WhatsApp API');

                return;
            }

            $apiVersion = config('services.whatsapp.api_version', 'v18.0');
            $url = "https://graph.facebook.com/{$apiVersion}/{$businessPhoneId}/messages";

            $message = [
                'messaging_product' => 'whatsapp',
                'to' => $lead->phone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => 'en_US',
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $lead->name ?: 'Valued Customer',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $message);

            if (! $response->successful()) {
                Log::error('WhatsApp acknowledgment message failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'lead_id' => $lead->id,
                ]);

                return;
            }

            Log::info('WhatsApp acknowledgment message sent successfully', [
                'lead_id' => $lead->id,
                'phone' => $lead->phone,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp acknowledgment message', [
                'error' => $e->getMessage(),
                'lead_id' => $lead->id,
            ]);
        }
    }
}
