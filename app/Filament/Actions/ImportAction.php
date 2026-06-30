<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Filament\Actions\Imports\ExcelImportAction;
use App\Traits\ResolvesExportImportPermission;

class ImportAction extends ExcelImportAction
{
    use ResolvesExportImportPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Import')
            ->icon('heroicon-o-arrow-down-tray');

        $this->setUpPermission(prefix: 'import', propertyName: 'importer');
    }

    public static function forImporter(string $importer): static
    {
        return static::make()->importer($importer);
    }
}
