<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ExportDownloadMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is an export download request
        $isExportRequest = $this->isExportDownloadRequest($request);

        if ($isExportRequest) {
            $exportId = $this->extractExportId($request);
            Log::info('ExportDownloadMiddleware: Detected export request', [
                'url' => $request->url(),
                'export_id' => $exportId,
            ]);

            if ($exportId) {
                $export = Export::query()->find($exportId);

                if ($export) {
                    // Check authorization
                    if ($export->user_id !== auth()->id()) {
                        Log::warning('ExportDownloadMiddleware: Unauthorized access attempt', ['user_id' => auth()->id(), 'export_user_id' => $export->user_id]);
                        abort(403);
                    }

                    $isValid = $this->validateExportFile($export);
                    Log::info('ExportDownloadMiddleware: Validation result', ['is_valid' => $isValid, 'file_name' => $export->file_name]);

                    if ($isValid) {
                        // If the file is an XLSX file, serve it directly to bypass XlsxDownloader issues
                        // XlsxDownloader expects a CSV source to convert, but if we already have an XLSX,
                        // we should just download it.
                        if (str_ends_with($export->file_name, '.xlsx')) {
                            Log::info('ExportDownloadMiddleware: Serving XLSX directly', ['file_name' => $export->file_name]);

                            return Storage::disk($export->file_disk)->download($export->file_name);
                        }
                    } else {
                        Log::error('Export file validation failed in middleware', [
                            'export_id' => $export->id,
                            'file_name' => $export->file_name,
                            'file_disk' => $export->file_disk,
                            'url' => $request->url(),
                        ]);

                        return response()->json([
                            'error' => 'Export file is not available. Please try regenerating the export.',
                            'export_id' => $export->id,
                        ], 404);
                    }
                } else {
                    Log::warning('ExportDownloadMiddleware: Export not found', ['export_id' => $exportId]);
                }
            }
        }

        return $next($request);
    }

    protected function isExportDownloadRequest(Request $request): bool
    {
        $path = $request->path();

        return str_contains($path, 'filament') &&
            str_contains($path, 'export') &&
            str_contains($path, 'download');
    }

    protected function extractExportId(Request $request): ?int
    {
        $path = $request->path();
        Log::info('ExportDownloadMiddleware: Extracting ID from path', ['path' => $path]);

        // Try to extract export ID from URL path
        // Matches .../exports/{id}/download
        if (preg_match('/exports?\/(\d+)\/download/', $path, $matches)) {
            return (int) $matches[1];
        }

        // Try to extract from query parameters
        $exportId = $request->query('export_id') ?? $request->query('id');
        if ($exportId) {
            return (int) $exportId;
        }

        return null;
    }

    protected function validateExportFile(Export $export): bool
    {
        try {
            if (! $export->file_name || ! $export->file_disk) {
                return false;
            }

            $disk = Storage::disk($export->file_disk);
            $fileName = $export->file_name;

            // Log environment details
            Log::info('ExportDownloadMiddleware: Validating file', [
                'export_id' => $export->id,
                'file_name' => $fileName,
                'disk' => $export->file_disk,
                'php_user' => get_current_user(),
            ]);

            // Check if file exists exactly as stored
            if (! $disk->exists($fileName)) {
                Log::info('ExportDownloadMiddleware: Exact match failed, checking alternatives');

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
                    $exists = $disk->exists($path);
                    Log::info('ExportDownloadMiddleware: Checking path', ['path' => $path, 'exists' => $exists]);

                    if ($exists) {
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
                Log::error('ExportDownloadMiddleware: File not found in any path', [
                    'checked_paths' => $pathsToCheck,
                    'disk_root' => $disk->path(''),
                ]);
                return false;
            } else {
                Log::info('ExportDownloadMiddleware: Exact match found');
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Export file validation failed with exception', [
                'export_id' => $export->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
