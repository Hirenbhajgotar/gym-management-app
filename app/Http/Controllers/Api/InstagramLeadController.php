<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantSocialConfig;
use App\Services\InstagramLeadProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InstagramLeadController extends Controller
{
    protected InstagramLeadProcessor $processor;

    public function __construct(InstagramLeadProcessor $processor)
    {
        $this->processor = $processor;
    }

    /**
     * Handle Instagram lead webhook
     */
    public function handleWebhook(Request $request)
    {
        try {
            Log::info('Instagram webhook received', [
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
            ]);

            // Get the configuration for this webhook
            $config = $this->getConfig($request);

            if (! $config) {
                Log::warning('No Instagram configuration found for webhook', [
                    'request_data' => $request->all(),
                ]);

                return response()->json(['error' => 'Configuration not found'], 404);
            }

            // Verify webhook signature
            if (! $this->processor->verifyWebhook($request->all(), $request->headers->all())) {
                Log::warning('Instagram webhook verification failed', [
                    'config_id' => $config->id,
                    'headers' => $request->headers->all(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Process the lead
            $lead = $this->processor->processLead($request->all());

            if ($lead) {
                Log::info('Instagram lead processed successfully', [
                    'lead_id' => $lead->id,
                    'config_id' => $config->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Lead processed successfully',
                    'lead_id' => $lead->id,
                ]);
            }

            Log::warning('Failed to process Instagram lead', [
                'config_id' => $config->id,
                'data' => $request->all(),
            ]);

            return response()->json(['error' => 'Failed to process lead'], 422);
        } catch (\Exception $e) {
            Log::error('Instagram webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Verify Instagram webhook (for webhook setup)
     */
    public function verifyWebhook(Request $request)
    {
        try {
            Log::info('Instagram webhook verification request', [
                'query' => $request->query(),
                'headers' => $request->headers->all(),
            ]);

            // Handle Facebook's webhook verification
            $hubMode = $request->query('hub_mode');
            $hubVerifyToken = $request->query('hub_verify_token');
            $hubChallenge = $request->query('hub_challenge');

            if ($hubMode === 'subscribe' && $hubVerifyToken) {
                // Find configuration by verify token
                $config = TenantSocialConfig::query()->where('platform', 'instagram')
                    ->where('webhook_verify_token', $hubVerifyToken)
                    ->first();

                if ($config) {
                    Log::info('Instagram webhook verification successful', [
                        'config_id' => $config->id,
                        'hub_challenge' => $hubChallenge,
                    ]);

                    return response($hubChallenge, 200)
                        ->header('Content-Type', 'text/plain');
                }
            }

            Log::warning('Instagram webhook verification failed', [
                'hub_mode' => $hubMode,
                'hub_verify_token' => $hubVerifyToken,
            ]);

            return response()->json(['error' => 'Verification failed'], 403);
        } catch (\Exception $e) {
            Log::error('Instagram webhook verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Verification error'], 500);
        }
    }

    /**
     * Get Instagram configuration for the webhook
     */
    private function getConfig(Request $request): ?TenantSocialConfig
    {
        // Try to find configuration by app ID or page ID from the webhook data
        $data = $request->all();

        // Instagram typically sends page/app information in the webhook
        $pageId = $data['entry'][0]['id'] ?? null;
        $appId = $data['app_id'] ?? null;

        if ($pageId) {
            return TenantSocialConfig::query()->where('platform', 'instagram')
                ->where('page_id', $pageId)
                ->where('is_active', true)
                ->first();
        }

        if ($appId) {
            return TenantSocialConfig::query()->where('platform', 'instagram')
                ->whereJsonContains('additional_config->app_id', $appId)
                ->where('is_active', true)
                ->first();
        }

        // Fallback: get first active Instagram configuration
        return TenantSocialConfig::query()->where('platform', 'instagram')
            ->where('is_active', true)
            ->first();
    }
}
