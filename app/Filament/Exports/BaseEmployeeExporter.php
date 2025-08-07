<?php

namespace App\Filament\Exports;

use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

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
                ->getStateUsing(fn($record) => Carbon::parse($record->employeeDetails?->hire_date)->format('Y-m-d')),
            ExportColumn::make('employeeDetails.monthly_salary'),
            ExportColumn::make('employeeDetails.date_of_birth')
                ->label('Date of Birth')
                ->getStateUsing(fn($record) => Carbon::parse($record->employeeDetails?->date_of_birth)->format('Y-m-d')),
            ExportColumn::make('employeeDetails.gender')
                ->formatStateUsing(fn($state) => $state?->value ?? 'N/A'),
            ExportColumn::make('employeeDetails.marital_status')
                ->formatStateUsing(fn($state) => $state?->value ?? 'N/A'),
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

    abstract public static function getCompletedNotificationBody(Export $export): string;
}
