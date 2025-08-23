<?php

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
        if ($this->isExportDownloadRequest($request)) {
            $exportId = $this->extractExportId($request);

            if ($exportId) {
                $export = Export::find($exportId);

                if ($export && !$this->validateExportFile($export)) {
                    Log::error('Export file validation failed in middleware', [
                        'export_id' => $export->id,
                        'file_name' => $export->file_name,
                        'file_disk' => $export->file_disk,
                        'url' => $request->url(),
                    ]);

                    // Return a proper error response instead of letting it fail
                    return response()->json([
                        'error' => 'Export file is not available. Please try regenerating the export.',
                        'export_id' => $export->id,
                    ], 404);
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

        // Try to extract export ID from URL path
        if (preg_match('/export\/(\d+)\/download/', $path, $matches)) {
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
