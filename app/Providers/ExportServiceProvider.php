<?php

namespace App\Providers;

use Filament\Actions\Exports\Downloaders\XlsxDownloader;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class ExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Add error handling for export downloads
        $this->handleExportDownloadErrors();
    }

    protected function handleExportDownloadErrors(): void
    {
        // Listen for export download attempts and validate files
        Event::listen('filament.export.downloading', function (Export $export) {
            $this->validateExportFile($export);
        });
    }

    protected function validateExportFile(Export $export): bool
    {
        try {
            if (!$export->file_name || !$export->file_disk) {
                Log::error('Export file validation failed: missing file_name or file_disk', [
                    'export_id' => $export->id,
                    'file_name' => $export->file_name,
                    'file_disk' => $export->file_disk,
                ]);
                return false;
            }

            $disk = Storage::disk($export->file_disk);
            if (!$disk->exists($export->file_name)) {
                Log::error('Export file validation failed: file does not exist on disk', [
                    'export_id' => $export->id,
                    'file_name' => $export->file_name,
                    'file_disk' => $export->file_disk,
                ]);
                return false;
            }

            // Check if the file is readable
            $stream = $disk->readStream($export->file_name);
            if (!$stream) {
                Log::error('Export file validation failed: cannot read file stream', [
                    'export_id' => $export->id,
                    'file_name' => $export->file_name,
                    'file_disk' => $export->file_disk,
                ]);
                return false;
            }

            // Close the stream since we're just testing
            fclose($stream);

            return true;
        } catch (\Exception $e) {
            Log::error('Export file validation failed with exception', [
                'export_id' => $export->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
