<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantSocialConfig;
use App\Services\FacebookLeadProcessor;
use App\Services\InstagramLeadProcessor;
use App\Services\WhatsAppLeadProcessor;
use App\Services\IndiaMartLeadProcessor;
use App\Services\JustDialLeadProcessor;
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
            if (!isset($this->processors[$platform])) {
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
            $processor = app($processorClass);

            // Get configuration for the platform
            $config = $this->getConfig($request, $platform);

            if (!$config) {
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
        } catch (\Exception $e) {
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
            ], 500);
        }
    }

    /**
     * Get webhook verification status
     */
    public function getVerificationStatus(Request $request, string $platform)
    {
        try {
            if (!isset($this->processors[$platform])) {
                return response()->json([
                    'error' => 'Unsupported platform',
                    'supported_platforms' => array_keys($this->processors),
                ], 400);
            }

            $config = $this->getConfig($request, $platform);

            if (!$config) {
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
                'verification_status' => $this->checkVerificationStatus($config, $platform),
                'config_id' => $config->id,
                'last_updated' => $config->updated_at,
            ];

            return response()->json($status);
        } catch (\Exception $e) {
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
        $baseData = [
            'name' => $request->input('name', 'Test Lead ' . ucfirst($platform)),
            'email' => $request->input('email', 'test.' . $platform . '@example.com'),
            'phone' => $request->input('phone', '+919999999999'),
            'message' => $request->input('message', 'Test message from ' . ucfirst($platform)),
        ];

        switch ($platform) {
            case 'facebook':
            case 'instagram':
                return [
                    'entry' => [
                        [
                            'id' => '123456789',
                            'time' => time(),
                            'changes' => [
                                [
                                    'value' => [
                                        'leadgen_id' => 'test_lead_' . uniqid(),
                                        'page_id' => '123456789',
                                        'form_id' => '987654321',
                                        'field_data' => [
                                            ['name' => 'full_name', 'values' => [$baseData['name']]],
                                            ['name' => 'email', 'values' => [$baseData['email']]],
                                            ['name' => 'phone_number', 'values' => [$baseData['phone']]],
                                        ],
                                    ],
                                    'field' => 'leadgen',
                                ],
                            ],
                        ],
                    ],
                    'object' => 'page',
                ];

            case 'whatsapp':
                return [
                    'object' => 'whatsapp_business_account',
                    'entry' => [
                        [
                            'id' => '123456789',
                            'changes' => [
                                [
                                    'value' => [
                                        'messages' => [
                                            [
                                                'from' => '919999999999',
                                                'id' => 'test_msg_' . uniqid(),
                                                'timestamp' => time(),
                                                'text' => ['body' => $baseData['message']],
                                                'type' => 'text',
                                            ],
                                        ],
                                        'contacts' => [
                                            [
                                                'profile' => ['name' => $baseData['name']],
                                                'wa_id' => '919999999999',
                                            ],
                                        ],
                                    ],
                                    'field' => 'messages',
                                ],
                            ],
                        ],
                    ],
                ];

            case 'indiamart':
                return [
                    'GLUSR_MOBILE' => $baseData['phone'],
                    'GLUSR_USR_NAME' => $baseData['name'],
                    'GLUSR_USR_ID' => 'test_user_' . uniqid(),
                    'SENDEREMAIL' => $baseData['email'],
                    'SENDERNAME' => $baseData['name'],
                    'SUBJECT' => 'Product Inquiry',
                    'QUERY_MESSAGE' => $baseData['message'],
                    'GLUSR_USR_COMPANYNAME' => 'Test Company',
                    'ENQ_MESSAGE' => $baseData['message'],
                    'ENQ_ADDRESS' => 'Test Address',
                    'ENQ_CITY' => 'Test City',
                    'ENQ_STATE' => 'Test State',
                    'QUERY_ID' => 'test_query_' . uniqid(),
                    'ENQ_CALL_DURATION' => '',
                    'Call_Date' => date('Y-m-d H:i:s'),
                    'Receiver_mobile' => '',
                ];

            case 'justdial':
                return [
                    'mobile' => $baseData['phone'],
                    'name' => $baseData['name'],
                    'email' => $baseData['email'],
                    'company' => 'Test Company',
                    'city' => 'Test City',
                    'query' => $baseData['message'],
                    'docid' => 'test_doc_' . uniqid(),
                    'contact_person' => $baseData['name'],
                    'std_code' => '011',
                    'landline' => '12345678',
                    'data_city' => 'Test City',
                    'paid_status' => '1',
                    'instrument_type' => 'Cash',
                    'budget' => '10000',
                    'payment_mode' => 'Online',
                ];

            default:
                return $baseData;
        }
    }

    /**
     * Get configuration for the platform
     */
    private function getConfig(Request $request, string $platform): ?TenantSocialConfig
    {
        $user = $request->user();
        if (!$user || !$user->tenant_id) {
            return null;
        }

        return TenantSocialConfig::where('tenant_id', $user->tenant_id)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get webhook URL for platform
     */
    private function getWebhookUrl(string $platform): string
    {
        return route('api.webhooks.' . $platform . '.leads');
    }

    /**
     * Check verification status for platform
     */
    private function checkVerificationStatus(TenantSocialConfig $config, string $platform): array
    {
        $status = [
            'webhook_verify_token' => !empty($config->webhook_verify_token),
            'api_key_configured' => !empty($config->api_key),
            'webhook_url_configured' => !empty($config->webhook_url),
            'page_id_configured' => !empty($config->page_id),
            'app_id_configured' => isset($config->config['app_id']),
        ];

        $status['overall'] = $status['webhook_verify_token'] &&
            $status['api_key_configured'] &&
            $status['webhook_url_configured'];

        return $status;
    }
}
