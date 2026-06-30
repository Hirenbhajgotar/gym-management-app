<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\TenantSocialConfig;
use App\Services\FacebookLeadProcessor;
use App\Services\IndiaMartLeadProcessor;
use App\Services\InstagramLeadProcessor;
use App\Services\JustDialLeadProcessor;
use App\Services\WhatsAppLeadProcessor;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookTestController extends Controller
{
    protected array $processors = [
        'facebook' => FacebookLeadProcessor::class,
        'instagram' => InstagramLeadProcessor::class,
        'whatsapp' => WhatsAppLeadProcessor::class,
        'indiamart' => IndiaMartLeadProcessor::class,
        'justdial' => JustDialLeadProcessor::class,
    ];

    /**
     * Test webhook endpoint for social media platforms
     */
    public function testWebhook(Request $request, string $platform)
    {
        try {
            if (! isset($this->processors[$platform])) {
                return response()->json([
                    'error' => 'Unsupported platform',
                    'supported_platforms' => array_keys($this->processors),
                ], 400);
            }

            Log::info("Testing {$platform} webhook", [
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Get the processor instance
            $processorClass = $this->processors[$platform];
            $processor = resolve($processorClass);

            // Get configuration for the platform
            $config = $this->getConfig($request, $platform);

            if (! $config) {
                return response()->json([
                    'error' => 'No active configuration found for this platform',
                    'platform' => $platform,
                ], 404);
            }

            // Create test data based on platform
            $testData = $this->generateTestData($platform, $request);

            // Process the test lead
            $lead = $processor->processLead($testData);

            if ($lead) {
                Log::info("Test {$platform} lead processed successfully", [
                    'lead_id' => $lead->id,
                    'config_id' => $config->id,
                    'platform' => $platform,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Test webhook processed successfully',
                    'platform' => $platform,
                    'lead_id' => $lead->id,
                    'lead_data' => [
                        'name' => $lead->name,
                        'email' => $lead->email,
                        'phone' => $lead->phone,
                        'source' => $lead->source,
                        'status' => $lead->status,
                    ],
                    'test_data_used' => $testData,
                ]);
            }

            return response()->json([
                'error' => 'Failed to process test lead',
                'platform' => $platform,
                'test_data' => $testData,
            ], 422);
        } catch (Exception $e) {
            Log::error("Test {$platform} webhook processing failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'platform' => $platform,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Test webhook processing failed',
                'platform' => $platform,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Get webhook verification status
     */
    public function getVerificationStatus(Request $request, string $platform)
    {
        try {
            if (! isset($this->processors[$platform])) {
                return response()->json([
                    'error' => 'Unsupported platform',
                    'supported_platforms' => array_keys($this->processors),
                ], 400);
            }

            $config = $this->getConfig($request, $platform);

            if (! $config) {
                return response()->json([
                    'platform' => $platform,
                    'configured' => false,
                    'message' => 'No configuration found for this platform',
                ]);
            }

            $status = [
                'platform' => $platform,
                'configured' => true,
                'is_active' => $config->is_active,
                'webhook_url' => $this->getWebhookUrl($platform),
                'verification_status' => $this->checkVerificationStatus($config),
                'config_id' => $config->id,
                'last_updated' => $config->updated_at,
            ];

            return response()->json($status);
        } catch (Exception $e) {
            Log::error("Failed to get verification status for {$platform}", [
                'error' => $e->getMessage(),
                'platform' => $platform,
            ]);

            return response()->json([
                'error' => 'Failed to get verification status',
                'platform' => $platform,
            ], 500);
        }
    }

    /**
     * Generate test data for different platforms
     */
    private function generateTestData(string $platform, Request $request): array
    {
        $timestamp = time();
        $uniqueId = uniqid('test_', true);

        $baseData = [
            'name' => $request->input('name', 'Test Lead ' . ucfirst($platform)),
            'email' => $request->input('email', "test.{$platform}.{$uniqueId}@example.com"),
            'phone' => $request->input('phone', '+91' . str_pad(mt_rand(1000000000, 9999999999), 10, '0')),
            'message' => $request->input('message', "Test inquiry from {$platform} - " . date('Y-m-d H:i:s')),
        ];

        return match ($platform) {
            'facebook', 'instagram' => [
                'entry' => [
                    [
                        'id' => $request->input('page_id', '123456789'),
                        'time' => $timestamp,
                        'changes' => [
                            [
                                'value' => [
                                    'leadgen_id' => 'lead_' . $uniqueId,
                                    'page_id' => $request->input('page_id', '123456789'),
                                    'form_id' => $request->input('form_id', '987654321'),
                                    'adgroup_id' => $request->input('adgroup_id', '456789123'),
                                    'ad_id' => $request->input('ad_id', '789123456'),
                                    'created_time' => $timestamp,
                                    'field_data' => $this->formatFieldData($baseData),
                                ],
                                'field' => 'leadgen',
                            ],
                        ],
                    ],
                ],
                'object' => 'page',
            ],

            'whatsapp' => [
                'object' => 'whatsapp_business_account',
                'entry' => [
                    [
                        'id' => $request->input('business_account_id', '123456789'),
                        'changes' => [
                            [
                                'value' => [
                                    'messaging_product' => 'whatsapp',
                                    'metadata' => [
                                        'display_phone_number' => '919999999999',
                                        'phone_number_id' => 'phone_' . $uniqueId,
                                    ],
                                    'messages' => [
                                        [
                                            'from' => str_replace('+', '', $baseData['phone']),
                                            'id' => 'msg_' . $uniqueId,
                                            'timestamp' => $timestamp,
                                            'text' => ['body' => $baseData['message']],
                                            'type' => 'text',
                                        ],
                                    ],
                                    'contacts' => [
                                        [
                                            'profile' => ['name' => $baseData['name']],
                                            'wa_id' => str_replace('+', '', $baseData['phone']),
                                        ],
                                    ],
                                ],
                                'field' => 'messages',
                            ],
                        ],
                    ],
                ],
            ],

            'indiamart' => [
                'UNIQUE_QUERY_ID' => 'query_' . $uniqueId,
                'QUERY_ID' => 'query_' . $uniqueId,
                'RFQ_TYPE' => 'B',
                'QUERY_TYPE' => 'W',
                'GLUSR_USR_ID' => mt_rand(100000, 999999),
                'GLUSR_USR_NAME' => $baseData['name'],
                'GLUSR_USR_EMAIL' => $baseData['email'],
                'GLUSR_MOBILE' => $baseData['phone'],
                'GLUSR_USR_COMPANYNAME' => $request->input('company', 'Test Fitness Company'),
                'SENDERNAME' => $baseData['name'],
                'SENDEREMAIL' => $baseData['email'],
                'SUBJECT' => $request->input('subject', 'Gym Membership Inquiry'),
                'QUERY_MESSAGE' => $baseData['message'],
                'ENQ_MESSAGE' => $baseData['message'],
                'ENQ_ADDRESS' => $request->input('address', 'Test Address, Test City'),
                'ENQ_CITY' => $request->input('city', 'Test City'),
                'ENQ_STATE' => $request->input('state', 'Test State'),
                'ENQ_PINCODE' => $request->input('pincode', '123456'),
                'ENQ_CALL_DURATION' => '',
                'QUERY_MODID' => '123',
                'QUERY_MODREFID' => '456',
                'MOB_DIAL_TIME' => date('Y-m-d H:i:s', $timestamp),
                'Call_Date' => date('Y-m-d H:i:s', $timestamp),
                'Receiver_mobile' => $request->input('receiver_mobile', ''),
            ],

            'justdial' => [
                'docid' => 'doc_' . $uniqueId,
                'mobile' => str_replace('+91', '', $baseData['phone']),
                'name' => $baseData['name'],
                'email' => $baseData['email'],
                'company' => $request->input('company', 'Test Fitness Company'),
                'city' => $request->input('city', 'Test City'),
                'query' => $baseData['message'],
                'contact_person' => $baseData['name'],
                'std_code' => $request->input('std_code', '011'),
                'landline' => $request->input('landline', '12345678'),
                'data_city' => $request->input('city', 'Test City'),
                'area' => $request->input('area', 'Test Area'),
                'business_type' => $request->input('business_type', 'Fitness'),
                'category' => $request->input('category', 'Gym'),
                'paid_status' => '1',
                'instrument_type' => 'Online',
                'budget' => $request->input('budget', '10000'),
                'payment_mode' => 'Online',
                'enquiry_date' => date('Y-m-d H:i:s', $timestamp),
            ],

            default => $baseData,
        };
    }

    /**
     * Get configuration for the platform
     */
    private function getConfig(Request $request, string $platform): ?TenantSocialConfig
    {
        // For testing, we'll use the first available gym's ID
        $gym = Gym::query()->first();
        if (! $gym) {
            Log::error('No gym found for testing webhook configuration.');

            return null;
        }

        $query = TenantSocialConfig::query()->where('gym_id', $gym->id)
            ->where('platform', $platform)
            ->where('is_active', true);

        // If page_id is provided in the request, use it to narrow down the search
        if ($request->has('page_id')) {
            $query->where('page_id', $request->input('page_id'));
        }

        return $query->first();
    }

    /**
     * Get webhook URL for platform
     */
    private function getWebhookUrl(string $platform): string
    {
        return route('api.webhooks.' . $platform . '.leads');
    }

    /**
     * Formats base data into Facebook/Instagram field_data structure.
     */
    private function formatFieldData(array $baseData): array
    {
        $fieldData = [];
        foreach ($baseData as $key => $value) {
            $fieldData[] = [
                'name' => $key,
                'values' => [$value],
            ];
        }

        return $fieldData;
    }

    /**
     * Check verification status for platform
     */
    private function checkVerificationStatus(TenantSocialConfig $config): array
    {
        $status = [
            'webhook_verify_token' => filled($config->webhook_verify_token),
            'api_key_configured' => filled($config->api_key),
            'webhook_url_configured' => filled($config->webhook_url),
            'page_id_configured' => filled($config->page_id),
            'app_id_configured' => isset($config->config['app_id']),
        ];

        $status['overall'] = $status['webhook_verify_token'] &&
            $status['api_key_configured'] &&
            $status['webhook_url_configured'];

        return $status;
    }
}
