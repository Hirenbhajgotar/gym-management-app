<?php

namespace App\Http\Controllers\Api;

use App\Models\WhatsAppUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

class TwilioWebhookController
{
    /**
     * Handle Twilio message delivery status callbacks.
     */
    public function handleStatusCallback(Request $request): JsonResponse
    {
        // Validate Twilio signature in production
        if (! app()->environment('local', 'testing')) {
            if (! $this->validateTwilioSignature($request)) {
                Log::warning('Invalid Twilio webhook signature', [
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        $messageSid = $request->input('MessageSid');
        $messageStatus = $request->input('MessageStatus');
        $errorCode = $request->input('ErrorCode');
        $errorMessage = $request->input('ErrorMessage');

        if (! $messageSid || ! $messageStatus) {
            return response()->json(['error' => 'Missing required fields'], 422);
        }

        Log::info('Twilio status callback received', [
            'message_sid' => $messageSid,
            'status' => $messageStatus,
            'error_code' => $errorCode,
        ]);

        // Update the usage log with the delivery status
        $usageLog = WhatsAppUsageLog::where('twilio_message_sid', $messageSid)->first();

        if ($usageLog) {
            $updateData = [
                'delivery_status' => $messageStatus,
            ];

            if ($errorCode) {
                $updateData['error_code'] = $errorCode;
                $updateData['error_message'] = $errorMessage;
            }

            if ($messageStatus === 'delivered') {
                $updateData['delivered_at'] = now();
            } elseif ($messageStatus === 'read') {
                $updateData['read_at'] = now();
            } elseif ($messageStatus === 'failed' || $messageStatus === 'undelivered') {
                $updateData['failed_at'] = now();
            }

            $usageLog->update($updateData);

            Log::info('WhatsApp usage log updated', [
                'usage_log_id' => $usageLog->id,
                'new_status' => $messageStatus,
            ]);
        } else {
            Log::warning('No usage log found for Twilio message', [
                'message_sid' => $messageSid,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Validate an incoming Twilio webhook request using the request signature.
     */
    private function validateTwilioSignature(Request $request): bool
    {
        $authToken = config('services.twilio.master_token');

        if (! $authToken) {
            Log::error('Twilio auth token not configured for webhook validation');

            return false;
        }

        $validator = new RequestValidator($authToken);
        $signature = $request->header('X-Twilio-Signature', '');
        $url = $request->fullUrl();
        $params = $request->all();

        return $validator->validate($signature, $url, $params);
    }
}
