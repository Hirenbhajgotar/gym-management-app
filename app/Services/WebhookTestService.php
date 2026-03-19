<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Lead;
use App\Models\TenantSocialConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WebhookTestService
{
    /**
     * Process test webhook for a specific platform
     */
    public function processTestWebhook(string $platform, Request $request, TenantSocialConfig $config): array
    {
        try {
            Log::info('Processing test webhook', [
                'platform' => $platform,
                'config_id' => $config->id,
                'tenant_id' => $config->tenant_id,
            ]);

            // Get the appropriate processor for the platform
            $processor = $this->getPlatformProcessor($platform);

            if (! $processor) {
                return [
                    'success' => false,
                    'message' => "No processor available for platform: {$platform}",
                ];
            }

            // For testing, we can simulate the webhook processing
            $testData = $this->generateTestData($platform, $config);

            // Process the test data
            $lead = $processor->processLead($testData);

            if ($lead) {
                return [
                    'success' => true,
                    'message' => 'Test webhook processed successfully',
                    'lead_id' => $lead->id,
                    'lead_data' => [
                        'name' => $lead->name,
                        'email' => $lead->email,
                        'phone' => $lead->phone,
                        'source' => $lead->source->value,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to create lead from test data',
            ];
        } catch (\Exception $e) {
            Log::error('Test webhook processing failed', [
                'platform' => $platform,
                'config_id' => $config->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Test webhook processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process simulated lead data
     */
    public function processSimulatedLead(string $platform, array $sampleData, TenantSocialConfig $config): array
    {
        try {
            Log::info('Processing simulated lead', [
                'platform' => $platform,
                'config_id' => $config->id,
                'tenant_id' => $config->tenant_id,
                'sample_data' => $sampleData,
            ]);

            // Get the appropriate processor for the platform
            $processor = $this->getPlatformProcessor($platform);

            if (! $processor) {
                return [
                    'success' => false,
                    'message' => "No processor available for platform: {$platform}",
                ];
            }

            // Add gym_id to sample data
            $gym = Gym::where('tenant_id', $config->tenant_id)->first();
            if (! $gym) {
                return [
                    'success' => false,
                    'message' => 'No gym found for tenant',
                ];
            }

            $sampleData['gym_id'] = $gym->id;
            $sampleData['tenant_id'] = $config->tenant_id;

            // Process the simulated data
            $lead = $processor->processLead($sampleData);

            if ($lead) {
                // Store simulation result
                $this->storeSimulationResult($platform, $config, $sampleData, $lead);

                return [
                    'success' => true,
                    'message' => 'Simulated lead processed successfully',
                    'lead_id' => $lead->id,
                    'lead_data' => [
                        'name' => $lead->name,
                        'email' => $lead->email,
                        'phone' => $lead->phone,
                        'source' => $lead->source->value,
                        'status' => $lead->status->value,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to create lead from simulated data',
            ];
        } catch (\Exception $e) {
            Log::error('Simulated lead processing failed', [
                'platform' => $platform,
                'config_id' => $config->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Simulated lead processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get platform-specific processor
     */
    private function getPlatformProcessor(string $platform): ?SocialMediaLeadProcessor
    {
        return match ($platform) {
            'facebook' => new FacebookLeadProcessor,
            'instagram' => new InstagramLeadProcessor,
            'whatsapp' => new WhatsAppLeadProcessor,
            'indiamart' => new IndiaMartLeadProcessor,
            'justdial' => new JustDialLeadProcessor,
            default => null,
        };
    }

    /**
     * Generate test data for different platforms
     */
    private function generateTestData(string $platform, TenantSocialConfig $config): array
    {
        $gym = Gym::where('tenant_id', $config->tenant_id)->first();

        if (! $gym) {
            throw new \Exception('No gym found for tenant');
        }

        $baseData = [
            'gym_id' => $gym->id,
            'tenant_id' => $config->tenant_id,
            'name' => 'Test Lead from ' . ucfirst($platform),
            'email' => 'test.' . strtolower($platform) . '.' . uniqid() . '@example.com',
            'phone' => '+91' . rand(9000000000, 9999999999),
            'source' => $platform,
            'created_at' => now()->toIso8601String(),
        ];

        switch ($platform) {
            case 'facebook':
            case 'instagram':
                return array_merge($baseData, [
                    'ad_id' => 'test_ad_' . uniqid(),
                    'form_id' => 'test_form_' . uniqid(),
                    'campaign_id' => 'test_campaign_' . uniqid(),
                    'platform' => $platform,
                    'interested_in' => 'Gym Membership',
                    'budget' => rand(1000, 5000),
                ]);

            case 'whatsapp':
                return array_merge($baseData, [
                    'message' => 'Hi, I am interested in gym membership. Can you provide more details?',
                    'wa_id' => 'test_wa_' . uniqid(),
                    'profile_name' => $baseData['name'],
                ]);

            case 'justdial':
                return array_merge($baseData, [
                    'category' => 'Gyms & Fitness Centres',
                    'city' => 'Mumbai',
                    'requirement' => 'Monthly gym membership',
                    'preferred_time' => 'Evening',
                ]);

            default:
                return $baseData;
        }
    }

    /**
     * Store simulation result for tracking
     */
    private function storeSimulationResult(string $platform, TenantSocialConfig $config, array $sampleData, Lead $lead): void
    {
        try {
            $result = [
                'platform' => $platform,
                'config_id' => $config->id,
                'tenant_id' => $config->tenant_id,
                'timestamp' => now()->toIso8601String(),
                'type' => 'simulation',
                'sample_data' => $sampleData,
                'lead_created' => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'source' => $lead->source->value,
                ],
            ];

            // Store in cache for recent activity
            $recentSimulations = Cache::get("webhook_simulations_{$config->id}", []);
            array_unshift($recentSimulations, $result);
            $recentSimulations = array_slice($recentSimulations, 0, 20); // Keep last 20
            Cache::put("webhook_simulations_{$config->id}", $recentSimulations, 86400);

            // Also store in file for persistence
            $logFile = "webhooks/tenant_{$config->tenant_id}/simulations.log";
            $existingLogs = [];

            if (Storage::disk('local')->exists($logFile)) {
                $existingLogs = json_decode(Storage::disk('local')->get($logFile), true) ?? [];
            }

            $existingLogs[] = $result;
            $existingLogs = array_slice($existingLogs, -100); // Keep last 100

            Storage::disk('local')->put($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            Log::error('Failed to store simulation result', [
                'platform' => $platform,
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get recent webhook activity for a configuration
     */
    public function getRecentActivity(int $configId, int $limit = 20): array
    {
        try {
            $simulations = Cache::get("webhook_simulations_{$configId}", []);

            // Get from file storage as well
            $config = TenantSocialConfig::find($configId);
            if ($config) {
                $logFile = "webhooks/tenant_{$config->tenant_id}/simulations.log";
                if (Storage::disk('local')->exists($logFile)) {
                    $fileLogs = json_decode(Storage::disk('local')->get($logFile), true) ?? [];
                    $simulations = array_merge($simulations, $fileLogs);
                }
            }

            // Sort by timestamp (newest first)
            usort($simulations, function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            return array_slice($simulations, 0, $limit);
        } catch (\Exception $e) {
            Log::error('Failed to get recent activity', [
                'config_id' => $configId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
