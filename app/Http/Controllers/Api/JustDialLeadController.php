<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JustDialLeadProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class JustDialLeadController extends Controller
{
    public function __construct(
        private JustDialLeadProcessor $processor
    ) {}

    /**
     * Handle JustDial webhook for leads
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            Log::info('JustDial webhook received', [
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
                'ip' => $request->ip(),
            ]);

            // Process the JustDial lead
            $result = $this->processor->processWebhook($request);

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Lead processed successfully',
                    'lead_id' => $result['lead_id'] ?? null,
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['message'] ?? 'Failed to process lead',
            ], 400);
        } catch (\Exception $e) {
            Log::error('JustDial webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
