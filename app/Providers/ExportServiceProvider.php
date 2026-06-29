<?php

declare(strict_types=1);

namespace App\Providers;

use Exception;
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
            if (! $export->file_name || ! $export->file_disk) {
                Log::error('Export file validation failed: missing file_name or file_disk', [
                    'export_id' => $export->id,
                    'file_name' => $export->file_name,
                    'file_disk' => $export->file_disk,
                ]);

                return false;
            }

            $disk = Storage::disk($export->file_disk);
            $fileName = $export->file_name;

            // Check if file exists exactly as stored
            if (! $disk->exists($fileName)) {
                // Try with extensions and directory structure
                $found = false;
                $pathsToCheck = [
                    $fileName,
                    $fileName . '.xlsx',
                    $fileName . '.csv',
                    'filament_exports/' . $export->id . '/' . $fileName,
                    'filament_exports/' . $export->id . '/' . $fileName . '.xlsx',
                    'filament_exports/' . $export->id . '/' . $fileName . '.csv',
                ];

                foreach ($pathsToCheck as $path) {
                    if ($disk->exists($path)) {
                        $export->file_name = $path;
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    // Save the corrected filename to the database
                    $export->save();

                    return true;
                }
                Log::error('Export file validation failed: file does not exist on disk', [
                    'export_id' => $export->id,
                    'file_name' => $export->file_name,
                    'file_disk' => $export->file_disk,
                    'checked_paths' => $pathsToCheck,
                ]);

                return false;
            }

            // Check if the file is readable
            $stream = $disk->readStream($export->file_name);
            if (! $stream) {
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
        } catch (Exception $e) {
            Log::error('Export file validation failed with exception', [
                'export_id' => $export->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
