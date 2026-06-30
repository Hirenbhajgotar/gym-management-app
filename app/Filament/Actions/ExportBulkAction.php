<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Traits\ResolvesExportImportPermission;
use Filament\Tables\Actions\ExportBulkAction as BaseExportBulkAction;

class ExportBulkAction extends BaseExportBulkAction
{
    use ResolvesExportImportPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Export')
            ->icon('heroicon-o-arrow-up-tray');

        $this->setUpPermission(prefix: 'export', propertyName: 'exporter');
    }
}
