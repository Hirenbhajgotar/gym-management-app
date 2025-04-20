<?php

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__ . '/auth.php';
