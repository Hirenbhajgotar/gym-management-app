<?php

use App\Models\Gym;
use App\Models\Lead;
use App\Models\TenantSocialConfig;
use App\Http\Controllers\Api\BiometricCheckInController;
use App\Http\Controllers\Api\BiometricDeviceController;
use App\Http\Controllers\Api\BiometricEnrollController;
use App\Http\Controllers\Api\BiometricOfflineSyncController;
use App\Http\Controllers\Api\BiometricWebhookController;
use App\Http\Controllers\Api\FacebookLeadController;
use App\Http\Controllers\Api\InstagramLeadController;
use App\Http\Controllers\Api\RazorpayWebhookController;
use App\Http\Controllers\Api\TwilioTemplateWebhookController;
use App\Http\Controllers\Api\TwilioWebhookController;
use App\Http\Controllers\Api\WebhookTestController;
use App\Http\Controllers\Api\WhatsAppLeadController;
use App\Http\Controllers\Api\WhatsAppTemplateWebhookController;
use App\Http\Controllers\ReportApiController;
use App\Http\Middleware\AuthenticateBiometricDevice;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    Route::prefix('/whatsapp')->group(function () {
        // WhatsApp Template Status Webhook
        Route::get('/templates-status', [WhatsAppTemplateWebhookController::class, 'verifyMetaWebhook']);

        Route::post('/templates-status', [WhatsAppTemplateWebhookController::class, 'handleTemplateStatusWebhook'])
            ->middleware('throttle:60,1');

        // WhatsApp Business API Webhook
        Route::post('/messages', [WhatsAppLeadController::class, 'handleWebhook']);
    });

    Route::prefix('/twilio')->group(function () {
        // Twilio Delivery Status Webhook
        Route::post('/status', [TwilioWebhookController::class, 'handleStatusCallback']);

        // Twilio Template Status Webhook
        Route::post('/templates-status', [TwilioTemplateWebhookController::class, 'handleTemplateStatusWebhook'])
            ->middleware('throttle:60,1');
    });

    // IndiaMart Lead Webhook
    // Route::post('/indiamart/leads', [IndiaMartLeadController::class, 'handleWebhook'])
    //     ->name('api.webhooks.indiamart.leads');

    // // JustDial Lead Webhook
    // Route::post('/justdial/leads', [JustDialLeadController::class, 'handleWebhook'])
    //     ->name('api.webhooks.justdial.leads');

    // Razorpay Payment Webhook
    Route::post('/razorpay/payment', [RazorpayWebhookController::class, 'handlePaymentWebhook'])
        ->name('api.webhooks.razorpay.payment');

    // Webhook Verification Routes (GET)
    Route::get('/facebook/leads', [FacebookLeadController::class, 'verifyWebhook'])
        ->name('api.webhooks.facebook.verify');

    Route::get('/instagram/leads', [InstagramLeadController::class, 'verifyWebhook'])
        ->name('api.webhooks.instagram.verify');

    Route::get('/whatsapp/messages', [WhatsAppLeadController::class, 'verifyWebhook'])
        ->name('api.webhooks.whatsapp.verify');

    // Route::get('/indiamart/leads', [IndiaMartLeadController::class, 'verifyWebhook'])
    //     ->name('api.webhooks.indiamart.verify');

    // Route::get('/justdial/leads', [JustDialLeadController::class, 'verifyWebhook'])
    //     ->name('api.webhooks.justdial.verify');

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
        $tenant = Filament::getTenant();
        if (! $user || ! $tenant?->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $gym = Gym::where('id', $tenant->id)->first();
        if (! $gym) {
            return response()->json(['error' => 'No gym found for tenant'], 404);
        }

        return Lead::where('source', $source)
            ->where('gym_id', $gym->id)
            ->paginate(20);
    })->where('source', 'facebook|instagram|whatsapp|indiamart|justdial|social_media')
        ->name('api.leads.by-source');

    // Get lead analytics
    Route::get('/analytics', function (Request $request) {
        $user = $request->user();
        if (! $user || ! $user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $gym = Gym::where('tenant_id', $user->tenant_id)->first();
        if (! $gym) {
            return response()->json(['error' => 'No gym found for tenant'], 404);
        }

        $gymId = $gym->id;

        $analytics = [
            'total_leads' => Lead::where('gym_id', $gymId)->count(),
            'by_source' => Lead::where('gym_id', $gymId)
                ->selectRaw('source, COUNT(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source'),
            'by_status' => Lead::where('gym_id', $gymId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'this_month' => Lead::where('gym_id', $gymId)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'last_7_days' => Lead::where('gym_id', $gymId)
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
        if (! $user || ! $user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return TenantSocialConfig::where('tenant_id', $user->tenant_id)
            ->get();
    })->name('api.social-config.index');

    // Get specific configuration
    Route::get('/{id}', function (Request $request, $id) {
        $user = $request->user();
        if (! $user || ! $user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return TenantSocialConfig::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->firstOrFail();
    })->name('api.social-config.show');

    // Toggle configuration status
    Route::patch('/{id}/toggle', function (Request $request, $id) {
        $user = $request->user();
        if (! $user || ! $user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $config = TenantSocialConfig::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->firstOrFail();

        $config->update(['is_active' => ! $config->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Configuration status updated',
            'is_active' => $config->is_active,
        ]);
    })->name('api.social-config.toggle');
});

// Reports API Routes for BI Tools
Route::prefix('reports')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/members', [ReportApiController::class, 'members'])->name('api.reports.members');
    Route::get('/revenue', [ReportApiController::class, 'revenue'])->name('api.reports.revenue');
    Route::get('/leads', [ReportApiController::class, 'leads'])->name('api.reports.leads');
    Route::get('/attendance', [ReportApiController::class, 'attendance'])->name('api.reports.attendance');
    Route::get('/dashboard', [ReportApiController::class, 'dashboard'])->name('api.reports.dashboard');
});

// Biometric Device API Routes
Route::prefix('v1/biometric')->middleware([AuthenticateBiometricDevice::class])->group(function () {
    Route::post('/check-in', [BiometricCheckInController::class, 'checkIn'])
        ->name('api.biometric.check-in');
    Route::post('/check-out', [BiometricCheckInController::class, 'checkOut'])
        ->name('api.biometric.check-out');
    Route::post('/enroll', [BiometricEnrollController::class, 'enroll'])
        ->name('api.biometric.enroll');
    Route::post('/enroll-sync', [BiometricEnrollController::class, 'syncFromDevice'])
        ->name('api.biometric.enroll-sync');
    Route::delete('/template/{template}', [BiometricEnrollController::class, 'deleteTemplate'])
        ->name('api.biometric.template.delete');
    Route::post('/heartbeat', [BiometricDeviceController::class, 'heartbeat'])
        ->name('api.biometric.heartbeat');
    Route::get('/device/config', [BiometricDeviceController::class, 'config'])
        ->name('api.biometric.config');
    Route::get('/device/test', [BiometricDeviceController::class, 'testConnection'])
        ->name('api.biometric.test');

    // Offline sync route (uses biometric device auth)
    Route::post('/offline-sync', [BiometricOfflineSyncController::class, 'sync'])
        ->name('api.biometric.offline-sync');
});

// Vendor webhook routes (token verified inside controller)
Route::post('/v1/biometric/webhook/{token}', [BiometricWebhookController::class, 'handle'])
    ->name('api.biometric.webhook');
