<?php

namespace App\Filament\Admin\Resources\MembershipPlanResource\Exports;

use App\Models\MembershipPlan;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MembershipPlanExporter extends Exporter
{
    protected static ?string $model = MembershipPlan::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('gym.name')->label('Gym'),
            ExportColumn::make('price'),
            ExportColumn::make('currency'),
            ExportColumn::make('duration')->label('Duration (Months)'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('members_count')->label('Members Count'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your membership plan export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' have been exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
