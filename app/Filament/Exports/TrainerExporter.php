<?php

namespace App\Filament\Exports;

use App\Models\Trainer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TrainerExporter extends Exporter
{
    protected static ?string $model = Trainer::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('email'),
            ExportColumn::make('phone'),
            ExportColumn::make('specialization'),
            ExportColumn::make('experience_years')
                ->label('Experience (Years)'),
            ExportColumn::make('members_count')
                ->label('Assigned Members')
                ->counts('members'),
            ExportColumn::make('is_available')
                ->label('Is Available')
                ->getStateUsing(fn ($record) => $record->is_available ? 'Yes' : 'No'),
            ExportColumn::make('available_hours'),
            ExportColumn::make('basic_salary')
                ->label('Basic Salary')
                ->getStateUsing(fn ($record) => $record->basic_salary),
            ExportColumn::make('pt_salary_per_member')
                ->label('PT Salary/Member'),
            ExportColumn::make('total_earnings')
                ->label('Total Earnings')
                ->getStateUsing(fn ($record) => $record->total_earnings),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your trainer export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
