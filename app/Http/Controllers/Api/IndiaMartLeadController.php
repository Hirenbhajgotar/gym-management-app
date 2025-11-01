<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IndiaMartLeadProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IndiaMartLeadController extends Controller
{
    public function __construct(
        private IndiaMartLeadProcessor $processor
    ) {}

    /**
     * Handle IndiaMart webhook for leads
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            Log::info('IndiaMart webhook received', [
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
                'ip' => $request->ip(),
            ]);

            // Process the IndiaMart lead
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
            Log::error('IndiaMart webhook processing failed', [
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
