<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\GymMember;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class GymMemberExporter extends Exporter
{
    protected static ?string $model = GymMember::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('email'),
            ExportColumn::make('phone'),
            ExportColumn::make('weight'),
            ExportColumn::make('height'),
            ExportColumn::make('age'),
            ExportColumn::make('dob'),
            ExportColumn::make('membership_start'),
            ExportColumn::make('membership_end'),
            ExportColumn::make('payment_status')
                ->formatStateUsing(fn ($state) => $state?->value ?? 'N/A'),
            ExportColumn::make('trainer.name'),
            ExportColumn::make('membershipPlan.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your member export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
