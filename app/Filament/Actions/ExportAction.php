<?php

namespace App\Filament\Actions;

use App\Traits\ResolvesExportImportPermission;
use Filament\Actions\ExportAction as BaseExportAction;
use Filament\Actions\Exports\Exporter;

class ExportAction extends BaseExportAction
{
    use ResolvesExportImportPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Export')
            ->icon('heroicon-o-arrow-up-tray');

        $this->setUpPermission(prefix: 'export', propertyName: 'exporter');
    }

    /**
     * @param  class-string<Exporter>  $exporter  The exporter class
     */
    public static function forExporter(string $exporter): static
    {
        return static::make()
            ->exporter($exporter);
    }
}
