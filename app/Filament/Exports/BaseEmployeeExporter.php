<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\User;
use Exception;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

abstract class BaseEmployeeExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('email'),
            ExportColumn::make('phone'),
            ExportColumn::make('employeeDetails.hire_date')
                ->label('Hire Date')
                ->getStateUsing(fn ($record) => Date::parse($record->employeeDetails?->hire_date)->format('Y-m-d')),
            ExportColumn::make('employeeDetails.monthly_salary')
                ->label('Monthly Salary'),
            ExportColumn::make('employeeDetails.date_of_birth')
                ->label('Date of Birth')
                ->getStateUsing(fn ($record) => Date::parse($record->employeeDetails?->date_of_birth)->format('Y-m-d')),
            ExportColumn::make('employeeDetails.gender')
                ->label('Gender')
                ->formatStateUsing(fn ($state) => $state?->value ?? 'N/A'),
            ExportColumn::make('employeeDetails.marital_status')
                ->label('Marital Status')
                ->formatStateUsing(fn ($state) => $state?->value ?? 'N/A'),
            ExportColumn::make('employeeDetails.employment_type')
                ->label('Employment Type'),
            ExportColumn::make('employeeDetails.emergency_contact_name')
                ->label('Emergency Contact Name'),
            ExportColumn::make('employeeDetails.emergency_contact_relationship')
                ->label('Emergency Contact Relationship'),
            ExportColumn::make('employeeDetails.emergency_contact_phone')
                ->label('Emergency Contact Phone'),
            ExportColumn::make('employeeDetails.emergency_contact_email')
                ->label('Emergency Contact Email'),
            ExportColumn::make('employeeDetails.skills')
                ->label('Skills')
                ->listAsJson(),
        ];
    }

    public function getFormats(): array
    {
        return [
            ExportFormat::Xlsx,
        ];
    }

    public function setUp(): void
    {
        // Check if directory exists and is writable
        $path = storage_path('app/filament_exports');

        if (! file_exists($path)) {
            mkdir($path, 0775, true);
        }

        throw_unless(is_writable($path), Exception::class, "Export directory not writable: {$path}");
    }

    /**
     * Validate that the export file exists before attempting download
     */
    public static function validateExportFile(Export $export): bool
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

                if (! $found) {
                    Log::error('Export file validation failed: file does not exist on disk', [
                        'export_id' => $export->id,
                        'file_name' => $export->file_name,
                        'file_disk' => $export->file_disk,
                        'checked_paths' => $pathsToCheck,
                    ]);

                    return false;
                }
            }

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

    /**
     * Handle export download errors and show user-friendly notifications
     */
    public static function handleDownloadError(Export $export): void
    {
        if (! static::validateExportFile($export)) {
            // Show error notification to user
            Notification::make()
                ->title('Export Download Failed')
                ->body('The export file is not available. This may be due to a temporary issue. Please try regenerating the export.')
                ->danger()
                ->persistent()
                ->send();

            // Log the error for debugging
            Log::error('Export download failed - file not available', [
                'file_name' => $export->file_name,
                'file_disk' => $export->file_disk,
            ]);
        }
    }

    abstract public static function getCompletedNotificationBody(Export $export): string;
}
