<?php

use App\Livewire\Frontend\HomeController;
use App\Livewire\GymOwnerRegister;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

// Route::get('/', function () {
//     return view('welcome');
// })->name('home');

Route::get('/', HomeController::class)->name('home');



Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('gym-register', GymOwnerRegister::class)->name('gym.register');
// Route::view('gym-register', 'gym-register')->name('gym.register');

Route::middleware(['auth'])->group(function () {
    // Download failed rows CSV
    Route::get('/download-failed-rows/{file}', function ($file) {
        \Illuminate\Support\Facades\Log::info('Attempting to download failed rows CSV', ['file' => $file]);
        $fullPath = 'imports/' . $file;
        $storagePath = storage_path('app/' . $fullPath);
        if (Storage::disk('local')->exists($fullPath)) {
            \Illuminate\Support\Facades\Log::info('File found, initiating download', ['file' => $fullPath, 'storage_path' => $storagePath]);

            return response()->download($storagePath);
        }
        \Illuminate\Support\Facades\Log::error('Failed rows CSV not found', ['file' => $fullPath, 'storage_path' => $storagePath]);
        Notification::make()
            ->title('Error')
            ->body('The requested error report file was not found.')
            ->danger()
            ->send();

        return redirect()->route('filament.admin.resources.gym-members.index')
            ->with('error', 'File not found.');
    })->name('download.failed.rows');

    Route::get('/filament/failed-import-download/{importId}', function ($importId) {
        $import = Import::where('id', $importId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Serve the file
        $fullPath = Storage::disk('public')->path($import->file_path);
        if (!file_exists($fullPath)) {
            abort(404, 'File not found.');
        }

        return response()->download($fullPath, basename($fullPath));
    })->name('filament.failed-import.download');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__ . '/auth.php';
