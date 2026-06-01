<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMetaTemplateWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles Meta WhatsApp Business API webhook for template status updates.
 *
 * This controller receives real-time notifications when Meta approves,
 * rejects, or changes template statuses, updating the database instantly
 * instead of relying solely on polling (SyncWhatsAppTemplateStatusesCommand).
 */
class WhatsAppTemplateWebhookController extends Controller
{
    /**
     * Handle GET request for webhook verification.
     *
     * Meta sends a GET request with hub_mode, hub_verify_token, and hub_challenge
     * to verify the endpoint during webhook setup.
     */
    public function verifyMetaWebhook(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $hubMode = $request->input('hub_mode');
        $hubVerifyToken = $request->input('hub_verify_token');
        $hubChallenge = $request->input('hub_challenge');
        $verifyToken = (string) config('services.meta.webhook_verify_token');

        Log::info('WhatsApp template webhook verification attempt', [
            'hub_mode' => $hubMode,
            'hub_verify_token' => $hubVerifyToken,
        ]);

        if ($hubMode === 'subscribe' && $hubVerifyToken === $verifyToken) {
            Log::info('WhatsApp template webhook verified successfully');

            return response($hubChallenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp template webhook verification failed', [
            'hub_mode' => $hubMode,
            'hub_verify_token' => substr($hubVerifyToken ?? '', 0, 8) . '...',
            'expected_token' => substr($verifyToken ?? '', 0, 8) . '...',
        ]);

        return response()->json(['error' => 'Verification failed'], 403);
    }

    /**
     * Handle POST request for template status webhook events.
     *
     * Meta sends POST with X-Hub-Signature-256 header for authentication.
     * Payload contains message_template_status_update events with template
     * status changes (APPROVED, REJECTED, PENDING, etc.).
     */
    public function handleTemplateStatusWebhook(Request $request): JsonResponse
    {
        try {
            // Verify webhook signature
            $signature = $request->header('X-Hub-Signature-256');

            if (! $signature) {
                Log::warning('WhatsApp template webhook missing signature header');

                return response()->json(['error' => 'Missing signature'], 401);
            }

            // Meta uses the same app_secret as Facebook for X-Hub-Signature-256
            $appSecret = (string) config('services.meta.app_secret');

            if (! $appSecret) {
                Log::error('WhatsApp template webhook: app_secret not configured');

                return response()->json(['error' => 'Server configuration error'], 500);
            }

            // Verify signature using hash_equals to prevent timing attacks
            $payload = $request->getContent();
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

            if (! hash_equals($expectedSignature, $signature)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Parse the webhook payload
            $data = $request->all();

            if (! isset($data['entry']) || ! is_array($data['entry'])) {
                Log::info('WhatsApp template webhook: no entries in payload');

                return response()->json(['status' => 'success'], 200);
            }

            // Process each entry
            foreach ($data['entry'] as $entry) {
                if (! isset($entry['changes']) || ! is_array($entry['changes'])) {
                    continue;
                }

                $wabaId = ($entry['id'] ?? '');

                if ($wabaId === '') {
                    Log::warning('WhatsApp template webhook: missing entry id');

                    continue;
                }

                foreach ($entry['changes'] as $change) {
                    if (! isset($change['field']) || ! isset($change['value']) || ! isset($change['value']['message_template_id'])) {
                        continue;
                    }

                    // Only process message_template_status_update field
                    if (($change['field'] ?? '') !== 'message_template_status_update') {
                        continue;
                    }

                    $value = $change['value'] ?? [];

                    // Extract template status information
                    $metaTemplateId = $value['message_template_id'] ?? null;
                    $event = $value['event'] ?? null;
                    $reason = $value['reason'] ?? null;
                    $previousEvent = $value['previous_event'] ?? null;

                    if (! $metaTemplateId || ! $event) {
                        Log::warning('WhatsApp template webhook: missing template_id or event', [
                            'value' => $value,
                        ]);

                        continue;
                    }

                    Log::info('WhatsApp template status update', [
                        'meta_template_id' => $metaTemplateId,
                        'event' => $event,
                        'reason' => $reason,
                        'previous_event' => $previousEvent,
                    ]);

                    // Dispatch job for async processing
                    ProcessMetaTemplateWebhook::dispatch(
                        $wabaId,
                        $metaTemplateId,
                        $event,
                        $reason,
                        $previousEvent
                    );
                }
            }

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('WhatsApp template webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
