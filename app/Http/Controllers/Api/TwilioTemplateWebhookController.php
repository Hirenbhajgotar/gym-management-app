<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTwilioTemplateWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

/**
 * Handles Twilio WhatsApp template status webhook updates.
 *
 * This controller receives real-time notifications when Twilio updates
 * template statuses (approved, rejected, pending, etc.), dispatching
 * async jobs to update the database instantly.
 */
class TwilioTemplateWebhookController extends Controller
{
    /**
     * Handle POST request for template status webhook events.
     *
     * Twilio sends form-encoded POST with X-Twilio-Signature header.
     * Payload contains ContentSid, Status, and optional ErrorCode/ErrorMessage.
     */
    public function handleTemplateStatusWebhook(Request $request): JsonResponse
    {
        try {
            // Validate Twilio signature in production
            if (! app()->environment('local', 'testing')) {
                if (! $this->validateTwilioSignature($request)) {
                    Log::warning('Twilio template webhook: invalid signature', [
                        'url' => $request->fullUrl(),
                        'ip' => $request->ip(),
                    ]);

                    return response()->json(['error' => 'Invalid signature'], 403);
                }
            }

            $contentSid = (string) $request->input('ContentSid');
            $status = (string) $request->input('Status');

            if (! $contentSid || ! $status) {
                Log::warning('Twilio template webhook: missing required fields', [
                    'content_sid' => $contentSid,
                    'status' => $status,
                ]);

                return response()->json(['error' => 'Missing required fields'], 422);
            }

            $errorCode = $request->input('ErrorCode');
            $errorMessage = $request->input('ErrorMessage');

            Log::info('Twilio template status update received', [
                'content_sid' => $contentSid,
                'status' => $status,
                'error_code' => $errorCode,
            ]);

            // Dispatch job for async processing
            dispatch(new ProcessTwilioTemplateWebhook($contentSid, $status, $errorCode, $errorMessage));

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('Twilio template webhook processing failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Validate an incoming Twilio webhook request using the request signature.
     */
    private function validateTwilioSignature(Request $request): bool
    {
        $authToken = (string) config('services.twilio.master_token');

        if (! $authToken) {
            Log::error('Twilio template webhook: auth token not configured');

            return false;
        }

        $validator = new RequestValidator($authToken);
        $signature = $request->header('X-Twilio-Signature', '');
        $url = $this->getWebhookUrl($request);
        $params = $request->all();

        return $validator->validate($signature, $url, $params);
    }

    /**
     * Get the URL that Twilio used to generate the signature.
     */
    private function getWebhookUrl(Request $request): string
    {
        // Option 1: Use APP_URL if configured (recommended for production)
        $appUrl = config('app.url');

        if ($appUrl && app()->environment('production')) {
            return rtrim($appUrl, '/') . $request->getRequestUri();
        }

        // Option 2: Fall back to fullUrl()
        return $request->fullUrl();
    }
}
