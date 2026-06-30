<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use Filament\Actions\Exports\Models\Export;

class ManagerExporter extends BaseEmployeeExporter
{
    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your manager export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
