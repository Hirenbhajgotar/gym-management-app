<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FacebookLeadController;
use App\Http\Controllers\Api\InstagramLeadController;
use App\Http\Controllers\Api\WhatsAppLeadController;
use App\Http\Controllers\Api\IndiaMartLeadController;
use App\Http\Controllers\Api\JustDialLeadController;
use App\Http\Controllers\Api\WebhookTestController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Social Media Webhook Routes
Route::prefix('webhooks')->group(function () {

    // Facebook Lead Ads Webhook
    Route::post('/facebook/leads', [FacebookLeadController::class, 'handleWebhook'])
        ->name('api.webhooks.facebook.leads');

    // Instagram Lead Ads Webhook
    Route::post('/instagram/leads', [InstagramLeadController::class, 'handleWebhook'])
        ->name('api.webhooks.instagram.leads');

    // WhatsApp Business API Webhook
    Route::post('/whatsapp/messages', [WhatsAppLeadController::class, 'handleWebhook'])
        ->name('api.webhooks.whatsapp.messages');

    // IndiaMart Lead Webhook
    Route::post('/indiamart/leads', [IndiaMartLeadController::class, 'handleWebhook'])
        ->name('api.webhooks.indiamart.leads');

    // JustDial Lead Webhook
    Route::post('/justdial/leads', [JustDialLeadController::class, 'handleWebhook'])
        ->name('api.webhooks.justdial.leads');

    // Webhook Verification Routes (GET)
    Route::get('/facebook/leads', [FacebookLeadController::class, 'verifyWebhook'])
        ->name('api.webhooks.facebook.verify');

    Route::get('/instagram/leads', [InstagramLeadController::class, 'verifyWebhook'])
        ->name('api.webhooks.instagram.verify');

    Route::get('/whatsapp/messages', [WhatsAppLeadController::class, 'verifyWebhook'])
        ->name('api.webhooks.whatsapp.verify');

    Route::get('/indiamart/leads', [IndiaMartLeadController::class, 'verifyWebhook'])
        ->name('api.webhooks.indiamart.verify');

    Route::get('/justdial/leads', [JustDialLeadController::class, 'verifyWebhook'])
        ->name('api.webhooks.justdial.verify');

    // Webhook Testing Routes
    // Webhook Testing Routes (unauthenticated for ease of testing)
    Route::post('/test/{platform}', [WebhookTestController::class, 'testWebhook'])
        ->where('platform', 'facebook|instagram|whatsapp|indiamart|justdial')
        ->name('api.webhooks.test');

    Route::get('/test/{platform}/status', [WebhookTestController::class, 'getVerificationStatus'])
        ->where('platform', 'facebook|instagram|whatsapp|indiamart|justdial')
        ->name('api.webhooks.test.status');
});

// Lead Management API Routes
Route::prefix('leads')->middleware(['auth:sanctum', 'tenant'])->group(function () {

    // Get leads by source
    Route::get('/source/{source}', function (Request $request, $source) {
        $user = $request->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $gym = \App\Models\Gym::where('tenant_id', $user->tenant_id)->first();
        if (!$gym) {
            return response()->json(['error' => 'No gym found for tenant'], 404);
        }

        return \App\Models\Lead::where('source', $source)
            ->where('gym_id', $gym->id)
            ->paginate(20);
    })->where('source', 'facebook|instagram|whatsapp|indiamart|justdial|social_media')
        ->name('api.leads.by-source');

    // Get lead analytics
    Route::get('/analytics', function (Request $request) {
        $user = $request->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $gym = \App\Models\Gym::where('tenant_id', $user->tenant_id)->first();
        if (!$gym) {
            return response()->json(['error' => 'No gym found for tenant'], 404);
        }

        $gymId = $gym->id;

        $analytics = [
            'total_leads' => \App\Models\Lead::where('gym_id', $gymId)->count(),
            'by_source' => \App\Models\Lead::where('gym_id', $gymId)
                ->selectRaw('source, COUNT(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source'),
            'by_status' => \App\Models\Lead::where('gym_id', $gymId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'this_month' => \App\Models\Lead::where('gym_id', $gymId)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'last_7_days' => \App\Models\Lead::where('gym_id', $gymId)
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->count(),
        ];

        return response()->json($analytics);
    })->name('api.leads.analytics');
});

// Social Media Configuration API Routes
Route::prefix('social-config')->middleware(['auth:sanctum', 'tenant'])->group(function () {

    // Get all configurations for current tenant
    Route::get('/', function (Request $request) {
        $user = $request->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return \App\Models\TenantSocialConfig::where('tenant_id', $user->tenant_id)
            ->get();
    })->name('api.social-config.index');

    // Get specific configuration
    Route::get('/{id}', function (Request $request, $id) {
        $user = $request->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return \App\Models\TenantSocialConfig::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->firstOrFail();
    })->name('api.social-config.show');

    // Toggle configuration status
    Route::patch('/{id}/toggle', function (Request $request, $id) {
        $user = $request->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $config = \App\Models\TenantSocialConfig::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->firstOrFail();

        $config->update(['is_active' => !$config->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Configuration status updated',
            'is_active' => $config->is_active,
        ]);
    })->name('api.social-config.toggle');
});
