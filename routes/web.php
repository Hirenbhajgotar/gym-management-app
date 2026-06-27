<?php

use App\Http\Controllers\BiometricConsentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MemberInvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SharedReportController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WhatsApp\EmbeddedSignupController;
use App\Livewire\Frontend\BlogDetail;
use App\Livewire\Frontend\BlogListing;
use App\Livewire\Frontend\CmsPageController;
use App\Livewire\Frontend\HomeController;
use App\Livewire\Frontend\ProfileController;
use App\Livewire\Frontend\QRAttendance;
use App\Livewire\Frontend\RequestDemo;
use App\Livewire\GymOwnerRegister;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', HomeController::class)->name('home');

Route::get('/blog', BlogListing::class)->name('blog');

Route::get('/blog/{slug}', BlogDetail::class)->name('blog.show');

Route::get('/page/{slug}', CmsPageController::class)->name('cms.page');

Route::get('/request-demo', RequestDemo::class)->name('request-demo');

Route::get('/profile', ProfileController::class)
    ->name('profile')
    ->middleware(['auth', 'verified']);

Route::get('/thank-you', function () {
    if (! session()->has('success')) {
        return to_route('home');
    }

    return view('thank-you');
})->name('thank-you');

// Route::view('dashboard', 'dashboard')
//     ->middleware(['auth', 'verified'])
//     ->name('dashboard');

Route::get('business-register', GymOwnerRegister::class)->name('gym.register');

Route::middleware(['auth'])->group(function () {
    Route::get('/clear-cache', function () {
        Artisan::call('optimize:clear');

        return to_route('home');
    })->name('clear-cache');

    // Download failed rows CSV
    Route::get('/download-failed-rows/{file}', function ($file) {
        Log::info(
            'Attempting to download failed rows CSV',
            ['file' => $file],
        );
        $fullPath = 'imports/' . $file;
        $storagePath = storage_path('app/' . $fullPath);
        if (Storage::disk('local')->exists($fullPath)) {
            Log::info(
                'File found, initiating download',
                ['file' => $fullPath, 'storage_path' => $storagePath],
            );

            return response()->download($storagePath);
        }
        Log::error('Failed rows CSV not found', [
            'file' => $fullPath,
            'storage_path' => $storagePath,
        ]);
        Notification::make()
            ->title('Error')
            ->body('The requested error report file was not found.')
            ->danger()
            ->send();

        return to_route('filament.admin.resources.gym-members.index')
            ->with('error', 'File not found.');
    })->name('download.failed.rows');

    Route::get('/filament/failed-import-download/{importId}', function ($importId) {
        $import = Import::query()->where('id', $importId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Check if file path exists and is valid
        abort_if(blank($import->file_path) || $import->file_path === 'N/A', 404, 'No failed rows report available for this import.');

        // Build the full path to the file in storage/app/public
        $fullPath = storage_path('app/public/' . $import->file_path);

        // Check if file exists
        abort_unless(file_exists($fullPath), 404, 'File not found.');

        $fileName = basename($import->file_path);

        return response()->download($fullPath, $fileName);
    })->name('filament.failed-import.download');

    // Route::redirect('settings', 'settings/profile');

    // Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    // Volt::route('settings/password', 'settings.password')->name('settings.password');
    // Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::get('/download-sample/{file}', function ($file, Request $request) {
    $fileName = $request->get('fileName', 'sample.xlsx');
    $path = 'import/templates/xls/' . $file;
    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->download($path, $fileName);
})->name('download.sample');

// Payment Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/payments/create-order', [PaymentController::class, 'createOrder'])->name('payments.create-order');
    Route::post('/payments/verify', [PaymentController::class, 'verifyPayment'])->name('payments.verify');
    Route::get('/payments/checkout', [PaymentController::class, 'checkout'])->name('payments.checkout');
    Route::get('/payments/success', [PaymentController::class, 'success'])->name('payments.success');
    Route::get('/payments/failure', [PaymentController::class, 'failure'])->name('payments.failure');
});

// Gym Owner Registration Checkout Routes (no auth required for initial registration)
Route::prefix('gym-owner')->name('gym-owner.')->group(function () {
    Route::get('/checkout', [SubscriptionController::class, 'handleGymOwnerCheckout'])->name('checkout');
    Route::post('/verify-payment', [SubscriptionController::class, 'verifyGymOwnerPayment'])->name('verify-payment');
});

// Subscription Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/subscriptions/checkout', [SubscriptionController::class, 'checkout'])->name('subscriptions.checkout');
    Route::post('/subscriptions/verify-payment', [SubscriptionController::class, 'verifyPayment'])->name('subscriptions.verify-payment');
    Route::get('/subscriptions/payment/{orderId}', [SubscriptionController::class, 'completePendingPayment'])->name('subscription.payment.complete');

    // Member Invoice Route
    Route::get('/member/invoice/{payment}', [MemberInvoiceController::class, 'show'])->name('member.invoice.show');
});

// Razorpay Webhook (no auth required)
Route::post('/payments/webhook', [PaymentController::class, 'handleWebhook'])->name('payments.webhook');

// Sitemap Route
Route::get('/{filename}.xml', function (string $filename) {
    $path = public_path($filename . '.xml');

    if (! file_exists($path)) {
        Log::error('Sitemap not found');

        abort(404);
    }

    return response()->file($path, ['Content-Type' => 'application/xml']);
})->whereIn('filename', config('sitemap.allowed_filenames', []))->name('sitemap');

// WhatsApp Embedded Signup Routes
Route::middleware(['auth'])->prefix('whatsapp/embedded-signup')->name('whatsapp.embedded-signup.')->group(function () {
    Route::get('/redirect/{tenant}', [EmbeddedSignupController::class, 'redirect'])->name('redirect');

    Route::get('/callback', [EmbeddedSignupController::class, 'callback'])
        ->name('callback')
        ->middleware('throttle:5,1'); // 5 attempts per minute per IP

    Route::post('/manual-connect/{tenant}', [EmbeddedSignupController::class, 'manualConnect'])->name('manual-connect');
});

require __DIR__ . '/auth.php';

Route::get('/download-sample-excel/{type}', [ImportController::class, 'downloadSample'])->name('download.sample.excel');

// Shared Reports Routes (public access)
Route::prefix('shared-reports')->name('shared-reports.')->group(function () {
    Route::get('/{token}', [SharedReportController::class, 'show'])->name('show');
});

// Biometric Consent Routes (public, signed URL protected)
Route::get('/consent/biometric', [BiometricConsentController::class, 'show'])
    ->name('biometric.consent.show')
    ->middleware('signed');

Route::post('/consent/biometric', [BiometricConsentController::class, 'store'])
    ->name('biometric.consent.store')
    ->middleware('signed');

// QR Code Attendance Routes (public)
Route::get('/attendance/qr-checkin/{gymId}/{token}', QRAttendance::class)
    ->name('attendance.qr.checkin');

Route::get('/attendance/qr-checkout/{gymId}/{token}', QRAttendance::class)
    ->name('attendance.qr.checkout');
