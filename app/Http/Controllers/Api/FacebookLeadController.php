<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FacebookLeadProcessor;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookLeadController extends Controller
{
    protected FacebookLeadProcessor $processor;

    public function __construct(FacebookLeadProcessor $processor)
    {
        $this->processor = $processor;
    }

    /**
     * Handle Facebook Lead Ads webhook
     */
    public function webhook(Request $request)
    {
        try {
            // Log the incoming webhook for debugging
            Log::info('Facebook webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'ip' => $request->ip(),
            ]);

            // Handle webhook verification (for initial setup)
            if ($request->has('hub_mode') && $request->has('hub_verify_token')) {
                return $this->verifyWebhook($request);
            }

            // Process the webhook data
            $data = $request->all();
            $headers = $request->headers->all();

            // Verify webhook signature
            if (! $this->processor->verifyWebhook($data, $headers)) {
                Log::warning('Facebook webhook verification failed', [
                    'data' => $data,
                    'headers' => $headers,
                ]);

                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Process each entry in the webhook
            if (isset($data['entry']) && is_array($data['entry'])) {
                foreach ($data['entry'] as $entry) {
                    if (isset($entry['changes']) && is_array($entry['changes'])) {
                        foreach ($entry['changes'] as $change) {
                            if ($change['field'] === 'leadgen') {
                                $this->processor->processLead($change['value']);
                            }
                        }
                    }
                }
            }

            Log::info('Facebook webhook processed successfully');

            return response()->json(['status' => 'success'], 200);
        } catch (Exception $e) {
            Log::error('Facebook webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Verify webhook during setup
     */
    private function verifyWebhook(Request $request)
    {
        $verifyToken = config('services.facebook.webhook_verify_token');
        $hubMode = $request->get('hub_mode');
        $hubVerifyToken = $request->get('hub_verify_token');
        $hubChallenge = $request->get('hub_challenge');

        if ($hubMode === 'subscribe' && $hubVerifyToken === $verifyToken) {
            Log::info('Facebook webhook verified successfully');

            return response($hubChallenge, 200);
        }

        Log::warning('Facebook webhook verification failed', [
            'hub_mode' => $hubMode,
            'hub_verify_token' => $hubVerifyToken,
            'expected_token' => $verifyToken,
        ]);

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
