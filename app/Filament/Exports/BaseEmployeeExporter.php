<?php

namespace App\Filament\Exports;

use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
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
                ->getStateUsing(fn ($record) => Carbon::parse($record->employeeDetails?->hire_date)->format('Y-m-d')),
            ExportColumn::make('employeeDetails.monthly_salary'),
            ExportColumn::make('employeeDetails.date_of_birth')
                ->label('Date of Birth')
                ->getStateUsing(fn ($record) => Carbon::parse($record->employeeDetails?->date_of_birth)->format('Y-m-d')),
            ExportColumn::make('employeeDetails.gender')
                ->formatStateUsing(fn ($state) => $state?->value ?? 'N/A'),
            ExportColumn::make('employeeDetails.marital_status')
                ->formatStateUsing(fn ($state) => $state?->value ?? 'N/A'),
            ExportColumn::make('employeeDetails.employment_type'),
            ExportColumn::make('employeeDetails.emergency_contact_name'),
            ExportColumn::make('employeeDetails.emergency_contact_relationship'),
            ExportColumn::make('employeeDetails.emergency_contact_phone'),
            ExportColumn::make('employeeDetails.emergency_contact_email'),
            ExportColumn::make('employeeDetails.skills')
                ->listAsJson(),
        ];
    }

    public function getFormats(): array
    {
        return [
            ExportFormat::Xlsx,
        ];
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
            if (! $disk->exists($export->file_name)) {
                Log::error('Export file validation failed: file does not exist on disk', [
                    'export_id' => $export->id,
                    'file_name' => $export->file_name,
                    'file_disk' => $export->file_disk,
                ]);

                return false;
            }

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
